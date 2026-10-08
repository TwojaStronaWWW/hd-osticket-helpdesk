#!/usr/bin/env bash
# ==============================================================================
# backup.sh - Skrypt automatycznego backupu bazy danych osTicket
# Wdrożenie: https://hd.twojastronawww.pl/
# Zgodność: ISO/IEC 27001 (Polityka Zarządzania Kopiami Zapasowymi)
# ==============================================================================

set -euo pipefail

# Konfiguracja środowiska
BACKUP_DIR="/var/backups/osticket"
DATE_TAG=$(date +"%Y%m%d_%H%M%S")
DB_NAME="hd_osticket_db"
DB_USER="hd_osticket_user"
# Hasło zaleca się pobierać z pliku ~/.my.cnf lub ze zmiennej środowiskowej
RETENTION_DAYS=14

# Tworzenie katalogu backupu jeśli nie istnieje
mkdir -p "${BACKUP_DIR}"

BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${DATE_TAG}.sql.gz"

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Rozpoczynanie zrzutu bazy danych ${DB_NAME}..."

# Zrzut bazy z bezpiecznymi flagami transakcyjnymi i kompresją
mysqldump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  "${DB_NAME}" | gzip -9 > "${BACKUP_FILE}"

# Ustawienie restrykcyjnych uprawnień pliku kopii (tylko root / admin)
chmod 600 "${BACKUP_FILE}"

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Kopia zapasowa utworzona pomyślnie: ${BACKUP_FILE}"

# Rotacja kopii - usunięcie starszych niż RETENTION_DAYS
echo "[$(date +'%Y-%m-%d %H:%M:%S')] Czyszczenie kopii starszych niż ${RETENTION_DAYS} dni..."
find "${BACKUP_DIR}" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -delete

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Procedura backupu zakończona sukcesem."
