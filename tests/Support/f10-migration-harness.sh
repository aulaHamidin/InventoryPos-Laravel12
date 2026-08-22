#!/usr/bin/env bash

set -euo pipefail

baseline_sha="3c9cf4295b42abac8a3128098f645ad39176eff4"
f10_billing="database/migrations/2026_08_22_000001_create_phase10_billing_tables.php"
f10_security="database/migrations/2026_08_22_000002_create_phase10_platform_security_tables.php"
db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-3306}"
db_admin_username="${DB_ADMIN_USERNAME:-root}"
db_admin_password="${DB_ADMIN_PASSWORD:-${DB_PASSWORD:-password}}"
suffix="${GITHUB_RUN_ID:-local}_$RANDOM"
suffix="${suffix//[^a-zA-Z0-9_]/_}"
upgrade_db="f10_upgrade_${suffix}"
rollback_db="f10_rollback_${suffix}"
baseline_root="$(mktemp -d)"
baseline_dir="$baseline_root/database/migrations"

export MYSQL_PWD="$db_admin_password"
mysql_admin=(mysql --protocol=TCP -h "$db_host" -P "$db_port" -u "$db_admin_username")

cleanup() {
    "${mysql_admin[@]}" -e "DROP DATABASE IF EXISTS \`$upgrade_db\`; DROP DATABASE IF EXISTS \`$rollback_db\`;" >/dev/null
    rm -rf -- "$baseline_root"
}
trap cleanup EXIT
trap 'echo "F10 migration harness failed at line ${BASH_LINENO[0]}." >&2' ERR

git cat-file -e "${baseline_sha}^{commit}"
git merge-base --is-ancestor "$baseline_sha" HEAD
git archive "$baseline_sha" database/migrations | tar -x -C "$baseline_root"

"${mysql_admin[@]}" -e "CREATE DATABASE \`$upgrade_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE \`$rollback_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

seed_baseline() {
    local database="$1"
    "${mysql_admin[@]}" "$database" <<'SQL'
INSERT INTO tenants (id, nama_toko, slug, operational_status, allow_negative_stock, dead_stock_days, created_at, updated_at)
VALUES (1, 'F10 Harness', 'f10-harness', 'active', 0, 90, '2026-08-01 00:00:00', '2026-08-01 00:00:00');
INSERT INTO users (id, tenant_id, name, email, no_hp, password, role, is_active, auth_version, created_at, updated_at)
VALUES (1, 1, 'Historic Owner', 'owner@f10.test', '081200000010', 'owner-hash', 'owner', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO admins (id, name, email, password, role, two_factor_secret, two_factor_confirmed_at, created_at, updated_at)
VALUES (1, 'Historic Admin', 'admin@f10.test', 'admin-hash', 'super_admin', 'historic-secret', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO categories (id, tenant_id, kode, nama, created_at, updated_at)
VALUES (1, 1, 'CAT-F10', 'Harness', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO items (id, tenant_id, category_id, kode, nama, satuan, harga_beli, average_cost, harga_jual, stok_saat_ini, stok_minimal, threshold_mode, lead_time_days, safety_stock_days, movement_class, is_active, created_at, updated_at)
VALUES (1, 1, 1, 'ITM-F10', 'Historic Item', 'Pcs', 50, 55, 100, 9, 3, 'manual', 0, 0, 'normal', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO item_stock_movements (tenant_id, item_id, user_id, movement_type, qty, direction, harga_satuan, note, created_at)
VALUES (1, 1, 1, 'stock_in', 9, 'in', 55, 'immutable baseline', UTC_TIMESTAMP());
SQL
}

for database in "$upgrade_db" "$rollback_db"; do
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:fresh --path="$baseline_dir" --realpath --force
    seed_baseline "$database"
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate --path="$(pwd)/$f10_billing" --realpath --force
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate --path="$(pwd)/$f10_security" --realpath --force
done

test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(code,'|',price,'|',is_internal,'|',is_active) FROM plans WHERE code='LEGACY-F0-F9'")" = "LEGACY-F0-F9|0.00|1|0"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(status,'|',DATE_FORMAT(ends_at,'%Y-%m-%d')) FROM subscriptions WHERE tenant_id=1")" = "active|9999-12-31"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(stok_saat_ini,'|',average_cost,'|',stok_minimal) FROM items WHERE id=1")" = "9|55.00|3"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(*) FROM item_stock_movements WHERE tenant_id=1 AND note='immutable baseline'")" = "1"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(is_active,'|',auth_version,'|',IF(two_factor_secret IS NULL,1,0),'|',IF(two_factor_confirmed_at IS NULL,1,0)) FROM admins WHERE id=1")" = "1|1|1|1"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema='$upgrade_db' AND index_name IN ('subscriptions_one_current_unique','invoices_one_open_unique','admins_role_active_index')")" = "3"

DB_DATABASE="$rollback_db" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:rollback --path="$(pwd)/$f10_security" --realpath --step=1 --force
DB_DATABASE="$rollback_db" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:rollback --path="$(pwd)/$f10_billing" --realpath --step=1 --force
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM tenants WHERE id=1")" = "1"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM item_stock_movements WHERE tenant_id=1")" = "1"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$rollback_db' AND table_name IN ('plans','subscriptions','invoices','billing_payments','subscription_events','trial_claims','tenant_deletion_requests','impersonation_sessions')")" = "0"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$rollback_db' AND table_name='admins' AND column_name IN ('is_active','auth_version','two_factor_recovery_code_hashes','two_factor_last_used_step')")" = "0"

echo "F10 migration upgrade and billing/security-lossy rollback harness passed; exact F10 state requires backup."
