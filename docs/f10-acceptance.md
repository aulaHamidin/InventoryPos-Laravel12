# Fase 10 Acceptance — Billing MRR & Admin Pusat

Status: **IMPLEMENTASI LOKAL SELESAI — MENUNGGU REMOTE GATE**

Baseline resmi: `3c9cf4295b42abac8a3128098f645ad39176eff4`

SHA implementasi lokal: `46d5623032cf4b8c1f358b15b0055a0e02f9c720`

## Kontrak dan implementasi

- CD-10.1 dan implementation plan F10 berstatus **DISETUJUI UNTUK IMPLEMENTASI**.
- Schema billing, Legacy backfill, Admin security/MFA, subscription capability, platform lifecycle, manual billing, impersonation read-only, retention/purge, Owner API, serta Admin/App UI telah diimplementasikan.
- Implementasi memakai exact IDR decimal, `BillingClock` Asia/Jakarta, lock order Tenant → Subscription → Invoice → Payment, dan `IDENTITY_HASH_KEY` terpisah dari `APP_KEY`.
- Production deployment ditunda; Fase 9 tetap terbuka sampai F9B.

## Gate lokal

- [x] Migration fresh dan harness upgrade/rollback MySQL lulus; rollback dinyatakan billing/security-lossy.
- [x] Full regression F0–F9A: 145 test lulus, 1.045 assertion; 8 test runtime dipisahkan dari suite cepat.
- [x] `billing-runtime` lokal Redis/MySQL: 2 test, 35 assertion.
- [x] Billing/payment/scheduler concurrency multi-process lulus.
- [x] Capability/role, tenant isolation, Support no-leak, impersonation read-only, MFA replay/recovery, deletion/purge, HMAC tombstone, dan API zero-mutation lulus.
- [x] Query count MRR dan F9A tetap konstan.
- [x] npm test/build, Pint, Composer validate/platform requirements/audit, npm audit, secret scan, view cache, route list, serta schedule list lulus.
- [x] Evidence lokal tersanitasi tersedia di `docs/evidence/f10-billing-admin-2026-08-22/`.

## Gate remote

- [ ] Full workflow CI pada SHA final hijau.
- [ ] Job `billing-runtime`, `analytics-runtime`, `staff-runtime`, security, browser, dan hardening smoke hijau.
- [ ] Manual F9A Hardening Baseline pada SHA final F10 hijau.
- [ ] Tidak ada P0; setiap P1 decision-complete.
- [ ] Merge SHA F10 dicatat sebagai baseline F11.

## Rollback dan deployment

Rollback F10 menghapus data billing serta status revocation/MFA F10 dan tidak dapat mengembalikan state secara persis. Maintenance mode dan backup wajib sebelum rollback. Deployment, migration/backfill runtime, backup/restore, RPO/RTO, worker/scheduler health, serta alert delivery tetap **BELUM DIEKSEKUSI — WAJIB F9B**.
