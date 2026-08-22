# Evidence Fase 10 — Billing MRR & Admin Pusat

Tanggal verifikasi lokal: 2026-08-22
Baseline resmi: `3c9cf4295b42abac8a3128098f645ad39176eff4`

## Evidence lokal

- Full PHP regression: **145 passed**, **1.045 assertions**, 8 Redis-only test di-skip oleh suite cepat sesuai desain CI.
- Billing Redis runtime: **2 passed**, **35 assertions**, memakai MySQL, Redis cache, dan Redis session nyata.
- F10 migration harness: upgrade baseline, Legacy backfill, data preservation, serta billing/security-lossy rollback lulus pada dua database MySQL sementara.
- Concurrency: billing sweep versus manual payment verification lulus melalui dua proses; hasil akhir subscription/invoice/payment konsisten dan audit verifikasi tunggal.
- Security: Composer advisory, npm high-severity audit, dan repository secret scan lulus tanpa temuan.
- UI semantic check: mandatory Admin MFA setup/recovery flow, dashboard MRR, resource Admin, Owner Billing, capability flags, Legacy non-aging, dan deletion surface terbaca pada aplikasi lokal.
- Screenshot `admin-dashboard-mrr-desktop-1440x900.png` memakai akun/data sintetis dan tidak memuat password, secret TOTP, recovery code, token, nomor/email nyata, atau payment reference.

## Matrix otomatis

`tests/Browser/f10-billing-admin.spec.js` dijalankan pada desktop 1440×900, mobile 390×844, dan tablet 768×1024 untuk Chromium serta Firefox melalui job `browser-runtime`. Skenario mencakup Admin MFA/resource access serta Owner billing/deletion. Hasil dan screenshot failure-only disimpan sebagai artifact CI tersanitasi.

## Evidence remote final

- SHA final branch: `16f4ff7b267cd34c9d3a23ce626a946c02db69a1`.
- [CI `32584128556`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32584128556): seluruh job hijau; browser matrix 41 applicable cases lulus dan 43 contractual skips.
- [F9A Hardening Baseline `32584135135`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32584135135): load, idempotency/conflict, reconciliation, dan queue profile hijau.
- Merge SHA F10 sekaligus baseline F11: `9ba663a4c1d71a73b4c2182d96cd6dc90eb84868`.

## Batas klaim

- Deployment production, migration runtime, backup/restore drill, health worker/scheduler nyata, dan alert delivery tetap ditunda ke F9B.
- `IDENTITY_HASH_KEY` lokal/CI bersifat sintetis. Ketersediaan, backup, dan stabilitas secret deployment wajib diverifikasi pada F9B.
- Production public tetap dilarang sebelum F9B dan runtime acceptance F12 selesai.
