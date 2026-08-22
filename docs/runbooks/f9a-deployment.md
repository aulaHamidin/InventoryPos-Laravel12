# Draft Runbook Deployment v1 / Pilot

Status: **DRAFT F9A — EKSEKUSI DEPLOYMENT WAJIB F9B**

## Tujuan dan otorisasi

Runbook ini menjadi prosedur deployment pilot non-public setelah F0–F12 code-complete. Eksekusi memerlukan release owner, operator deployment, reviewer database, dan incident owner yang tercatat. Jangan gunakan data toko nyata tanpa persetujuan eksplisit.

## Preflight

1. Catat release SHA, migration list, backup identifier, operator, waktu mulai, dan rollback window.
2. Pastikan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS valid, `APP_URL` final, `expose_php=Off`, cookie secure/HTTP-only/SameSite, serta CORS berisi allowlist eksplisit tanpa wildcard credential.
3. Pastikan cache, session, queue, dan limiter memakai Redis/distributed lock; gunakan prefix environment unik.
4. Pastikan storage private tidak disajikan langsung oleh web server dan public storage hanya berisi aset yang memang publik.
5. Verifikasi secret provider, rotasi credential sementara, database least-privilege, dan log redaction.
6. Jalankan dari artifact release yang sama: Composer validation/platform/audit, npm audit/build, migration fresh/upgrade/rollback harness, PHPUnit, runtime Redis, browser matrix, route list, schedule list, dan view cache.
7. Pastikan worker memproses `exports,analytics,default`, scheduler hanya satu logical runner, dan endpoint health F12 siap sebelum traffic pilot.

## Eksekusi pilot

1. Aktifkan maintenance mode dan blok akses publik.
2. Ambil backup terverifikasi dan catat checksum/retention identifier.
3. Deploy artifact immutable, install dependency production, lalu jalankan migration satu kali.
4. Jalankan backfill F7/F8 dan `analytics:status --fail-on-incomplete`; tunggu queue analytics habis dan failed job nol.
5. Jalankan cache/view optimization, mulai worker/scheduler, lalu lakukan health, login, tenant-isolation, POS cash/manual, export private, rate-limit, session revocation, dan webhook sandbox smoke test.
6. Buka hanya untuk pilot sintetis. Pantau error, latency, queue, worker/scheduler heartbeat, database, storage, dan alert.
7. Bila seluruh gate stabil sampai akhir observation window, catat hasil F9B. Public v1 tetap dilarang sebelum F9B dan F12 runtime acceptance selesai.

## Bukti minimum

Release SHA, migration output tersanitasi, health/queue/scheduler snapshot, backfill status, smoke result, backup identifier, operator/reviewer, waktu, temuan, serta keputusan go/rollback. Password, token, cookie, OTP, signature, dan connection string tidak boleh masuk evidence.
