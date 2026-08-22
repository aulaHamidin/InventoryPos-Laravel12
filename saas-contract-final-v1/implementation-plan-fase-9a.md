# Implementation Plan Fase 9A — Hardening Pre-Deploy

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Baseline resmi: `ac6b7bf7ab630ba061b69a37a816804152b7695b`

Referensi utama:

1. `document-delta-f9-hardening-pilot-split.md` (**CD-9.1**).
2. `document-delta-f9a-rate-limit-transport-hardening.md` (**CD-9.2**).
3. `api-specification.md` §1, §3, §14, dan §16.
4. `software-architecture-document.md` §11, §17–19, dan §20.
5. `development-roadmap.md` §11.
6. `master-plan-fase-5-12.md` §9.

## 1. Sasaran dan batas fase

F9A membuktikan hardening yang dapat dijalankan pada local Docker dan CI sebelum fitur F10–F12 dibangun. Hasil F9A adalah baseline load/concurrency, security review, query/queue profile, browser/device matrix, serta draft runbook yang reproducible.

Status exit exact adalah **HARDENING PRE-DEPLOY SELESAI**. Status tersebut menjadi entry gate F10, tetapi tidak menutup Fase 9.

Di luar F9A dan tetap menjadi F9B: deployment pilot, migration/backfill pada environment pilot, backup terjadwal, restore drill production-like, RPO/RTO aktual, health worker/scheduler deployment nyata, alert delivery nyata, serta pilot operasional.

## 2. Security hardening

1. Implementasikan named limiter `api-login`, `api-read`, `api-write`, dan `api-export` sesuai CD-9.2. Limiter tenant-scoped mengambil tenant/User dari authenticated identity dan menggunakan Redis pada runtime multi-process. Logout tetap tidak dibatasi.
2. Normalisasikan seluruh exception throttle API menjadi status `429`, envelope `RATE_LIMITED`, `Retry-After`, `X-RateLimit-Limit`, dan `X-RateLimit-Remaining`. Throttled request tidak boleh mencapai Action atau membuat side effect.
3. Tambahkan CORS allowlist deny-by-default, security-header middleware, HSTS production/HTTPS-only, private `Cache-Control`, dan matikan direct serving disk private serta PHP version disclosure pada hardening runtime.
4. Tambahkan shared recursive sensitive-data redactor. `RecordAuditAction` dan structured log context wajib menggunakannya; key sensitif dipertahankan dengan nilai `[REDACTED]` tanpa menggagalkan transaksi.
5. Audit tenant isolation, ownership, Policy/Action parity, mass assignment, CSRF/session/Sanctum, IDOR, private download/traversal, forged Livewire request, error leakage, secret/log exposure, dan dependency advisory.

Tidak ada migration, schema, enum, permission, endpoint, atau business state baru pada F9A. Bila profile membutuhkan index/schema, pekerjaan berhenti pada temuan dan memerlukan Document Delta baru.

## 3. Load, concurrency, query, dan queue

1. Buat fixture sintetis hardening yang hanya berjalan pada environment local/testing dan database hardening terpisah. Full fixture: 10 tenant × 2.000 item aktif × 2 kasir; subset mempunyai stok tinggi, stock-race target, serta analytics history. Credential bersifat generated/ephemeral dan tidak dicetak.
2. Full baseline memakai 20 VU: 2 menit ramp-up, 10 menit steady, 1 menit drain; 12 VU catalog, 6 VU checkout/payment, dan 2 VU login/session/dashboard. Pembayaran: cash 60%, QRIS 20%, transfer 20%.
3. Gate: dashboard dan login p95 ≤2.000 ms; item search/scan/status p95 ≤750 ms; checkout/payment p95 ≤1.500 ms; valid error <1%; unexpected 5xx nol. Expected conflict `409/422/429` dilaporkan terpisah.
4. Rekonsiliasi pasca-load memverifikasi stok/ledger, payment/movement uniqueness, actor/cashier, idempotency, dan tenant isolation. Perluas concurrency test stock terakhir, same-key retry/conflict, dua kasir, cash/manual, void/return/expiry, Redis session revocation, dan analytics job lifecycle.
5. Query profiler membandingkan 200 dengan 2.000 item untuk dashboard, list/search/scan, checkout, payment, serta analytics. Query count harus konstan; normalized baseline disimpan versioned dan waktu query dilaporkan.
6. Queue profile mengalirkan 500 analytics job dan 5 export sintetis melalui tiga worker `exports,analytics,default`; queue harus habis ≤5 menit, failed job nol, dan output tenant-isolated.

## 4. Browser/device matrix

