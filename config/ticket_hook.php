<?php
declare(strict_types=1);

/**
 * Centralny Hub Zgłoszeń i Powiadomień Leadów (osTicket + E-mail + Ntfy)
 * Lokalizacja produkcyjna: https://hd.twojastronawww.pl/ticket_hook.php
 *
 * Obsługuje:
 * 1. Formularze kontaktowe (twojastronawww.pl, katosalsahub.pl, fuegolingua.pl)
 * 2. Leady kontaktowe z Czatbotów AI (event = contact_lead_captured)
 * 3. Gorące Leady z Czatbotów AI (event = hot_lead_detected — zaangażowana rozmowa)
 */

header_remove('X-Powered-By');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && strtolower(trim((string)$_SERVER['HTTP_SEC_FETCH_SITE'])) === 'cross-site') {
    http_response_code(403);
    echo json_encode(['error' => 'Cross-Site Request Blocked']);
    exit;
}

/**
 * Pobiera zmienną środowiskową z getenv(), $_ENV lub głównego pliku .env na LH.pl
 */
function get_hook_env(string $key, string $default = ''): string {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return (string)$val;
    }
    if (!empty($_ENV[$key])) {
        return (string)$_ENV[$key];
    }
    $envCandidates = [
        __DIR__ . '/.env',
        __DIR__ . '/include/.env',
    ];
    foreach ($envCandidates as $envPath) {
        if (@is_readable($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    if (trim($k) === $key) {
                        return trim($v, " \t\n\r\0\x0B\"'");
                    }
                }
            }
        }
    }
    return $default;
}

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) {
    $data = is_array($_POST) ? $_POST : [];
}

$expectedApiKey = get_hook_env('OSTICKET_API_KEY');
$apiKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $data['api_key'] ?? $_POST['api_key'] ?? '');

