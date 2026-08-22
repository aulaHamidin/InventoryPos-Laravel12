# Fase 9A Acceptance — Hardening Pre-Deploy

Status: **IMPLEMENTASI LOKAL LULUS — MENUNGGU REMOTE CI, BELUM DITUTUP**

Baseline resmi: `ac6b7bf7ab630ba061b69a37a816804152b7695b`

## Kontrak dan implementasi

- CD-9.1 mengunci pemisahan F9A/F9B.
- CD-9.2 mengunci rate limit dan transport hardening tanpa endpoint/migration baru.
- Implementation plan F9A berstatus **DISETUJUI UNTUK IMPLEMENTASI**.
- Commit dokumentasi awal: `fdd89b4`.
- SHA implementasi, merge SHA/baseline F10, dan remote CI run diisi setelah penutupan.

## Gate sementara

- [x] CD-9.2 dan implementation plan disahkan serta source of truth sinkron.
- [x] Rate limiter Redis, transport/header/CORS/private cache, dan shared redactor diimplementasikan.
- [x] Fixture hardening terisolasi, load harness, rekonsiliasi, query profiler, dan queue profiler tersedia.
- [x] Playwright matrix serta job CI security/browser/smoke/manual baseline tersedia.
- [x] Draft deployment/rollback/incident/backup/restore/support tersedia; runtime F9B ditandai jelas.
- [x] Full regression F0–F8, concurrency, Redis runtime, security/dependency/secret gate lulus pada source snapshot lokal final.
- [x] Browser matrix Chromium/Firefox dan visual evidence lulus lokal: 29 passed, 43 skipped, 0 failed.
- [x] Full baseline lokal 10 tenant × 2.000 item × 20 VU dan queue profile 500/5 lulus.
- [ ] Full remote CI dan workflow manual baseline hijau.
- [x] Gate lokal tidak menemukan P0/P1; keputusan final tetap menunggu remote gate.
- [ ] Merge SHA dicatat sebagai baseline F10 dan status berubah exact menjadi **HARDENING PRE-DEPLOY SELESAI**.

## Batas klaim

Fase 9 tetap terbuka. Deployment pilot, migration/backfill runtime, backup terjadwal, restore drill, RPO/RTO aktual, worker/scheduler health nyata, alert delivery, dan pilot operasional tetap F9B. F10 belum boleh memakai dokumen ini sebagai exit gate hingga seluruh checklist penutupan di atas lulus.
