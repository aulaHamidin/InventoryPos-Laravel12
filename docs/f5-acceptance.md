# Acceptance Gate Fase 5 — Cycle Counting

Tanggal eksekusi lokal: 15 Agustus 2026

Status: **LOCAL + VISUAL GATE LULUS; CI REMOTE MENUNGGU**

## Revision

- Baseline commit: `8347f92 Add-master-plan-for-phases-5-through-12`.
- Implementasi F5 masih berupa working tree lokal; commit SHA F5 dan CI run belum tersedia.

## Runtime

- Laravel 12 / PHP 8.3.33 / Node.js 20 / MySQL 8.4.
- Service Sail aplikasi, queue, MySQL, Redis, dan Mailpit aktif; MySQL dan Redis healthy.

## Migration

- `migrate:fresh --seed --force` pada database `testing`: lulus.
- Rollback dua migration F5 lalu upgrade kembali dari baseline F0–4: lulus.
- Check scope/rack, status/timestamp, count-field completeness, physical quantity, unique detail, dan FK diuji pada MySQL.

## Automated tests

- Full suite: **65 test / 314 assertion**, seluruhnya lulus.
- F5 targeted: **17 test / 91 assertion**, seluruhnya lulus.
- Multi-process: same rack, different rack, full-vs-partial, full-vs-full, count-vs-stock movement, dan duplicate finalize lulus.
- Tenant isolation, Ownership Guard, Staff denial, time-aware reconciliation, first-snapshot correction, negative-stock policy, API contract, dan Livewire contract lulus.

## Quality gate

- `composer validate --strict`: lulus.
- `composer check-platform-reqs`: lulus.
- `vendor/bin/pint --test`: 193 file lulus.
- `npm run build`: application dan Filament theme lulus.
- `php artisan view:cache`: lulus.
- `php artisan route:list`: enam route opname ditemukan.
- CI workflow telah ditambah `view:cache` dan `route:list`; run remote menunggu commit/push.

## Visual walkthrough

- Walkthrough Owner dijalankan pada desktop 1440 × 900 dan mobile 390 × 844.
- Desktop membuktikan create, count pertama dengan snapshot 5, review delta -2, confirmation, movement keluar 2 unit, serta completed read-only.
- Mobile membuktikan scan/search fallback, input fisik dominan, Save & Next, progress, zero-delta tanpa movement, dan completed summary responsif.
- Scope conflict same-rack menghasilkan notifikasi `Scope opname berkonflik dengan sesi aktif.`; incomplete dan duplicate-finalize dicakup automated contract tests.
- Screenshot index: [`evidence/f5-cycle-counting-2026-08-15/`](evidence/f5-cycle-counting-2026-08-15/).

## Known limitations

- Tidak ada cancel/delete draft sesuai kontrak F5; draft berkonflik harus diselesaikan.
- Staff tetap ditolak sampai aktivasi role pada Fase 8.
- Camera scanner bergantung pada `BarcodeDetector`; keyboard-wedge dan search menjadi fallback.

## Final decision

- Automated/local quality gate: **PASS**.
- Visual desktop/mobile: **PASS**.
- CI remote: **PENDING**.
- Fase 5 belum boleh ditandai selesai dan Fase 6 belum boleh dimulai.