if ($expectedApiKey === '' || $apiKey === '' || !hash_equals($expectedApiKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Valid API key required']);
    exit;
}

$centralEmail = get_hook_env('CENTRAL_LEAD_EMAIL', 'poczatek.krzysztof@gmail.com');
$event = strtolower(trim((string)($data['event'] ?? '')));

// Dedykowana ścieżka dla Porannego Raportu Systemowego (07:30: Scrapery KSH + Audyt ISO 27001)
// Wysyła bezpośredni e-mail na $centralEmail bez zaśmiecania kolejki zgłoszeń w osTicket.
if ($event === 'morning_report' || !empty($data['email_only'])) {
    $mailSubject = trim((string)($data['subject'] ?? 'Poranny Raport Systemowy (Scrapery + Audyt)'));
    $mailBody    = trim((string)($data['message'] ?? ''));
    if ($mailBody === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Empty message body']);
        exit;
    }

    $fromAddress = 'kontakt@twojastronawww.pl';
    $encodedSubj = '=?UTF-8?B?' . base64_encode($mailSubject) . '?=';
    $headers     = "From: TwojaStronaWWW & KatoSalsaHub <{$fromAddress}>\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit\r\n";

    $emailSent = @mail($centralEmail, $encodedSubj, $mailBody, $headers, "-f{$fromAddress}");
    http_response_code($emailSent ? 200 : 500);
    echo json_encode([
        'success'    => $emailSent,
        'email_sent' => $emailSent,
        'recipient'  => $centralEmail,
    ]);
    exit;
}

require_once __DIR__ . '/main.inc.php';
require_once INCLUDE_DIR . 'class.ticket.php';

$isChatbotEvent = in_array($event, ['contact_lead_captured', 'hot_lead_detected'], true);

if ($isChatbotEvent) {
    $siteId = strtolower(trim((string)($data['site_id'] ?? 'twojastronawww')));
    $siteMap = [
        'twojastronawww' => ['label' => 'TwojaStronaWWW.pl', 'topicId' => 10],
        'katosalsahub'   => ['label' => 'Kato Salsa Hub',    'topicId' => 12],
        'fuegolingua'    => ['label' => 'FuegoLingua.pl',    'topicId' => 10],
        'raricart'       => ['label' => 'Raricart.pl',       'topicId' => 10],
        'dancepuls'      => ['label' => 'DancePuls.pl',      'topicId' => 12],
    ];
    $siteInfo = $siteMap[$siteId] ?? ['label' => ucfirst($siteId), 'topicId' => 10];
    $siteLabel = $siteInfo['label'];

    $phones = is_array($data['phones'] ?? null) ? array_values(array_filter(array_map('strval', $data['phones']))) : [];
    $emails = is_array($data['emails'] ?? null) ? array_values(array_filter(array_map('strval', $data['emails']))) : [];
    $firstPhone = trim((string)($phones[0] ?? ($data['phone'] ?? '')));
    $firstEmail = trim((string)($emails[0] ?? ($data['email'] ?? '')));
    $question   = trim((string)($data['message'] ?? ''));
    $answer     = trim((string)($data['answer'] ?? ''));
    $priorList  = is_array($data['prior_questions'] ?? null) ? $data['prior_questions'] : [];
    $channel    = trim((string)($data['channel'] ?? 'chat'));

    if ($firstEmail !== '' && filter_var($firstEmail, FILTER_VALIDATE_EMAIL)) {
        $ticketEmail = $firstEmail;
    } else {
        $digits = preg_replace('/\D+/', '', $firstPhone) ?: '';
        $ticketEmail = $digits !== ''
            ? "tel-{$digits}@twojastronawww.pl"
            : "hotlead-{$siteId}-" . date('Ymd-His') . "@twojastronawww.pl";
    }

    if ($event === 'contact_lead_captured') {
        $contactLabel = trim(($firstPhone !== '' ? "TEL: {$firstPhone}" : '') . ($firstPhone !== '' && $firstEmail !== '' ? ' | ' : '') . ($firstEmail !== '' ? "EMAIL: {$firstEmail}" : ''));
        if ($contactLabel === '') {
            $contactLabel = 'Namiar z czatu';
        }
        $ticketName = $firstPhone !== '' ? "Lead Czatbot ({$firstPhone})" : "Lead Czatbot ({$firstEmail})";
        $ticketSubject = "[Lead Czatbot AI - {$siteLabel}] {$contactLabel}";
    } else {
        $ticketName = "Gorący Lead Czatbot ({$siteLabel})";
        $ticketSubject = "[Gorący Lead Czatbot AI - {$siteLabel}] Zaangażowana rozmowa (4. pytanie w sesji)";
    }

    $msgLines = [];
    $msgLines[] = $event === 'contact_lead_captured'
        ? "NOWY LEAD KONTAKTOWY Z CZATBOTA AI ({$siteLabel})"
        : "GORĄCY LEAD Z CZATBOTA AI — ZAANGAŻOWANA ROZMOWA ({$siteLabel})";
    $msgLines[] = "==================================================";
    $msgLines[] = "Strona WWW:      {$siteLabel} ({$siteId})";
    $msgLines[] = "Typ zdarzenia:   {$event} (kanał: {$channel})";
    $msgLines[] = "Telefon:         " . ($firstPhone !== '' ? implode(', ', $phones) : '-');
    $msgLines[] = "E-mail:          " . ($firstEmail !== '' ? implode(', ', $emails) : '-');
    $msgLines[] = "Data zgłoszenia: " . date('Y-m-d H:i:s');
    $msgLines[] = "--------------------------------------------------";
    $msgLines[] = "Bieżąca wiadomość klienta:";
    $msgLines[] = $question !== '' ? $question : '-';

    if ($answer !== '') {
        $msgLines[] = "";
        $msgLines[] = "Odpowiedź Asystenta AI:";
        $msgLines[] = $answer;
    }

    if (!empty($priorList)) {
        $msgLines[] = "";
        $msgLines[] = "Wcześniejsze pytania klienta w tej sesji:";
        foreach ($priorList as $pq) {
            $msgLines[] = (string)$pq;
        }
    }

    $ticketMessage = implode("\n", $msgLines);

    $data['name']        = $ticketName;
    $data['email']       = $ticketEmail;
    $data['phone']       = $firstPhone;
    $data['subject']     = $ticketSubject;
    $data['message']     = $ticketMessage;
    $data['topicId']     = (int)($data['topicId'] ?? $siteInfo['topicId']);
    $data['source']      = 'API';
    $data['autorespond'] = false;
    $data['alertuser']   = false;
} else {
    $rawEmail = trim((string)($data['email'] ?? ''));
    $rawPhone = trim((string)($data['phone'] ?? ''));

    if ($rawEmail === '' || $rawEmail === '-' || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
        $digits = preg_replace('/\D+/', '', $rawPhone) ?: date('YmdHis');
        $data['email']       = "tel-{$digits}@twojastronawww.pl";
        $data['autorespond'] = false;
        $data['alertuser']   = false;
    }

    if ($rawPhone !== '' && $rawPhone !== '-' && isset($data['message']) && strpos((string)$data['message'], $rawPhone) === false) {
        $data['message'] = "Telefon kontaktowy: {$rawPhone}\n-----------------------------------------\n" . (string)$data['message'];
    }
}

if (empty($data['email']) || empty($data['name']) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required fields: email, name, message'
    ]);
    exit;
}

