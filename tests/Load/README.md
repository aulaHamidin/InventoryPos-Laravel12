# F9A Load Harness

Harness ini hanya untuk database hardening terisolasi.

```bash
DB_DATABASE=inventori_q_hardening php artisan migrate:fresh --force
DB_DATABASE=inventori_q_hardening php artisan hardening:seed --profile=smoke
PHP_CLI_SERVER_WORKERS=4 DB_DATABASE=inventori_q_hardening php -d expose_php=0 artisan serve --no-reload
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/work" -w /work \
  grafana/k6:2.1.0@sha256:65c920dc067d5e2e00befbf982af6ad6ad0117034e8b1c65817c7975c52d4669 run \
  -e F9A_PROFILE=smoke \
  -e BASE_URL=http://host.docker.internal:8000 \
  -e F9A_MANIFEST=/work/storage/framework/testing/f9a-hardening-manifest.json \
  tests/Load/f9a-hardening.js
```

`baseline` memakai 10 tenant × 2.000 item, 20 VU, 2 menit ramp-up, 10 menit steady-state, dan 1 menit drain. `smoke` memakai 2 tenant × 200 item, total 5 VU, dan 60 detik. Manifest berisi credential sintetis ephemeral, berizin `0600`, berada di storage ignored, dan dilarang menjadi artifact/evidence.

K6 image dipin pada `grafana/k6:2.1.0`. Concurrency race tetap dibuktikan PHPUnit multi-process; `409/422/429` yang sengaja dipicu tidak dicampur ke error-rate workload valid.

Jalankan `tests/Load/f9a-conflicts.js` setelah workload valid. Script satu iterasi tersebut membuktikan retry checkout/payment, lalu mencatat expected `409`, `422`, dan `429` sebagai counter terpisah sebelum rekonsiliasi.

Pada runner Linux native/CI, gunakan `--network host` dan `BASE_URL=http://127.0.0.1:8000`. Contoh di atas memakai `host.docker.internal` agar bekerja pada Docker Desktop/WSL.
