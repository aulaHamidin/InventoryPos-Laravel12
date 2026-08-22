# Software Architecture Document (SAD)
# Smart POS & Manajemen Stok — SaaS Ritel Komoditas Padat SKU

## 1. Architectural Style

- Laravel 12 monolith.
- Shared-schema multi-tenant.
- UI → Action → Model → DB.
- Event/Listener untuk side effect.
- Service hanya untuk integrasi/domain helper yang memang membutuhkan abstraction.
- Tidak ada Repository Pattern.
- Tidak ada direct UI → Model mutation untuk business transaction.

---

## 2. Folder Structure

```text
app/
├── Actions/
│   ├── Auth/
│   ├── Inventory/
│   ├── POS/
│   ├── Payment/
│   ├── Opname/
│   ├── Shopping/
│   ├── Analytics/
│   ├── Billing/
│   ├── Tenant/
│   └── Admin/
├── Events/
├── Listeners/
├── Jobs/
├── Models/
├── Policies/
├── Services/
│   ├── Payment/
│   └── Reporting/
├── Support/
│   ├── Tenant/
│   ├── Idempotency/
│   └── Locking/
└── Filament/
```

Action harus spesifik.

Contoh:

- `RecordStockInAction`
- `RecordStockOutAction`
- `RecordCustomerReturnAction`
- `FinalizePosTransactionAction`
- `ConfirmManualPaymentAction`
- `ExpirePendingPosTransactionAction`
- `VoidPosTransactionAction`
- `MarkPosPaymentRefundedAction`
- `SetPreferredSupplierAction`
- `FinalizeStockOpnameAction`
- `ApplySmartThresholdAction`

Hindari `HandleStockAction` atau `HandleTransactionAction` yang memuat banyak domain.

---

## 3. Dependency Rules

### Identity and Panel Boundary

- Tenant identity (`User`: Owner/Staff) memakai guard `web` dan surface `/app`.
- Platform identity (`Admin`: Super Admin/Support) memakai guard `admin` dan surface `/admin`.
- Hanya panel tenant yang memasang `SetTenantContext` dan menemukan resource operasional toko.
- Panel platform tidak menemukan resource tenant; support access dan impersonation baru ditambahkan pada Fase 10 dengan audit wajib.
- Super-admin awal diprovision melalui command interaktif tanpa kredensial default di source control.

### UI

Boleh:

- validasi format;
- authorization invocation;
- memanggil Action.

Tidak boleh:

- menghitung total bisnis sendiri;
- update stok;
- mengubah status payment secara langsung.

### Action

Boleh:

- transaction;
- lock;
- business validation;
- model mutation;
- event dispatch setelah successful commit.

Tidak boleh:

- membaca HTTP Request langsung;
- merender UI;
- mengirim response HTTP.

### Listener

Hanya side effect.

Contoh:

- invalidate cache;
- notification;
- analytics event.

Listener tidak boleh menjadi source of truth stock mutation.

### Payment Boundary

POS Fase 6 tidak memiliki payment provider. Cash dan manual non-tunai masuk ke `FinalizePosTransactionAction`. Midtrans hanya dibungkus oleh service billing saat Fase 11 aktif dan tidak mengetahui UI.

### Analytics Boundary

- Formula SMA, Net POS demand, classification, dead override, dan threshold berada pada pure calculator/value object tanpa query database.
- Action, job, dan scheduler menyiapkan snapshot input tenant-scoped lalu memakai calculator yang sama.
- Event/listener hanya menjadwalkan recalculation setelah commit; analytics tidak pernah memutasi stock atau movement.
- Dashboard membaca nilai persisted dan tidak menghitung ulang raw ledger pada setiap render.

---

## 4. Transaction and Locking Rules

Semua stock mutation:

```php
DB::transaction(function () {
    // ownership validation
    // lock items
    // validate stock
    // insert movement
    // update item stock
});
```

Untuk multi-item:

```text
sort item IDs ascending
→ lockForUpdate()
→ process
```

Semua code path yang memutasi stock harus menggunakan rule ini.

---

## 5. State Machines

### 5.1 POS Transaction

```text
pending_payment
 ├── cash paid → completed
 ├── manual qris/transfer confirmed + stock valid → completed
 ├── manual qris/transfer confirmed + stock invalid → refund_required
 ├── age >= pending TTL → expired
 └── unrecoverable business error → failed

completed
 ├── void → voided
 ├── partial return → partially_returned
 └── full return → fully_returned

partially_returned
 └── remaining quantity returned → fully_returned
```

