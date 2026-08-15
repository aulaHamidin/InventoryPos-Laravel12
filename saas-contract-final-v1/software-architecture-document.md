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

POS + allowed stock operations.

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
- return quantity;
- refund amount;
- state transition.

### Feature

- auth;
- POS;
- billing;
- onboarding;
- deletion request.

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
- impersonation audit.

---

## 19. Deployment

- Docker.
- CI tests before deploy.
- migration checks.
- queue worker.
- Horizon.
- scheduled jobs.
- backup.
- restore verification.
- billing webhook endpoint monitoring setelah Fase 11.
- application error monitoring.
