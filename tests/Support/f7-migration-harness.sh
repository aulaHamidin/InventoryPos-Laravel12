#!/usr/bin/env bash

set -euo pipefail

baseline_sha="e72ef07"
f7_migration="database/migrations/2026_08_16_000001_add_phase7_analytics_to_items.php"
db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-3306}"
db_admin_username="${DB_ADMIN_USERNAME:-root}"
db_admin_password="${DB_ADMIN_PASSWORD:-${DB_PASSWORD:-password}}"
suffix="${GITHUB_RUN_ID:-local}_$RANDOM"
suffix="${suffix//[^a-zA-Z0-9_]/_}"
upgrade_db="f7_upgrade_${suffix}"
rollback_db="f7_rollback_${suffix}"
baseline_root="$(mktemp -d)"
baseline_dir="$baseline_root/database/migrations"

export MYSQL_PWD="$db_admin_password"
mysql_admin=(mysql --protocol=TCP -h "$db_host" -P "$db_port" -u "$db_admin_username")

on_error() {
    echo "F7 migration harness failed at line ${BASH_LINENO[0]}." >&2
}

cleanup() {
    "${mysql_admin[@]}" -e "DROP DATABASE IF EXISTS \`$upgrade_db\`; DROP DATABASE IF EXISTS \`$rollback_db\`;" >/dev/null
    rm -rf -- "$baseline_root"
}
trap on_error ERR
trap cleanup EXIT

git cat-file -e "${baseline_sha}^{commit}"
git merge-base --is-ancestor "$baseline_sha" HEAD
test -f "$f7_migration"

git archive "$baseline_sha" database/migrations | tar -x -C "$baseline_root"

"${mysql_admin[@]}" -e "CREATE DATABASE \`$upgrade_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE \`$rollback_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

seed_baseline() {
    local database="$1"
    "${mysql_admin[@]}" "$database" <<'SQL'
INSERT INTO tenants (id, nama_toko, slug, operational_status, allow_negative_stock, dead_stock_days, created_at, updated_at)
VALUES (1, 'F7 Harness', 'f7-harness', 'active', 0, 90, UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO users (id, tenant_id, name, email, no_hp, password, role, created_at, updated_at)
VALUES (1, 1, 'Owner', 'owner@f7.test', '081234567890', 'hash', 'owner', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO categories (id, tenant_id, kode, nama, created_at, updated_at)
VALUES (1, 1, 'F7', 'F7 Category', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO items (
    id, tenant_id, category_id, kode, nama, satuan, harga_beli, average_cost, harga_jual,
    stok_saat_ini, stok_minimal, threshold_mode, lead_time_days, safety_stock_days,
    movement_class, is_active, created_at, updated_at
) VALUES (
    1, 1, 1, 'F7-0001', 'Baseline Item', 'Pcs', 10.00, 12.50, 20.00,
    41, 13, 'auto_velocity', 2, 3, 'normal', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
);
INSERT INTO item_stock_movements (
    id, tenant_id, item_id, user_id, movement_type, qty, direction, harga_satuan, note, created_at
) VALUES (1, 1, 1, 1, 'sale', 5, 'out', 12.50, 'immutable baseline', UTC_TIMESTAMP());
SQL
}

for database in "$upgrade_db" "$rollback_db"; do
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:fresh --path="$baseline_dir" --realpath --force
    seed_baseline "$database"
    DB_DATABASE="$database" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate --path="$(pwd)/$f7_migration" --realpath --force
done

upgrade_values="$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(movement_class, '|', threshold_mode, '|', stok_minimal, '|', stok_saat_ini, '|', average_cost) FROM items WHERE id = 1")"
test "$upgrade_values" = "unclassified|manual|13|41|12.50"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT CONCAT(qty, '|', movement_type, '|', harga_satuan) FROM item_stock_movements WHERE id = 1")" = "5|sale|12.50"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$upgrade_db' AND table_name = 'items' AND column_name = 'analytics_calculated_at'")" = "1"
test "$("${mysql_admin[@]}" -N -B "$upgrade_db" -e "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = '$upgrade_db' AND index_name IN ('idx_items_tenant_active_movement_class', 'idx_movements_tenant_item_type_created')")" = "2"

DB_DATABASE="$rollback_db" DB_USERNAME="$db_admin_username" DB_PASSWORD="$db_admin_password" php artisan migrate:rollback --path="$(pwd)/$f7_migration" --realpath --step=1 --force
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT CONCAT(movement_class, '|', threshold_mode, '|', stok_minimal, '|', stok_saat_ini, '|', average_cost) FROM items WHERE id = 1")" = "normal|manual|13|41|12.50"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM item_stock_movements WHERE id = 1 AND qty = 5")" = "1"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$rollback_db' AND table_name = 'items' AND column_name = 'analytics_calculated_at'")" = "0"
test "$("${mysql_admin[@]}" -N -B "$rollback_db" -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = '$rollback_db' AND index_name IN ('idx_items_tenant_active_movement_class', 'idx_movements_tenant_item_type_created')")" = "0"

echo "F7 migration upgrade and lossy rollback harness passed."