Terminal transaction states:

- `failed`
- `expired`
- `voided`
- `fully_returned`

`refund_required` adalah exception state yang menunggu penyelesaian refund payment.

---

### 5.2 POS Payment

```text
pending
 ├── payment confirmed → paid
 └── payment failure → failed

paid
 ├── refund obligation → refund_required
 ├── partial refund → partially_refunded
 └── full refund → refunded

refund_required
 ├── owner marks full refund → refunded
 └── owner marks partial refund → partially_refunded
```

Payment status tidak mengontrol transaction status secara langsung tanpa validasi state machine.

---

### 5.3 Subscription

```text
trial → active
trial → expired

active → past_due
active → suspended
active → expired

past_due → active
past_due → suspended

suspended → active
suspended → expired
```

`tenants.operational_status` tidak mengikuti state machine subscription.

---

### 5.4 Shopping List

```text
draft → purchased → completed
draft → archived
purchased → archived
```

---

### 5.5 Stock Opname

```text
draft → completed
```

Tidak ada edit setelah completed.

---

## 6. POS Sequence

### 6.1 Checkout

```text
Client
 ↓
CheckoutPosAction
 ↓
validate tenant ownership
 ↓
load items
 ↓
sort IDs
 ↓
lock item rows
 ↓
validate active + stock
 ↓
server calculate price/discount/total
 ↓
create pos_transaction
 ↓
create pos_transaction_items
 ↓
commit
```

Tidak ada stock reduction pada tahap ini.

---

### 6.2 Cash

```text
checkout
 ↓
PayCashAction
 ↓
lock transaction
 ↓
lock item IDs ascending
 ↓
revalidate stock
 ↓
insert sale movements
 ↓
decrement stock
 ↓
create pos_payment(paid)
 ↓
transaction = completed
 ↓
commit
```

---

### 6.3 Manual QRIS/Transfer

```text
checkout
 ↓
pending_payment
 ↓
ConfirmManualPaymentAction
 ↓
operator checks merchant/bank application
 ↓
FinalizePosTransactionAction
 ↓
lock transaction
 ↓
resolve idempotency key
 ↓
lock item IDs ascending
 ↓
revalidate stock
 ├── enough → sale movements → payment paid → transaction completed
 └── insufficient → transaction refund_required
                  → payment refund_required
                  → notify owner
```

---

## 7. Manual Payment Idempotency

`ConfirmManualPaymentAction` harus bersifat idempotent.

Rules:

1. Transaction harus `pending_payment`.
2. `Idempotency-Key` unique per tenant.
3. Canonical payload adalah transaction, method, normalized reference, dan normalized note.
4. Key/payload sama mengembalikan hasil lama.
5. Key sama dengan transaction/payload berbeda menghasilkan `IDEMPOTENCY_CONFLICT`.
6. Unique-key race dibaca ulang setelah duplicate constraint dan tidak boleh menjadi HTTP 500.
7. Duplicate confirmation tidak boleh membuat payment atau movement kedua.

---

## 8. Pending Expiry

Pending checkout memakai TTL default 24 jam.

Scheduler dan payment confirmation mengunci transaction yang sama. Scheduler mengubah hanya transaction yang masih pending pada boundary expiry; payment terhadap transaction expired ditolak dan checkout baru wajib dibuat.

---

## 9. Return & Void Sequence

### Void

```text
completed
 ↓
lock transaction
 ↓
lock item IDs
 ↓
validate no previous return
 ↓
insert reversal movements
 ↓
transaction = voided
 ↓
if payment paid:
    cash/qris/transfer → payment refund_required
 ↓
commit
```

### Partial Return

```text
completed/partially_returned
 ↓
lock transaction
 ↓
validate returned_qty
 ↓
lock item IDs
 ↓
insert customer_return movements
 ↓
increment returned_qty
 ↓
calculate refund amount
 ↓
update transaction status
 ↓
payment paid → refund_required
 ↓
commit
```

Refund amount berasal dari cumulative exact net line amount, bukan harga jual kotor sebelum diskon. Payment status dapat `partially_refunded` dengan due nol bila refunded amount menutup obligation saat ini tetapi belum sebesar full payment amount.

