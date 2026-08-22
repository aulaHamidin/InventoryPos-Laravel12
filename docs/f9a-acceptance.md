# Fase 9A Acceptance — Hardening Pre-Deploy

Status: **HARDENING PRE-DEPLOY SELESAI**

Baseline resmi: `ac6b7bf7ab630ba061b69a37a816804152b7695b`

## Kontrak dan implementasi

- CD-9.1 mengunci pemisahan F9A/F9B.
- CD-9.2 mengunci rate limit dan transport hardening tanpa endpoint/migration baru.
- Implementation plan F9A berstatus **DISETUJUI UNTUK IMPLEMENTASI**.
- Commit dokumentasi awal: `fdd89b4`.
- SHA implementasi lokal: `bffa93db15f1dd838ee7ac693767a3d97d996bf6`.
- SHA remote gate: `13ec9fde4b4e992250bd550a02f4887e7604c5e3`.
- Merge SHA sekaligus baseline resmi F10: `3c9cf4295b42abac8a3128098f645ad39176eff4`.
- CI utama: run [`32571655689`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32571655689), sukses.
- Workflow manual F9A Hardening Baseline: run [`32571664915`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32571664915), sukses.

## Gate final

- [x] CD-9.2 dan implementation plan disahkan serta source of truth sinkron.
- [x] Rate limiter Redis, transport/header/CORS/private cache, dan shared redactor diimplementasikan.
- [x] Fixture hardening terisolasi, load harness, rekonsiliasi, query profiler, dan queue profiler tersedia.
- [x] Playwright matrix serta job CI security/browser/smoke/manual baseline tersedia.
- [x] Draft deployment/rollback/incident/backup/restore/support tersedia; runtime F9B ditandai jelas.
- [x] Full regression F0–F8, concurrency, Redis runtime, security/dependency/secret gate lulus pada source snapshot lokal final.
- [x] Browser matrix Chromium/Firefox dan visual evidence lulus lokal: 29 passed, 43 skipped, 0 failed.
- [x] Full baseline lokal 10 tenant × 2.000 item × 20 VU dan queue profile 500/5 lulus.
- [x] Full remote CI dan workflow manual baseline hijau pada SHA yang sama.
- [x] Gate lokal dan remote tidak menemukan P0/P1 terbuka.
- [x] Merge SHA dicatat sebagai baseline F10 dan status berubah exact menjadi **HARDENING PRE-DEPLOY SELESAI**.

## Batas klaim

Fase 9 tetap terbuka. Deployment pilot, migration/backfill runtime, backup terjadwal, restore drill, RPO/RTO aktual, worker/scheduler health nyata, alert delivery, dan pilot operasional tetap F9B. Checklist penutupan F9A telah lulus sehingga baseline di atas dapat dipakai untuk CD-10.1 dan implementation plan F10.
