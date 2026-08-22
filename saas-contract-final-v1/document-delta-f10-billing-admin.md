# Document Delta Fase 10 — Billing MRR & Admin Pusat

Status: **DISETUJUI**

ID: **CD-10.1**  
Baseline F10: `3c9cf4295b42abac8a3128098f645ad39176eff4`

## 1. Declaration

### Affected documents

- `prd-saas-manajemen-stok.md`
- `blueprint-saas-stok.md`
- `database-design-document.md`
- `software-architecture-document.md`
- `api-specification.md`
- `ui-ux-specification.md`
- `development-roadmap.md`
- `master-plan-fase-5-12.md`
- `implementation_plan.md`
- `.agents/AGENTS.md`

### Reason

Kontrak lama mewajibkan capability matrix subscription, Admin 2FA, manual billing, support access, impersonation, dan whole-tenant purge, tetapi belum mengunci capability per state, concurrency invariant, legacy backfill, lifecycle Admin, retensi deletion, MRR, atau public surface F10. Implementasi tanpa Delta akan memaksa pilihan bisnis dan security secara diam-diam.

### Current contract

- State subscription: `trial|active|past_due|suspended|expired`.
- Billing terpisah dari `tenants.operational_status` dan POS payment.
- Trial 14 hari, satu kali sepanjang masa.
- Admin-managed billing F10; Midtrans baru F11.
- Impersonation wajib reason, expiry, banner, dan audit.
- Tenant purge hanya sebagai satu operasi tenant setelah approval/retention.

### Proposed contract

Bagian 2–8 dokumen ini menjadi keputusan normatif F10.

## 2. Subscription Capability dan Aging

Capability subscription adalah upper bound tambahan di atas role/Policy dan tidak pernah memperluas hak Staff.

| Status | Read tenant | Operational write | Configuration write | Billing/deletion |
|---|---|---|---|---|
| `trial`, `active` | Ya | Ya sesuai role | Ya sesuai role | Owner |
| `past_due` | Ya | POS dan koreksi POS; Owner stock/opname/receiving/Shopping List | Tidak | Owner |
| `suspended`, `expired` | Read-only | Tidak | Tidak | Owner |
| Missing/corrupt | Owner hanya billing/support/deletion; Staff ditolak | Tidak | Tidak | Owner |

Operational write tidak mencakup master item/category/rack/supplier mutation, Staff lifecycle, analytics settings/apply, atau export. Role tetap membatasi Staff ke POS dan inventory read-only.

Operational ban selalu lebih kuat. System maintenance seperti analytics tetap berjalan.

Business time memakai `Asia/Jakarta`, storage UTC, dan clock eksplisit:

- `trial → expired` saat `as_of >= ends_at`;
- `active → past_due` saat `as_of >= ends_at`;
- `past_due → suspended` saat `as_of >= ends_at + 7 hari`;
- `expired` terminal; reactivation memerlukan subscription baru.

## 3. Plan, Billing, dan MRR

- IDR-only; amount adalah decimal string dua digit dan tidak memakai float.
- MRR hanya subscription `active`: monthly harga penuh, yearly harga dibagi 12. Trial, internal Legacy, past-due, suspended, dan expired dikecualikan. Past-due amount dilaporkan terpisah.
- Plan yang sudah direferensikan immutable untuk code, name, price, interval, dan trial policy. Perubahan dilakukan dengan clone; plan lama hanya dinonaktifkan.
- Tenant provisioning atomik wajib memilih trial 14 hari pada plan eligible atau paid-pending berupa subscription `suspended` dan invoice `open`.
- Maksimum satu subscription non-expired per tenant dan satu invoice open per subscription.
- Invoice number: `INV-{YYYYMM}-{ULID}` dalam waktu Jakarta.
- Manual billing adalah dua langkah. Record membuat payment `pending` dengan server invoice amount. Verify mengubah payment/invoice menjadi paid dan activate/extend subscription dalam satu transaction. Actor recorder boleh menjadi verifier. Reject mengubah payment menjadi `failed`.
- Trial/active/past-due memperpanjang dari `ends_at` lama. Reactivation suspended dimulai dari `paid_at`. Period monthly/yearly memakai calendar arithmetic no-overflow.
- Billing refund, automatic invoice, gateway, webhook, dan Midtrans tidak aktif pada F10.

## 4. Trial Lifetime dan Legacy Backfill

- Tambahkan global `trial_claims` berisi unique HMAC nomor HP ternormalisasi. Tidak ada raw phone atau FK tenant.
- HMAC memakai `IDENTITY_HASH_KEY`, bukan `APP_KEY`; key wajib stabil, secret, dan dibackup pada F9B.
- Trial creation mengunci tenant dan mengandalkan unique claim untuk concurrency. Claim tetap ada setelah tenant purge.
- Migration F10 membuat plan internal Legacy Rp0 dan subscription active non-aging untuk seluruh tenant F0–F9. Plan ini tersembunyi, tidak assignable, dan tidak masuk MRR.

