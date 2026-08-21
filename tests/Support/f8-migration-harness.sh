#!/usr/bin/env bash

set -euo pipefail

baseline_sha="ad07521fbdf81ccf5a3fe9185fecac5eb96fa01e"
f8_migration="database/migrations/2026_08_21_000001_add_phase8_staff_access_to_users.php"
db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-3306}"
db_admin_username="${DB_ADMIN_USERNAME:-root}"
db_admin_password="${DB_ADMIN_PASSWORD:-${DB_PASSWORD:-password}}"
suffix="${GITHUB_RUN_ID:-local}_$RANDOM"
suffix="${suffix//[^a-zA-Z0-9_]/_}"
upgrade_db="f8_upgrade_${suffix}"
rollback_db="f8_rollback_${suffix}"
baseline_root="$(mktemp -d)"
baseline_dir="$baseline_root/database/migrations"

export MYSQL_PWD="$db_admin_password"
mysql_admin=(mysql --protocol=TCP -h "$db_host" -P "$db_port" -u "$db_admin_username")

on_error() {
    echo "F8 migration harness failed at line ${BASH_LINENO[0]}." >&2
}

cleanup() {
    "${mysql_admin[@]}" -e "DROP DATABASE IF EXISTS \`$upgrade_db\`; DROP DATABASE IF EXISTS \`$rollback_db\`;" >/dev/null
    rm -rf -- "$baseline_root"
}
trap on_error ERR
trap cleanup EXIT

git cat-file -e "${baseline_sha}^{commit}"
git merge-base --is-ancestor "$baseline_sha" HEAD
test -f "$f8_migration"

git archive "$baseline_sha" database/migrations | tar -x -C "$baseline_root"

"${mysql_admin[@]}" -e "CREATE DATABASE \`$upgrade_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE \`$rollback_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

seed_baseline() {
    local database="$1"
    "${mysql_admin[@]}" "$database" <<'SQL'
INSERT INTO tenants (id, nama_toko, slug, operational_status, allow_negative_stock, dead_stock_days, created_at, updated_at)
VALUES (1, 'F8 Harness', 'f8-harness', 'active', 0, 90, UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO users (id, tenant_id, name, email, no_hp, password, role, created_at, updated_at)
VALUES
    (1, 1, 'Historic Owner', 'owner@f8.test', '081200000001', 'owner-hash', 'owner', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    (2, 1, 'Historic Staff', 'staff@f8.test', '081200000002', 'staff-hash', 'staff', UTC_TIMESTAMP(), UTC_TIMESTAMP());
SQL
}

for database in "$upgrade_db" "$rollback_db"; do
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:fresh --path="$baseline_dir" --realpath --force
    seed_baseline "$database"
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate --path="$(pwd)/$f8_migration" --realpath --force
done

test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(*) FROM users WHERE is_active = 1 AND auth_version = 1")" = "2"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT GROUP_CONCAT(CONCAT(role, '|', email, '|', no_hp) ORDER BY id SEPARATOR ';') FROM users")" = "owner|owner@f8.test|081200000001;staff|staff@f8.test|081200000002"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = '$upgrade_db' AND table_name = 'users' AND index_name = 'users_tenant_role_active_id_idx'")" = "1"

DB_DATABASE="$rollback_db" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:rollback --path="$(pwd)/$f8_migration" --realpath --step=1 --force
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM users")" = "2"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$rollback_db' AND table_name = 'users' AND column_name IN ('is_active', 'auth_version')")" = "0"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = '$rollback_db' AND table_name = 'users' AND index_name = 'users_tenant_role_active_id_idx'")" = "0"

echo "F8 migration upgrade and security-lossy rollback harness passed; exact inactive/revocation state requires backup."
