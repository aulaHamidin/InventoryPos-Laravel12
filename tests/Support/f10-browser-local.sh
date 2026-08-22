#!/usr/bin/env bash

set -euo pipefail

readonly browser_database='f10_local_hardening_browser'
readonly browser_port='8010'

cleanup() {
    if [[ -n "${server_pid:-}" ]]; then
        kill "$server_pid" 2>/dev/null || true
    fi
    if [[ -n "${worker_pid:-}" ]]; then
        kill "$worker_pid" 2>/dev/null || true
    fi

    docker compose exec -T mysql mysql -uroot -ppassword \
        -e "DROP DATABASE IF EXISTS \`f10_local_hardening_browser\`;" >/dev/null 2>&1 || true
}

trap cleanup EXIT

docker compose exec -T mysql mysql -uroot -ppassword \
    -e "DROP DATABASE IF EXISTS \`f10_local_hardening_browser\`; CREATE DATABASE \`f10_local_hardening_browser\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`f10_local_hardening_browser\`.* TO 'sail'@'%';"

export APP_ENV='testing'
export APP_URL="http://127.0.0.1:${browser_port}"
export DB_CONNECTION='mysql'
export DB_HOST='127.0.0.1'
export DB_PORT='3306'
export DB_DATABASE="$browser_database"
export DB_USERNAME='sail'
export DB_PASSWORD='password'
export BCRYPT_ROUNDS='4'
export CACHE_STORE='redis'
export CACHE_PREFIX='f10-local-browser-'
export REDIS_PREFIX='f10-local-browser-'
export QUEUE_CONNECTION='redis'
export SESSION_DRIVER='redis'
export REDIS_HOST='127.0.0.1'
export REDIS_PORT='6379'
export IDENTITY_HASH_KEY='f10-local-browser-ephemeral-key'
export HARDENING_RUNTIME_TESTS='true'
export F9A_BASE_URL="$APP_URL"
export F9A_MANIFEST='storage/framework/testing/f9a-hardening-manifest.json'

php artisan migrate:fresh --force
php artisan hardening:seed --profile=smoke

PHP_CLI_SERVER_WORKERS=4 php -d expose_php=0 artisan serve \
    --host=127.0.0.1 --port="$browser_port" --no-reload \
    > storage/logs/f10-browser-local-server.log 2>&1 &
server_pid=$!

php artisan queue:work redis --queue=exports,analytics,default \
    --sleep=0 --tries=3 --timeout=120 \
    > storage/logs/f10-browser-local-worker.log 2>&1 &
worker_pid=$!

for _attempt in $(seq 1 30); do
    if curl --fail --silent "$APP_URL/up" >/dev/null; then
        break
    fi
    sleep 1
done

curl --fail --silent "$APP_URL/up" >/dev/null
CI=1 npm run test:e2e:ci