Playwright menjalankan Chromium dan Firefox pada desktop `1440×900`, mobile `390×844`, dan tablet `768×1024`. Skenario mencakup Owner/Staff login, role navigation/widget, item search/exact scan, keyboard-wedge scanner, checkout diskon, cash/QRIS/transfer, histori Staff sendiri, direct URL denial, financial-field exclusion, responsive layout, dan print fallback.

Dashboard mobile diukur pada Chromium dengan profil jaringan 4G lokal yang dicatat. Firefox menjadi functional parity gate. Safari/iOS manual bila perangkat tersedia dan bukan blocker F9A. Evidence hanya memakai data sintetis dan tidak memuat password, token, kontak nyata, atau QRIS produksi.

## 5. CI, evidence, dan runbook

1. Pertahankan job `test`, `analytics-runtime`, dan `staff-runtime` tanpa pelemahan.
2. Tambahkan `security`, `browser-runtime`, dan `hardening-smoke`. Smoke memakai 2 tenant × 200 item, 5 VU, 60 detik pada PR/push.
3. Tambahkan workflow `hardening-baseline` manual untuk profil penuh 10 tenant/20 VU. Remote run hijau wajib sebelum F9A ditutup.
4. CI memakai MySQL, real Redis session/cache/queue, worker nyata, prefix/database unik, generated credentials, lockfile/tool pin, dan sanitized artifact. GitHub Actions yang memakai runtime deprecated diperbarui ke supported major.
5. Buat draft runbook deployment, rollback, incident, backup, restore, dan support. Backup/restore diberi status **BELUM DIEKSEKUSI — WAJIB F9B**.
6. Simpan hasil pada `docs/evidence/f9a-hardening-YYYY-MM-DD/` dan acceptance pada `docs/f9a-acceptance.md`, termasuk baseline, implementation/merge SHA, CI run, environment fingerprint, severity register, dan known limitations.

## 6. Test dan acceptance gate

- Rate limiter: setiap bucket, key isolation, login non-enumeration, Redis multi-process, header/envelope, logout exemption, dan zero side effect.
- Transport: security headers web/API/download, production HTTPS-only HSTS, CORS allow/deny, private no-store/direct denial, CSRF, error sanitization, dan no PHP disclosure pada hardening runner.
- Redaction: nested audit/log data, mixed-case key, array/object metadata, exception context, dan business transaction tetap berhasil.
- Security: tenant/cashier isolation, ownership guard, Policy/Action, mass assignment, forged Livewire, Staff financial projection, report/export/download/print denial, dan secret/dependency scan.
- Load/concurrency: threshold latency/error, stock/ledger reconciliation, POS idempotency, stock race, Redis session revocation, analytics job, dan queue drain.
- Browser: seluruh role, workflow, viewport, scanner, payment, unauthorized state, financial exclusion, dan print fallback.
- Regression: migration fresh serta upgrade/rollback harness F7/F8, seluruh unit/feature, `analytics-runtime`, `staff-runtime`, npm test/build, Pint, Composer validation/platform/audit, view cache, route list, dan schedule list.

Severity:

- P0: tenant/authorization bypass, secret disclosure, stock/ledger/payment corruption, unauthenticated sensitive access, data loss, atau reproducible unexpected 5xx pada baseline; tidak boleh terbuka.
- P1: rate-limit weakness, persistent workflow blocker, latency gate gagal, atau security misconfiguration; hanya dapat diterima dengan owner, mitigasi, target, dan retest date.
- P2/P3: defense-in-depth, usability, performance non-blocking, atau dokumentasi; tetap dicatat.

F9A ditutup setelah seluruh evidence dan remote CI lengkap, tidak ada P0, serta seluruh P1 decision-complete. Catat merge SHA penutupan sebagai baseline F10, centang hanya F9A, pertahankan Fase 9 terbuka, dan ubah langkah berikutnya menjadi CD-10.1 serta implementation plan F10.

## 7. Cross-Reference Checklist

| Source | Dampak F9A |
|---|---|
| DDD | Tidak ada table/enum/FK/index/delete change. Temuan kebutuhan index memerlukan Delta baru. |
| SAD | Middleware/limiter/redactor, Redis atomicity, zero-side-effect throttle, dan failure evidence. |
| API | Tidak ada endpoint/request baru; additive `429 RATE_LIMITED` dan retry headers sesuai CD-9.2. |
| UI/UX | Tidak ada capability baru; browser matrix menguji state/role/mobile/print existing. |
| PRD/Blueprint | Menguji target operasi ritel year-one tanpa mengubah scope/non-goal. |
| Roadmap | F9A menjadi entry gate F10; F9 dan runtime acceptance tetap terbuka sampai F9B. |
| Ambiguous terms | `operational-ready`, `valid error`, P0/P1, full baseline, dan hardening runtime didefinisikan di plan/evidence. |
