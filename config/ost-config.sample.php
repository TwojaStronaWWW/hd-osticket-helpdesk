<?php
/*********************************************************************
    ost-config.sample.php - Wzorcowy plik konfiguracyjny osTicket
    Wdrożenie: https://hd.twojastronawww.pl/
    
    UWAGA DOTYCZĄCA BEZPIECZEŃSTWA (ISO/IEC 27001):
    Nigdy nie commituj produkcyjnego pliku include/ost-config.php z hasłami do repozytorium!
    Poniższy plik stanowi wzorzec konfiguracyjny z bezpiecznymi placeholderami.
*********************************************************************/

# Ścieżka główna osTicket
define('ROOT_PATH', '/');

# Parametry połączenia z bazą danych MySQL / MariaDB
define('DBTYPE', 'mysql');
define('DBHOST', 'localhost');
define('DBNAME', 'hd_osticket_db');
define('DBUSER', 'hd_osticket_user');
define('DBPASS', 'PLACEHOLDER_SECURE_PASSWORD');

# Prefiks tabel w bazie danych (np. ost_)
define('TABLE_PREFIX', 'ost_');

# Klucz szyfrujący osTicket (generowany automatycznie podczas instalacji)
# Służy m.in. do szyfrowania haseł do kont pocztowych w bazie
define('SECRET_SALT', 'PLACEHOLDER_64_CHAR_RANDOM_HASH_SALT');

# Domyślny zestaw znaków bazy
define('DBCHARSET', 'utf8mb4');

# Konfiguracja sesji i bezpieczeństwa
define('SESSION_TTL', 86400); // 24h sesji agenta
define('MEMCACHE_SERVERS', '');

# Wersja wdrożeniowa i środowisko
define('CONFIG_ENVIRONMENT', 'production');