## 5. Admin 2FA dan Support

- Super Admin dan Support wajib TOTP pada login pertama.
- Secret 32 karakter, 30-second step, tolerance ±1 step, serta last-used step untuk mencegah replay.
- Delapan recovery code ditampilkan sekali, disimpan sebagai hash, dan dikonsumsi atomik.
- Admin memiliki `is_active` dan `auth_version`. Deactivate, password reset, dan MFA reset mencabut session lama tanpa bergantung session driver.
- Admin login/challenge dibatasi 5 attempt/menit per hash email + IP dan memakai pesan generik.
- Super Admin hanya mengelola Support lewat UI. Super Admin baru tetap melalui command.
- Support projection hanya tenant/owner contact, plan/status/period, invoice status, dan audit redacted. MRR, amount, payment reference, credential metadata, serta mutation disembunyikan dan ditolak backend.

## 6. Impersonation

- Super Admin dan Support hanya dapat memilih Owner aktif pada tenant operational-active.
- Read-only, maksimum 30 menit, session-bound, reason wajib, banner persistent, explicit end, lazy expiry, scheduled cleanup, serta start/end/expired audit.
- Support mode memakai projection operasional tanpa purchase cost, margin, profit, MRR, amount, atau payment reference. Super Admin memperoleh Owner projection tetapi tetap read-only.
- Seluruh Action mutation menolak active impersonation termasuk forged Livewire request.

## 7. Tenant Deletion

- Owner dapat request dan cancel sebelum approval.
- Super Admin dapat approve, reject, atau cancel approval sebelum `queued`.
- Approval menyimpan operational status sebelumnya, memban tenant, menaikkan auth version seluruh User, dan menghapus Sanctum tokens.
- Retention 30 hari. Cancellation memulihkan operational status tetapi tidak session/token lama.
- Due approval menjadi `queued`; purge hanya untuk queued request dengan `purge_after <= as_of`.
- Purge menjalankan satu `DELETE tenants` dalam transaction dan mengandalkan FK cascade.
- Tenant audit ikut purge. Audit global `tenant.purged` dengan `tenant_id=null` mempertahankan request ID, timestamp, dan identity HMAC tanpa PII.

State deletion:

```text
requested → cancelled
requested → rejected
requested → approved → cancelled
requested → approved → queued → purged
```

## 8. API dan UI Surface

Owner-only F10:

- `GET /api/v1/billing/subscription`
- `GET /api/v1/billing/invoices`
- `GET /api/v1/tenant/deletion-request`
- `POST /api/v1/tenant/deletion-request`
- `POST /api/v1/tenant/deletion-request/cancel`

Staff mendapat `403`; `tenant_id` tidak diterima dari client. Reason deletion 10–1.000 karakter. Duplicate open request menghasilkan `409 DELETION_REQUEST_EXISTS`. Capability violation menghasilkan `403 SUBSCRIPTION_CAPABILITY_DENIED` dan zero mutation/audit/job. Timestamp response selalu `+07:00`.

Tidak ada Admin JSON API atau billing pay/webhook F10. Admin mutation hanya melalui Filament dan Actions.

## 9. Audit Names

- `platform.support_created|profile_updated|access_reset|mfa_reset|activated|deactivated`
- `platform.tenant_created|owner_access_reset|banned|unbanned`
- `billing.plan_created|cloned|deactivated`
- `billing.subscription_created|transitioned|extended`
- `billing.invoice_created|voided`
- `billing.payment_recorded|verified|rejected`
- `platform.impersonation_started|ended|expired`
- `tenant.deletion_requested|cancelled|approved|rejected|queued`
- `tenant.purged`

Password, TOTP, recovery code, raw HMAC input, dan full payment reference tidak pernah masuk audit/log.

## 10. Migration, Compatibility, dan Test Impact

- Tambah tables `plans`, `subscriptions`, `invoices`, `billing_payments`, `subscription_events`, `trial_claims`, `tenant_deletion_requests`, dan `impersonation_sessions`; perluas `admins`.
- Billing/deletion timestamps memakai UTC `DATETIME`; business boundary dibuat di Jakarta.
- Upgrade mempertahankan seluruh data F0–F9 dan backfill Legacy. Rollback menghapus state billing/security F10 sehingga bersifat lossy dan memerlukan backup.
- Wajib: fresh/upgrade/rollback MySQL, state/capability/MRR unit, billing concurrency, Redis Admin session/TOTP, impersonation, deletion purge isolated DB, tenant isolation, Support no-leak, owner API, scheduler lock, browser matrix, dependency/secret audit, serta regresi F0–F9A.

## 11. Preserved Scope

- Tidak ada production deployment F10.
- Owner 2FA, public onboarding, OTP provider, Midtrans sandbox/webhook, automatic invoices, dan billing refund tetap F11.
- F9B tetap memegang migration/runtime deployment, backup/restore, worker/scheduler health, dan public release gate.