$data['source'] = $data['source'] ?? 'API';
$data['ip'] = filter_var($data['ip'] ?? '', FILTER_VALIDATE_IP)
    ? $data['ip']
    : (filter_var($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', FILTER_VALIDATE_IP)
        ?: (filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '127.0.0.1'));

$topicId = (int)($data['topicId'] ?? 0);
if ($topicId > 0 && class_exists('Topic')) {
    $topic = Topic::lookup($topicId);
    if ($topic) {
        $data['deptId'] = $topic->getDeptId();
    } else {
        unset($data['topicId']);
        $data['deptId'] = 1;
    }
} else {
    $data['deptId'] = (int)($data['deptId'] ?? 1);
}

$errors = [];
$ticket = Ticket::create($data, $errors, $data['source']);

// Bezpieczny fallback jeśli osTicket odrzucił np. niestandardowe pole phone/topicId
if (!($ticket instanceof Ticket)) {
    $fallbackData = [
        'name'        => (string)$data['name'],
        'email'       => (string)$data['email'],
        'subject'     => (string)($data['subject'] ?? 'Nowe zgłoszenie'),
        'message'     => (string)$data['message'],
        'deptId'      => 1,
        'ip'          => (string)$data['ip'],
        'source'      => 'API',
        'autorespond' => false,
        'alertuser'   => false,
    ];
    $errors = [];
    $ticket = Ticket::create($fallbackData, $errors, 'API');
}

$ticketCreated = ($ticket instanceof Ticket);
$ticketNumber  = $ticketCreated ? (string)$ticket->getNumber() : 'BRAK';
$ticketId      = $ticketCreated ? (int)$ticket->getId() : 0;
$deptId        = $ticketCreated ? (int)$ticket->getDeptId() : 1;

// Dla zdarzeń z Czatbota AI (lub gdy jawnie zażądano send_email=true):
// 1) wysyłamy scentralizowany e-mail na poczatek.krzysztof@gmail.com
// 2) dla twojastronawww zapisujemy też lead kontaktowy w pliku CSV panelu leadów
$shouldSendEmail = $isChatbotEvent || !empty($data['send_email']);
$emailSent = false;

if ($shouldSendEmail && $centralEmail !== '') {
    $mailSubject = (string)($data['subject'] ?? 'Nowy Lead') . ($ticketCreated ? " [Ticket #{$ticketNumber}]" : '');
    $mailBody    = (string)$data['message']
                 . "\n\n==================================================\n"
                 . "System Ticketowy (osTicket): " . ($ticketCreated ? "Utworzono Ticket #{$ticketNumber}" : "Błąd zapisu ticketu") . "\n"
                 . "Panel Helpdesk: https://hd.twojastronawww.pl/scp/\n";

    $fromAddress = 'kontakt@twojastronawww.pl';
    $headers     = "From: TwojaStronaWWW Lead Hub <{$fromAddress}>\r\n";
    $replyCandidate = trim((string)(($data['emails'][0] ?? '') ?: ($data['email'] ?? '')));
    if ($replyCandidate !== '' && filter_var($replyCandidate, FILTER_VALIDATE_EMAIL) && strpos($replyCandidate, '@twojastronawww.pl') === false) {
        $headers .= "Reply-To: {$replyCandidate}\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $emailSent = @mail($centralEmail, $mailSubject, $mailBody, $headers, "-f{$fromAddress}");
}

if ($ticketCreated) {
    http_response_code(201);
    echo json_encode([
        'success'    => true,
        'ticket_id'  => $ticketId,
        'number'     => $ticketNumber,
        'dept_id'    => $deptId,
        'email_sent' => $emailSent,
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success'    => false,
        'errors'     => $errors,
        'email_sent' => $emailSent,
    ]);
}
