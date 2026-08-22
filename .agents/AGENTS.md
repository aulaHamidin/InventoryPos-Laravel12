# AGENTS.md
# Panduan Operasional Coding Agent
# Smart POS & Manajemen Stok — Contract Enforcement

## 1. Status Dokumen

Dokumen ini adalah **enforcement contract**.

Detail bisnis, schema, architecture, API, UI, dan roadmap berada pada dokumen source of truth yang disebut di bawah.

Jika terdapat konflik, agent harus:

1. berhenti;
2. menunjukkan konflik;
3. tidak memilih berdasarkan kebiasaan umum;
4. meminta Document Delta Declaration.

---

## 2. Source of Truth

1. `prd-saas-manajemen-stok.md` — product requirements.
2. `blueprint-saas-stok.md` — business/domain blueprint.
3. `database-design-document.md` — schema/integrity.
4. `software-architecture-document.md` — execution architecture.
5. `api-specification.md` — API contract.
6. `ui-ux-specification.md` — interface contract.
7. `development-roadmap.md` — implementation sequence.
8. `AGENTS.md` — enforcement rules.

---

## 3. Non-Negotiable Architecture

### Multi-tenant

- Shared schema.
- `tenant_id` tidak pernah berasal dari client.
- Tenant context berasal dari authenticated identity.
- Ownership Guard wajib untuk external tenant-scoped IDs.
- Tenant Isolation Test wajib untuk fitur tenant-scoped.

### Code

- Laravel monolith.
- Action Pattern.
- Tidak ada Repository Pattern.
- UI → Action → Model → DB.
- Listener tidak membaca HTTP Request.
- Service tidak memanggil UI.
- Action spesifik per business operation.

### Stock

- Semua stock mutation dalam satu DB transaction.
- `lockForUpdate()` wajib.
- Multi-item lock order berdasarkan `item_id ASC`.
- Stock movement immutable.
- Event/Listener tidak boleh mengubah stock sebagai source of truth.
- Tidak boleh update `stok_saat_ini` langsung dari UI/controller.

### POS

- Server menghitung total.
- Checkout idempotent.
- Cash finalize dengan stock revalidation.
- QRIS POS bersifat statis/manual milik toko; transfer juga dikonfirmasi manual.
- Konfirmasi non-tunai wajib idempotent dan actor berasal dari authenticated identity.
- Tidak ada stock reservation v1.
- Payment manual yang telah diterima selalu revalidate stock.
- Stock gagal setelah payment → `refund_required`.
- Refund seluruh metode POS bersifat manual dan cumulative.
- Pending checkout lebih dari 24 jam → `expired` dan tidak dapat dibayar.
- Midtrans hanya digunakan untuk billing Fase 11, bukan POS.

### Payment

`pos_transactions` dan `pos_payments` adalah dua lifecycle berbeda.

Transaction:

- pending_payment;
- completed;
- failed;
- expired;
- refund_required;
- voided;
- partially_returned;
- fully_returned.

Payment:

- pending;
- paid;
- failed;
- refund_required;
- partially_refunded;
- refunded.

### Cycle Counting

- Partial per rack default.
- Full tersedia.
- Snapshot per item saat item dihitung.
- `qty_sistem_at_count` wajib.
- Time-aware reconciliation.
- Partial berbeda rack boleh paralel.
- Full dan partial tidak boleh overlap.
- Finalize tidak boleh parsial.

### Supplier

- Item dapat memiliki banyak supplier.
- Maksimum satu preferred supplier.
- Preferred selection harus transaction + lock.
- Shopping list tidak boleh menebak supplier dari movement terakhir.

### Analytics & Smart Threshold

- Boundary bisnis memakai zona waktu `Asia/Jakarta` dan half-open window `[as_of - duration, as_of)`.
- Item belum berumur penuh 30×24 jam wajib `unclassified`; tidak boleh prorata atau fake class.
- Demand hanya `max(0, Σsale - Σsale_void - Σcustomer_return)`; stock/adjustment/opname/supplier movements dikecualikan.
- Ambang tetap: fast `>=1.00`, normal `>=0.25 dan <1.00`, slow `<0.25`; dead adalah override setelah eligibility.
- `tenant.dead_stock_days=0` menonaktifkan dead. Eligible tanpa movement menghasilkan threshold nol dan class slow kecuali memenuhi dead override.
- Preferred supplier dengan lead time non-null, termasuk nol, dipakai sebelum item lead time fallback.
- Event after-commit, relevant config/supplier changes, daily sweep, direct endpoint, dan preview wajib memakai calculator backend yang sama.
- Hanya `threshold_mode=auto_velocity` boleh memperbarui `stok_minimal`; mode manual tidak boleh ditimpa.
- Semua item aktif tetap memperoleh persisted analytics class/timestamp saat kalkulasi berhasil, termasuk mode manual.
- Recalculation analytics tidak boleh mengubah stock, average cost, atau immutable movement ledger.
- Fase 7 hanya Owner. Staff operational read-only analytics baru aktif setelah Fase 8 dan tidak boleh menerima financial fields.

