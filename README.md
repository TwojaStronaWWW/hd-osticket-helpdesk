# HD Service Desk (osTicket) – https://hd.twojastronawww.pl/

> **Produkcyjne wdrożenie i administracja własną instancją systemu Helpdesk / Service Desk opartą na silniku osTicket pod domeną [hd.twojastronawww.pl](https://hd.twojastronawww.pl/).**  
> Projekt zrealizowany w oparciu o standardy zarządzania usługami IT **ITIL® 4 Foundation** oraz normy bezpieczeństwa informacji **ISO/IEC 27001:2023**.

---

## 🎯 Cel wdrożenia i architektura biznesowa

Celem wdrożenia było stworzenie scentralizowanego punktu kontaktowego (**SPOC – Single Point of Contact**) do obsługi zgłoszeń technicznych, zapytań wdrożeniowych oraz incydentów w ramach realizowanych projektów IT i web developmentu.

### Kluczowe założenia:
- **Standaryzacja obsługi:** Eliminacja rozproszonych zgłoszeń (e-mail, telefon, komunikatory) na rzecz jednego spójnego rejestru ticketów.
- **Mierzalność SLA:** Precyzyjne definiowanie czasów pierwszej reakcji oraz czasów rozwiązania w zależności od priorytetu incydentu.
- **Zgodność z RODO i ISO 27001:** Ochrona danych osobowych zgłaszających, szyfrowanie transmisji danych (SSL/TLS), rozliczalność działań agentów.

---

## 🛠️ Stack Technologiczny i Infrastruktura

| Warstwa | Technologia / Narzędzie | Zastosowanie |
| :--- | :--- | :--- |
| **Aplikacja** | [osTicket](https://osticket.com/) (Core Engine) | Otwartoźródłowy silnik obsługi zgłoszeń (PHP) |
| **Środowisko uruchomieniowe** | LAMP / LEMP (PHP 8.x) | Serwer WWW + interpreter PHP z modułami `mysqli`, `gd`, `intl`, `mbstring`, `openssl` |
| **Baza danych** | MySQL / MariaDB (InnoDB, UTF-8 mb4) | Relacyjna baza danych ticketów, wątków i uprawnień |
| **Bezpieczeństwo & Sieć** | Cloudflare (DNS, SSL/TLS Full Strict, WAF) | Ochrona przed atakami DDoS, wymuszenie HTTPS, ukrycie IP serwera źródłowego |
| **Poczta & Komunikacja** | Protokół SMTP / IMAP (SSL) | Automatyczny pobór maili do kolejki oraz dwustronna wysyłka powiadomień |

---

## 📋 Implementacja procesów ITIL® 4

### 1. Zarządzanie Incydentami i Wnioskami o Usługi (Incident & Service Request Mgmt)
- **Kategoryzacja zgłoszeń (Help Topics):**
  - `Awaria krytyczna (P1 / Critical)` – niedostępność serwisów produkcyjnych.
  - `Incydent techniczny (P2 / Major)` – częściowa degradacja funkcjonalności.
  - `Wniosek o zmianę (Change Request)` – nowe zapotrzebowanie lub modyfikacja.
  - `Wsparcie ogólne (General Support)` – pytania konfiguracyjne i konsultacje.

### 2. Plany SLA (Service Level Agreements)
Zgodnie z dobrymi praktykami ITIL 4 skonfigurowano reguły automatycznego naliczania czasu SLA z uwzględnieniem godzin roboczych:
- **SLA P1 (Krytyczny):** Pierwsza reakcja < 1h | Rozwiązanie < 4h.
- **SLA P2 (Wysoki):** Pierwsza reakcja < 4h | Rozwiązanie < 24h.
- **SLA P3 (Standard):** Pierwsza reakcja < 8h | Rozwiązanie < 48h.
- **Automatyczna eskalacja:** Monity o zbliżającym się przekroczeniu SLA wysyłane do opiekuna technicznego.

### 3. Organizacja Zespołu i Kolejek (Departments & Teams)
- **Dział Obsługi Klienta / L1:** Pierwsza linia weryfikacji i wstępny triage.
- **Dział Techniczny / L2 & Wdrożenia:** Realizacja złożonych zadań technicznych i poprawek w kodzie/infrastrukturze.
- **Role i Uprawnienia:** Zasada ograniczonego dostępu (**Need-to-know / Least Privilege**).

---

## 🔒 Bezpieczeństwo i Ochrona Danych (ISO/IEC 27001 & RODO)

1. **Szyfrowanie transmisji:**
   - Wymuszone przekierowanie całego ruchu na HTTPS za pośrednictwem Cloudflare Edge SSL.
   - Włączone nagłówki bezpieczeństwa: `Strict-Transport-Security` (HSTS), `X-Content-Type-Options: nosniff`.
2. **Ochrona pliku konfiguracyjnego:**
   - Plik `include/ost-config.php` zabezpieczony restrykcyjnymi uprawnieniami systemowymi (`chmod 644` lub `444`).
   - Brak jakichkolwiek haseł produkcyjnych, kluczy szyfrujących (`SECRET_SALT`) czy danych osobowych w publicznym repozytorium Gita.
3. **Zarządzanie sesjami i tożsamością:**
   - Automatyczne wygaszanie sesji po okresie bezczynności agenta.
   - Maskowanie wrażliwych danych w powiadomieniach zewnętrznych.

---

## 💾 Polityka Kopii Zapasowych i Disaster Recovery (Backup & BCP)

Aby zabezpieczyć integralność systemu i umożliwić szybkie odtworzenie po awarii (**Disaster Recovery**):
1. **Zrzut bazy danych:** Skrypt `backup.sh` wykonujący cykliczny zrzut MySQL z kompresją `gzip`:
   ```bash
   mysqldump -u [user] -p[pass] --single-transaction --quick [dbname] | gzip > /backup/osticket_$(date +%F).sql.gz
   ```
2. **Rotacja kopii:** Przechowywanie ostatnich 14 dni kopii dziennych oraz 4 kopii tygodniowych.
3. **Bezpieczne przechowywanie konfiguracji:** Szyfrowana kopia `ost-config.php` zdeponowana w bezpiecznym menedżerze sekretów.

---

## 🚀 Struktura Repozytorium

```plaintext
├── config/
│   └── ost-config.sample.php    # Wzorcowy plik konfiguracyjny (bezpieczne placeholdery)
├── scripts/
│   └── backup.sh                # Skrypt zautomatyzowanego backupu bazy danych MySQL
├── .gitignore                   # Zabezpieczenie przed przypadkowym commitem danych wrażliwych
└── README.md                    # Dokumentacja techniczna i procesowa
```

---

## 👤 Autor i Kontakt

- **Wdrożenie i Administracja:** Krzysztof Początek
- **Profil GitHub:** [@kpoczatek-dev](https://github.com/kpoczatek-dev)
- **Organizacja:** [Twoja Strona WWW](https://github.com/TwojaStronaWWW)
- **System Produkcyjny:** [https://hd.twojastronawww.pl/](https://hd.twojastronawww.pl/)
- **LinkedIn:** [Krzysztof Początek](https://linkedin.com/in/krzysztof-początek-a94a06166)
