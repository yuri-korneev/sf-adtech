set -euo pipefail
ts=$(date +%Y%m%d_%H%M%S)
mkdir -p database/dumps
mysqldump -u root -psecret --single-transaction --routines --triggers --events sfadtech > "database/dumps/sf_adtech_full_${ts}.sql"
echo "Dump: database/dumps/sf_adtech_full_${ts}.sql"