### Staff & Multi-Kasir

- Staff lifecycle memakai `is_active` dan driver-independent `auth_version`; deactivate/reset mencabut token dan session lama.
- Owner membuat Staff dengan password minimal 12 karakter; tidak ada invitation email Fase 8.
- Staff hanya mengakses dashboard operasional, POS, item/stok/low-stock/supplier read-only, analytics non-finansial, dan transaksi `cashier_id` sendiri.
- Staff boleh memakai diskon POS existing tetapi tidak boleh stock mutation/opname/receiving, Shopping List, report/export, void/return/refund, billing, analytics mutation, atau staff management.
- Idempotency POS tetap unique per tenant; actor lain dengan key sama mendapat conflict tanpa menerima resource pertama.
- Purchase cost, average cost, supplier last-buy price, margin, valuation, profit, dan billing tidak boleh masuk projection HTML/JSON/Livewire Staff.

### Billing

- `subscriptions.status` adalah source of truth billing.
- `tenants.operational_status` hanya administrative.
- Semua subscription punya `ends_at`.
- Trial 14 hari.
- Satu owner/nomor HP hanya satu trial sepanjang masa.
- Trial reuse harus ditolak oleh Action berdasarkan histori subscription.
- MVP manual billing.
- v1 automated billing.

### Deletion

- Individual historical records tidak dapat dihapus.
- Tidak ada endpoint delete untuk transaction/payment/movement/audit/subscription event.
- Tenant deletion melalui request.
- Purge hanya whole-tenant.
- Purge menggunakan `DELETE FROM tenants WHERE id = ?`.
- Child FK menggunakan `ON DELETE CASCADE`.
- Purge dilakukan command/scheduler setelah safety checks.

### Security

- Owner + Staff.
- Staff tidak boleh melihat purchase cost, margin, inventory value, profit, billing.
- Super Admin support access wajib audit.
- Impersonation wajib reason + expiry + banner + audit.
- Super Admin tidak boleh direct-edit stock.

### Rate Limit & Transport Hardening (CD-9.2)

- API login dibatasi 5 request/menit per hash email ternormalisasi + IP.
- Authenticated read dibatasi 300 request/menit, mutation 120 request/menit, dan create export 10 request/menit per tenant + User.
- Logout tidak dibatasi; limiter tenant-scoped tidak pernah mengambil `tenant_id` dari request.
- `429 RATE_LIMITED` wajib canonical, menyertakan retry/rate headers, dan menghasilkan zero mutation/audit/event/job.
- Runtime multi-process wajib memakai Redis/distributed atomic cache untuk limiter.
- CORS browser deny-by-default; wildcard credential dilarang.
- Security headers berlaku pada web/API/private download; HSTS hanya production HTTPS.
- Auth/private response `no-store`; private file tidak boleh dilayani langsung.
- Audit/log metadata wajib melalui recursive sensitive-value redaction tanpa menggagalkan business transaction.

### Urutan Gate v1 (CD-9.1)

- Urutan normatif setelah F8 adalah `F9A → F10 → F11 → F12 code-complete → deployment pilot → F9B + F12 runtime acceptance → Public v1`.
- F9A lulus hanya memberi status `HARDENING PRE-DEPLOY SELESAI`; Fase 9 tetap terbuka.
- F10 tidak boleh dimulai sebelum F9A lulus, tidak ada P0, seluruh P1 memiliki mitigasi/keputusan eksplisit, dan CI utama hijau.
- F11 sandbox boleh memakai HTTPS local tunnel, tetapi credential/URL aktif tidak boleh masuk repository, log, atau evidence; fake-only tidak menutup gate provider.
- F12 code-complete tidak menutup Fase 12. Health deployment, worker/scheduler, alert delivery, backup/restore, dan runtime evidence wajib lulus bersama F9B.
- Production public dilarang sebelum status `F9B/RUNTIME ACCEPTANCE SELESAI` tercapai.

---

## 4. Permanent Rejections

Jangan mengusulkan tanpa perubahan kontrak:

