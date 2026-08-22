# Implementation Plan Fase 10 — Billing MRR & Admin Pusat

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Baseline resmi: `3c9cf4295b42abac8a3128098f645ad39176eff4`  
Document Delta: [`document-delta-f10-billing-admin.md`](document-delta-f10-billing-admin.md) (**CD-10.1**)

## 1. Tujuan dan Batas

F10 membangun billing admin-managed, MRR, platform administration, mandatory Admin TOTP, read-only support impersonation, serta whole-tenant deletion. F10 selesai melalui local/CI dan tidak melakukan deployment production.

Di luar F10: public onboarding, Owner TOTP, OTP provider, automatic invoice, Midtrans, webhook, dan billing refund.

## 2. Schema dan Domain

- Tambahkan backed enum dan model untuk interval/status billing, deletion, dan impersonation.
- Migration membuat `plans`, `subscriptions`, `invoices`, `billing_payments`, immutable `subscription_events`, `trial_claims`, `tenant_deletion_requests`, `impersonation_sessions`, dan kolom lifecycle/MFA Admin.
- Gunakan generated unique slot untuk satu current subscription per tenant dan satu open invoice per subscription.
- Backfill seluruh tenant lama ke internal Legacy subscription non-aging tanpa mengubah data operasional.
- Gunakan `BillingClock` Jakarta; query adapter menyimpan/membandingkan UTC.
- Plan referenced immutable; clone menjadi versi baru. IDR amount memakai exact decimal arithmetic.
- Capability evaluator menjadi upper bound setelah Policy role dan diterapkan di HTTP, Filament, Livewire, serta Action.

## 3. Actions dan Runtime

- Platform Actions: Support lifecycle/MFA, tenant+Owner provisioning/reset, ban/unban.
- Billing Actions: plan create/clone/deactivate, subscription create/transition/sweep, invoice generate/void, manual payment record/verify/reject.
- Billing locks: Tenant → Subscription → Invoice → Payment. Verification repeated adalah idempotent dan tidak menghasilkan duplicate event/audit.
- TOTP memakai Google2FA core dan SVG QR, recovery hashes, last-step replay guard, Admin auth version middleware, serta Redis-backed rate limit/session tests.
- Impersonation menyimpan Admin guard asli, target Owner web context, fingerprint, expiry, banner, dan read-only guard.
- Deletion Actions menjalankan request/cancel/review/queue/purge dengan retention 30 hari, token/session revocation, state restore, dan global purge tombstone.
- Schedule: billing sweep, impersonation cleanup, deletion queue, dan purge memakai timezone Jakarta, `withoutOverlapping`, serta `onOneServer` untuk task cluster-wide.

## 4. API dan UI

- Implementasikan Owner API read-only subscription/invoice dan deletion request/cancel sesuai CD-10.1; strict request, rate limiter F9A, canonical errors, offset `+07:00`.
- Tidak ada Admin JSON API atau pay/webhook endpoint.
- Panel `/admin`: MFA, MRR/past-due dashboard, Tenant, Plan, Subscription, Invoice/Payment, Support, Audit, Impersonation, dan Deletion Review.
- Support memakai projection minimum dan tidak pernah menerima amount/reference/credential.
- Panel `/app`: Billing page Owner, invoice history, capability banner, serta deletion workflow. Staff tidak melihat Billing.
- Direct URL, forged Livewire, dan direct Action invocation tetap ditolak backend.

## 5. Test dan CI

- Migration harness baseline `3c9cf429…`, data preservation, Legacy backfill, isolated rollback.
- Unit: state machine, grace, period/month/leap, MRR, HMAC trial, TOTP/recovery, capability-role intersection.
- Feature/concurrency: provisioning, trial lifetime race, plan immutability, invoice/payment races, scheduler versus payment, ban independence, Support projection, MFA/session revocation, impersonation, deletion lifecycle/purge, API strict/error/no-mutation.
- Purge test hanya pada database terisolasi dan membuktikan tenant lain utuh, trial claim/tombstone bertahan.
- Tambahkan `billing-runtime` MySQL+Redis dan pertahankan seluruh CI F0–F9A. Jalankan manual F9A Hardening Baseline pada SHA final.
- Playwright desktop/mobile/tablet Chromium+Firefox untuk semua state Admin/Support/Owner F10.

## 6. Acceptance dan Exit

- Evidence: `docs/evidence/f10-billing-admin-YYYY-MM-DD/`.
- Acceptance: `docs/f10-acceptance.md` berisi baseline, implementation/merge SHA, migration, tests, CI, evidence, P0/P1, rollback warning, dan deployment deferred.
- F10 selesai bila CD, schema, state/event/audit, identity boundary, no-leak, impersonation, deletion, visual, full local/remote CI, dan manual hardening baseline hijau.
- Setelah merge, merge SHA F10 menjadi baseline F11; langkah berikutnya CD-11.1, Owner 2FA, OTP provider, dan Midtrans sandbox.

## 7. Cross-Reference Checklist

- DDD: tables, enum, FK, generated unique slot, indexes, backfill, purge cascade, lossy rollback.
- SAD: state transition, lock order, Action boundaries, scheduler, expiry/recovery.
- API: exact endpoints, Owner-only, strict body, canonical error, rate limit, no pay/webhook.
- UI: Admin/Support/Owner projection, disabled/hidden state, support banner, mobile/desktop evidence.
- Security: TOTP/recovery/replay, session revocation, tenant isolation, no impersonation mutation, sensitive redaction.