---

## 10. Refund Manual

POS v1 tidak memanggil API refund provider.

Owner:

1. membuka transaksi;
2. melihat amount yang harus direfund;
3. mengembalikan cash atau melakukan refund melalui media toko;
4. memilih `Tandai Sudah Refund`;
5. memasukkan actual refunded amount jika diperlukan;
6. sistem mencatat actor dan timestamp.

Validation:

`current_refunded_amount <= refunded_amount <= refund_obligation_amount <= amount`.

Status:

- full → `refunded`;
- partial → `partially_refunded`.

---

## 11. Ownership Guard

Semua Action yang menerima ID eksternal wajib menggunakan guard.

Contoh:

```php
$category = OwnershipGuard::forTenant($tenant, Category::class, $categoryId);
```

Guard tidak menerima tenant ID dari request.

Tenant context berasal dari authenticated session/token.

---

## 12. Preferred Supplier Concurrency

`SetPreferredSupplierAction`:

```text
BEGIN
lock item
load all item_suppliers
set preferred=false
set target=true
COMMIT
```

Concurrent requests harus menghasilkan tepat satu preferred supplier.

---

## 13. Cycle Counting Sequence

```text
CreateOpnameAction
 ↓
validate scope conflict
 ↓
create details
 ↓
user counts item
 ↓
lock item
 ↓
save qty_sistem_at_count = current stock
 ↓
save qty_fisik
 ↓
later finalize
 ↓
validate every detail counted
 ↓
lock all item IDs ascending
 ↓
for each:
    delta = qty_fisik - qty_sistem_at_count
    if delta != 0:
        insert opname_adjustment
        update current stock
 ↓
status completed
```

Tidak ada snapshot massal di awal.

---

## 13.1 Analytics & Smart Threshold

Canonical business window memakai zona waktu `Asia/Jakarta` dan half-open `[as_of - duration, as_of)`. Representasi timestamp persisten tidak boleh mengubah boundary tersebut. Eligibility memakai `items.created_at + 30×24 jam`.

```text
RecalculateItemAnalyticsAction
 ↓
resolve tenant + active item
 ↓
aggregate sale, sale_void, customer_return
for 30-day and dead windows
 ↓
resolve preferred supplier lead time
or item fallback
 ↓
AnalyticsCalculator (pure)
 ├── ineligible → unclassified
 ├── dead override
 └── fast | normal | slow
 ↓
persist movement_class + analytics_calculated_at
 ↓
if threshold_mode=auto_velocity
    persist stok_minimal
else
    preserve manual stok_minimal
```

Net demand:

```text
net_out = max(0, Σ sale - Σ sale_void - Σ customer_return)
avg_daily_out = net_out_30_days / 30
```

Classification precedence:

1. history `< 30×24 jam` → `unclassified`;
2. dead enabled, item cukup umur, net dead-window demand nol → `dead`;
3. average `>=1.00` → `fast`;
4. average `>=0.25` → `normal`;
5. selain itu → `slow`.

`dead_stock_days=0` menonaktifkan dead. Preferred lead time non-null, termasuk nol, mengoverride fallback item.
Eligible item tanpa movement menghasilkan threshold nol dan kelas slow, kecuali dead override terpenuhi.

Recalculation sources:

- after-commit event untuk `sale|sale_void|customer_return`;
- perubahan preferred supplier/lead time atau item lead/safety/mode;
- perubahan tenant dead days untuk seluruh item aktif tenant;
- daily sweep untuk aging, window shift, dan recovery;
- explicit `ApplySmartThresholdAction` dari endpoint/UI.

Daily sweep memproses tenant/item secara chunk. Job harus idempotent dan boleh dikoaleskan per item. Kegagalan analytics tidak me-rollback transaksi POS yang sudah committed.

Seluruh item aktif memperoleh persisted class/timestamp saat recalculation berhasil, termasuk item bermode manual. Hanya persistence `stok_minimal` yang dibatasi ke `auto_velocity`.

Explicit apply melakukan ownership validation lalu menghitung dan menyimpan konfigurasi, threshold, class, serta calculation timestamp secara atomik. Item ineligible menghasilkan HTTP 422 tanpa mutation.

---

## 14. Trial Invariant

`CreateSubscriptionAction` harus:

```text
resolve tenant owner
resolve owner no_hp
find any historical subscription
where subscription.plan.is_trial = true
if exists → reject
else → create trial
```

Trial record tidak boleh dihapus individual.

---

## 15. Tenant Purge

Purge hanya dilakukan oleh:

`PurgeTenantCommand`.

Preconditions:

- deletion request status `queued`;
- purge_after <= now;
- tenant tidak aktif;
- safety checks lulus.

Operation:

```text
DB transaction
DELETE FROM tenants WHERE id = tenantId
COMMIT
```

Foreign keys `ON DELETE CASCADE` menghapus child records sebagai satu unit.

Tidak ada manual cascade code.

---

## 16. Impersonation

Super Admin impersonation wajib:

- reason;
- target tenant;
- target user;
- start;
- expiry;
- audit event;
- visible banner;
- explicit end.

Tidak boleh digunakan tanpa alasan.

---

## 17. Authorization

### Owner

Full tenant capabilities.

### Staff

POS + allowed stock operations. Mulai Fase 8, insight analytics hanya read-only dan tidak boleh membawa cost, margin, valuation, profit, atau billing.

Fase 8 mengunci allowed stock operations menjadi read-only item/stok/low-stock/supplier. Staff hanya melihat dan membayar transaksi dengan `cashier_id` sendiri; diskon POS existing tetap diizinkan. Ability `pay` terpisah dari `void`, `return`, dan `refund`.

Session web menyimpan `users.auth_version` saat login dan memeriksanya pada setiap request. Deactivate/reset menaikkan version serta menghapus seluruh token; mekanisme ini tidak bergantung pada session driver. Provider dan middleware menolak User nonaktif sebelum tenant context dipakai.

### Super Admin

Platform administration.

### Support

Support read-only.

Authorization harus melalui Policy/permission gate.

---

## 18. Testing

### Unit

- money calculation;
- MAC;
- threshold;
- Net POS demand dan classification boundary;
- analytics eligibility/dead override;
- return quantity;
- refund amount;
- state transition.

### Feature

- auth;
- POS;
- billing;
- onboarding;
- deletion request.
- Smart Threshold apply dan no-mutation 422.
- analytics after-commit job dan daily sweep.

### Integration

- queued POS export PDF/XLSX;
- billing Midtrans/webhook pada Fase 11.

### Concurrency

- stock race;
- multi-item lock ordering;
- preferred supplier;
- opname;
- duplicate idempotency request.

### Security

- tenant isolation;
- ownership guard;
- role visibility;
- analytics tenant isolation dan Staff financial-field exclusion;
- impersonation audit.

---

## 19. Deployment

- Docker.
- CI tests before deploy.
- migration checks.
- queue worker.
- Horizon.
- scheduled jobs.
- daily analytics sweep.
- backup.
- restore verification.
- billing webhook endpoint monitoring setelah Fase 11.
- application error monitoring.

---

## 20. F9A Security Runtime

CD-9.2 menambahkan empat named rate limiter pada boundary HTTP:

```text
request
  -> login limiter (unauthenticated, email hash + IP)
  -> authentication / tenant context
  -> read | mutation | export limiter (tenant + User)
  -> Policy / Ownership Guard
  -> Controller
  -> Action
```

Limiter berjalan sebelum controller/Action sehingga `429` tidak dapat menghasilkan mutation, audit, event, atau job. Logout dikecualikan agar token/session revocation selalu dapat dilakukan. Redis menjadi backing store runtime multi-process; array/file hanya boleh dipakai pada unit test single-process.

Security-header middleware berlaku pada web, API, dan private download. HSTS hanya aktif pada production HTTPS. CORS browser deny-by-default dan membuka origin eksplisit tanpa wildcard credential. Private response memakai `no-store`, private disk tidak dilayani langsung, dan PHP version disclosure wajib nonaktif pada hardening/deployment runtime.

`SensitiveDataRedactor` menjadi boundary bersama sebelum metadata ditulis oleh audit atau structured logger. Redaction recursive mempertahankan key/shape dan mengganti value credential/token/cookie/OTP/signature dengan `[REDACTED]`; kegagalan redaction tidak boleh menggagalkan business transaction.

F9A hanya membuktikan behavior tersebut melalui local Docker dan CI. Health deployment, backup/restore, RPO/RTO, serta alert delivery tetap menjadi F9B.