- Repository Pattern.
- Lifetime license.
- Forecasting/regression/seasonality.
- WMA.
- Offline-first penuh.
- Microservices.
- CQRS.
- Event Sourcing penuh.
- Kubernetes/Kafka untuk kebutuhan ini.
- Automated refund POS pada v1.
- Stock reservation v1.
- Composite FK sebagai mekanisme utama tenant isolation.
- Direct stock editing.
- Deleting historical records individually.
- Custom roles/permission pada v1.

---

## 5. Proposal Workflow

Sebelum coding:

1. Baca dokumen terkait.
2. Buat `implementation_plan.md`.
3. Sertakan Cross-Reference Checklist.
4. Identifikasi schema/API/business rule/UI changes.
5. Dapatkan approval.
6. Baru implementasi.

Jika selama coding ditemukan kebutuhan baru:

- stop;
- buat Document Delta Declaration;
- update source of truth;
- update plan;
- lanjut setelah approval.

---

## 6. Cross-Reference Checklist

Setiap proposal wajib memverifikasi:

### DDD

- table yang disentuh;
- enum;
- FK;
- index;
- invariant;
- delete behavior.

### SAD

- Action;
- state transition;
- transaction boundary;
- lock order;
- side effect;
- failure recovery.

### API

- method;
- path;
- request;
- response;
- error code;
- idempotency;
- version impact.

### UI/UX

- role visibility;
- loading;
- empty;
- error;
- success;
- destructive confirmation;
- mobile behavior.

### PRD/Blueprint

- scope;
- priority;
- business rule;
- non-goal.

### Roadmap

- fase;
- dependency;
- acceptance criteria;
- test requirement.

### Ambiguous Term

Setiap istilah seperti:

- active;
- completed;
- available;
- paid;
- refunded;
- eligible;
- unclassified;
- trial;

harus memiliki definisi eksplisit.

---

## 7. Mandatory Testing Matrix

| Area | Test |
|---|---|
| Tenant model | Tenant Isolation |
| Foreign tenant ID | Ownership Guard |
| Stock | Race Condition |
| Multi-item stock | Lock Ordering |
| POS | Idempotency |
| POS manual payment | Duplicate/unique-key race |
| POS manual payment | Cash-vs-non-cash concurrency |
| POS pending | Expiry race |
| POS report | Method/permission isolation |
| Return | Partial Return |
| Void | Stock reversal |
| Refund | Amount invariant |
| Opname | Concurrency |
| Opname | Time-aware reconciliation |
| Supplier | Preferred concurrency |
| Analytics | Window start-inclusive/end-exclusive `Asia/Jakarta` |
| Analytics | Full-history eligibility dan `unclassified` |
| Analytics | Net POS demand, numeric boundary, dead override |
| Smart Threshold | Preferred lead-time zero/fallback dan ceil |
| Smart Threshold | Manual preservation dan 422 zero mutation |
| Analytics recalculation | After-commit/event/config/daily/direct parity |
| Analytics tenant | Isolation, ownership, no-N+1 |
| Analytics permission | Owner-only F7; Staff operational/no-financial setelah F8 |
| Trial | Trial reuse rejection |
| Billing | State transition |
| Billing webhook | Duplicate/out-of-order |
| Deletion | Purge safety |
| Impersonation | Audit |
| API | End-to-end feature test |

---

## 8. Forbidden Implementation Shortcuts

Agent tidak boleh:

- mengambil `tenant_id` dari request;
- mempercayai total POS dari client;
- update stock tanpa movement;
- update stock di listener;
- menganggap screenshot pelanggan sebagai bukti pembayaran POS;
- membuat adapter, endpoint, atau state Midtrans POS;
- finalize payment tanpa state validation;
- menebak supplier;
- menghitung analytics dengan prorata umur item atau window selain 30×24 jam;
- memakai gross sale atau seluruh stock out sebagai POS demand;
- menghitung ulang formula Smart Threshold di frontend;
- menimpa `stok_minimal` mode manual dari job/event/sweep;
- membuat trial tanpa histori check;
- delete transaction individual;
- bypass Action untuk support/admin stock correction;
- menyembunyikan impersonation;
- menganggap billing webhook selalu ordered.

---

## 9. Document Delta Declaration

Jika dibutuhkan:

- kolom baru;
- enum baru;
- endpoint baru;
- status baru;
- business rule baru;
- permission baru;
- UI state baru;

proposal harus menyertakan:

```text
Affected documents
Reason
Current contract
Proposed contract
Migration impact
Backward compatibility
Test impact
```

Tidak boleh ada silent schema drift.
