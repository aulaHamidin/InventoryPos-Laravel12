# Blueprint SaaS Manajemen Stok & Smart POS Multi-Tenant

## 1. Ringkasan

Produk adalah monolith Laravel 12 multi-tenant shared-schema untuk toko ritel komoditas padat SKU.

Domain utama:

1. Tenant & identity
2. Master data
3. Inventory ledger
4. Smart POS
5. Payment
6. Cycle counting
7. Shopping recommendation
8. Analytics
9. Billing
10. Administration
11. Audit & security

Prioritas domain:

`Inventory Accuracy > POS Speed > Recommendation > Simplicity > Reporting > Multi-store`.

---

## 2. Batasan Arsitektur

- Shared schema.
- Semua tenant data memiliki `tenant_id`.
- `tenant_id` berasal dari authenticated context.
- Relasi antar-tenant diverifikasi melalui Ownership Guard.
- Tidak ada repository layer.
- Action Pattern.
- Monolith.
- Tidak ada microservices.
- Tidak ada CQRS/Event Sourcing penuh.
- Event hanya side-effect.
- Stock movement immutable.

---

## 3. Tenant Model

Setiap tenant memiliki:

- owner;
- optional staff;
- master data;
- transaksi;
- subscription;
- audit history.

`tenants.operational_status` hanya:

- `active`
- `banned`

Status billing tidak berada di tenant.

Billing source of truth:

`subscriptions.status`.

Capability access dihitung dari kombinasi:

1. tenant operational status;
2. subscription status;
3. user role;
4. permission.

---

## 4. Role Model

### Owner

Full tenant access.

### Staff/Kasir

Boleh:

- POS;
- melihat harga jual;
- supplier read-only;
- stock input dasar sesuai permission.

Tidak boleh:

- harga beli;
- margin;
- nilai persediaan;
- laba;
- billing;
- manage staff;
- delete master;
- void;
- return/refund authorization.

Fase 8 mengunci staff pada POS (termasuk diskon existing), item/stok/low-stock/supplier read-only, analytics operasional non-finansial, dan transaksi `cashier_id` sendiri. Stock mutation/opname/receiving, Shopping List, report/export, void/return/refund, billing, dan staff management tetap Owner-only.

### Super Admin

Boleh:

- create/ban/unban tenant;
- manage subscription;
- support access;
- reset owner;
- impersonate dengan audit.

Tidak boleh:

- direct-edit `stok_saat_ini`;
- menghapus transaksi individual;
- menghapus movement;
- menghapus payment.

### Support

Read-only support access sesuai permission.

---

## 5. Lifecycle Produk

### MVP

- admin-created tenant;
- owner-only;
- core inventory;
- basic POS;
- manual billing.

### v1

- staff/kasir;
- QRIS statis dan transfer manual;
- Bluetooth printing;
- self-service onboarding;
- OTP;
- automated billing;
- analytics;
- Smart Threshold;
- full operational hardening.

### v2

- multi-outlet;
- richer analytics;
- automated refund;
- optional external integrations;
- additional notifications.

---

## 6. Inventory Ledger

### 6.1 Invariant

Untuk setiap mutasi:

```text
BEGIN TRANSACTION
  lock item row
  validate tenant ownership
  validate business invariant
  insert immutable movement
  update items.stok_saat_ini
COMMIT
```

Tidak boleh:

```text
insert movement
commit
update stock
```

dalam dua transaction berbeda.

### 6.2 Movement types

- `stock_in`
- `stock_out`
- `sale`
- `customer_return`
- `supplier_return`
- `damaged`
- `adjustment`
- `opname_adjustment`

### 6.3 Negative stock

Default:

`allow_negative_stock = false`.

Jika false, stock decrease harus memenuhi:

`qty <= stok_saat_ini`.

Jika true, transaksi dapat menghasilkan stok negatif dan harus terlihat jelas pada dashboard.

---

## 7. Locking Standard

Semua Action yang memutasi satu atau banyak item harus memperoleh lock dalam urutan `item_id ASC`.

Contoh:

```text
cart item IDs: [10, 2, 7]
sort → [2, 7, 10]
lock 2
lock 7
lock 10
```

Tujuannya mengurangi deadlock akibat dua request mengunci item dengan urutan berbeda.

Test wajib mencakup concurrent multi-item transaction.

---

## 8. Ownership Guard

Global scope membantu query default, tetapi tidak dianggap sebagai satu-satunya pertahanan.

Untuk setiap foreign key tenant-scoped yang berasal dari request:

- `category_id`
- `rack_id`
- `supplier_id`
- `item_id`
- `item_supplier_id`
- `pos_transaction_item_id`
- `stock_opname_id`
- `shopping_list_id`

Action wajib memverifikasi bahwa object tersebut berada pada tenant aktif.

Request dengan ID tenant lain:

`403/404` sesuai policy exposure, dan tidak boleh memproses data.

---

## 9. Smart POS

### 9.1 Checkout

Client mengirim:

- item ID;
- quantity;
- line discount.

Server:

1. mengambil harga jual;
2. memvalidasi item aktif;
3. memvalidasi quantity;
4. menghitung subtotal;
5. menghitung discount;
6. menghitung total;
7. membuat `pos_transaction`;
8. membuat `pos_transaction_items`.

Client tidak dipercaya untuk:

- unit price;
- subtotal;
- total;
- stock quantity.

### 9.2 Cash

`checkout → pay-cash → finalize`.

Stok divalidasi ulang ketika finalize.

Jika stok tidak cukup:

- transaction `failed`;
- tidak ada external payment;
- tidak ada refund.

### 9.3 QRIS Statis dan Transfer Manual

`checkout → pending_payment → operator confirmation → finalize`.

Operator memeriksa aplikasi merchant atau rekening toko. Aplikasi tidak menghasilkan QR, tidak memanggil provider POS, dan tidak menganggap screenshot customer sebagai bukti.

Tidak ada stock reservation. Stok divalidasi saat checkout dan saat confirmation. Jika validasi kedua gagal, transaction/payment menjadi `refund_required` tanpa sale movement.

Pending checkout memiliki TTL 24 jam. Setelah expiry, payment ditolak dan checkout baru wajib dibuat.

---

## 10. Payment Domain

POS payment dipisahkan dari billing payment.

### 10.1 `pos_transactions`

Lifecycle barang/transaksi:

```text
pending_payment
completed
failed
expired
voided
partially_returned
fully_returned
```

`refund_required` adalah status transaksi bisnis khusus ketika transaksi sudah memiliki payment yang valid tetapi finalisasi barang tidak dapat dilakukan.

### 10.2 `pos_payments`

Lifecycle uang:

```text
pending
paid
failed
refund_required
partially_refunded
refunded
```

Satu transaksi dapat memiliki payment record, tetapi status payment tidak digabung ke status transaction.

### 10.3 Manual payment idempotency

`POST pay-manual` wajib memakai `Idempotency-Key` unique per tenant. Key sama dengan transaction/method/reference/note yang sama mengembalikan hasil lama; key sama dengan payload atau transaction berbeda menghasilkan `IDEMPOTENCY_CONFLICT`.

Key yang sudah digunakan actor lain juga menghasilkan `IDEMPOTENCY_CONFLICT`; resource actor pertama tidak dikembalikan.

Duplicate key race harus ditangani dari unique constraint tanpa berubah menjadi HTTP 500.

---

## 11. Void, Return, Refund

### Void

Hanya transaksi completed yang belum memiliki return.

Void:

1. lock transaction;
2. lock item IDs ascending;
3. create reversal stock movements;
4. update transaction status;
5. create refund obligation bila payment sudah dibayar.

Cash, QRIS, dan transfer:

`pos_payment → refund_required`, lalu Owner mencatat pengembalian uang secara manual.

### Partial return

Return mengacu pada `pos_transaction_items`.

Kuantitas kumulatif yang diretur tidak boleh melebihi quantity terjual.

Status:

- masih ada item/qty yang belum diretur → `partially_returned`;
- seluruh qty selesai → `fully_returned`.

Return seluruh metode menghasilkan refund obligation sesuai cumulative net line amount.

Refund v1 manual.

Owner menekan:

`Tandai Sudah Refund`.

Sistem menyimpan:

- refunded amount;
- refunded_at;
- refunded_by.

---

## 12. Inventory Valuation

Valuasi adalah estimasi.

### Stock In

```text
new_avg =
(
  old_stock * old_avg
  +
  in_qty * in_cost
)
/
(
  old_stock + in_qty
)
```

### Stock Out

Average cost tetap.

### Customer Return

Average cost tidak dihitung ulang.

Return dinilai menggunakan average cost saat return.

### Supplier Return

Average cost tidak dihitung ulang.

### Damaged / Adjustment / Opname

Average cost tidak dihitung ulang.

Kontrak ini sengaja tidak melakukan historical-cost accounting.

---

## 13. Smart Threshold

Semua boundary bisnis kalkulasi memakai `as_of` dalam zona waktu `Asia/Jakarta` dan half-open window `[as_of - duration, as_of)`. Representasi penyimpanan internal tidak boleh mengubah boundary tersebut. Item baru menjadi eligible setelah memiliki histori penuh 30×24 jam berdasarkan `items.created_at`. Sebelum itu class adalah `unclassified`, mode threshold tetap manual, dan tidak ada prorata hari aktif.

Demand hanya berasal dari Net POS:

```text
net_out = max(
  0,
  Σ sale - Σ sale_void - Σ customer_return
)

avg_daily_out = net_out_30_days / 30

threshold =
ceil(
  avg_daily_out
  * (effective_lead_time_days + safety_stock_days)
)
```

Movement stock-in/out operasional, supplier return, damaged, adjustment, dan opname adjustment tidak dihitung sebagai demand.

Classification:

| Kelas | Rule |
|---|---|
| `unclassified` | histori `< 30×24 jam` |
| `fast` | `avg_daily_out >= 1.00` |
| `normal` | `0.25 <= avg_daily_out < 1.00` |
| `slow` | `0 <= avg_daily_out < 0.25` |
| `dead` | item cukup umur dan net demand pada dead window nol |

Dead dievaluasi setelah eligibility dan sebelum velocity. `dead_stock_days=0` menonaktifkan dead. Item eligible tanpa movement menghasilkan threshold `0` dan menjadi slow kecuali memenuhi dead.

Effective lead time berasal dari preferred supplier bila nilainya non-null—termasuk nol—kemudian fallback ke `items.lead_time_days`. `stok_minimal` hanya diubah otomatis dalam mode `auto_velocity`; mode manual tidak boleh ditimpa.

Event `sale|sale_void|customer_return` menjadwalkan recalculation setelah commit. Perubahan preferred supplier/lead time, item lead/safety days, threshold mode, dan dead days juga memicu recalculation. Daily sweep menangani aging/window shift dan explicit Smart Threshold action menghitung langsung menggunakan calculator yang sama.

Forecasting statistik bukan bagian produk.

---

## 14. Shopping List

Generate hanya item:

`stok_saat_ini <= stok_minimal/threshold`.

Supplier:

1. preferred supplier;
2. jika tidak ada → `supplier_id = null`.

Tidak boleh memilih supplier berdasarkan:

- last movement;
- harga terakhir;
- random selection.

Sebelum submit:

- supplier wajib ada;
- quantity wajib > 0.

---

## 15. Cycle Counting

### Session

`draft → completed`.

Scope:

- `partial + rack_id`;
- `full`.

Constraint:

- partial pada rack yang sama tidak boleh overlap;
- full tidak boleh overlap dengan partial aktif;
- partial antar rack berbeda boleh paralel.

Membership sesi berasal dari item aktif dan non-deleted dalam scope saat create dan tidak berubah setelah detail dibuat. Scope kosong ditolak.

### Detail

Saat item dihitung:

```text
lock item
qty_sistem_at_count = stok_saat_ini
counted_at = now
qty_fisik = input
```

`qty_fisik` wajib integer non-negatif. Save pertama mengunci snapshot dan `counted_at`; save berikutnya hanya mengoreksi `qty_fisik` dan `note`.

Finalize:

```text
selisih = qty_fisik - qty_sistem_at_count
```

Kemudian movement adjustment diterapkan terhadap stok terkini.

Contoh:

```text
count snapshot = 100
fisik = 98
setelah count terjadi sale 5
stok sekarang = 95

adjustment = -2
final = 93
```

Hasil 93 benar karena 5 unit sale terjadi setelah snapshot.

---

## 16. Supplier Model

`item_suppliers` memiliki:

- item;
- supplier;
- supplier SKU;
- last purchase price;
- lead time;
- preferred flag.

Invariant:

> maksimum satu preferred supplier aktif per item.

`SetPreferredSupplierAction` menjalankan transaction dan lock item terlebih dahulu.

Concurrency test wajib membuktikan dua request simultan tidak dapat menghasilkan dua preferred supplier.

---

## 17. Billing

### Subscription states

```text
trial
active
past_due
suspended
expired
```

Capability matrix harus eksplisit.

CD-10.1 capability matrix:

- `trial|active`: read, operational write, dan configuration write sesuai role;
- `past_due`: read dan operational write; configuration/master/Staff/analytics mutation/export diblokir;
- `suspended|expired`: read-only; Owner tetap dapat billing dan deletion;
- missing/corrupt: Owner hanya billing/support/deletion, Staff ditolak;
- operational ban selalu lebih kuat dan capability subscription tidak pernah menambah hak role.

Scheduler Jakarta mengubah trial ke expired dan active ke past_due pada `ends_at`, lalu past_due ke suspended setelah tujuh hari. Expired terminal.

`tenants.operational_status` tidak berubah oleh scheduler billing.

### Trial invariant

Satu pemilik/nomor HP hanya satu trial sepanjang masa.

Lifetime invariant dipertahankan setelah purge melalui unique HMAC pada `trial_claims` menggunakan secret `IDENTITY_HASH_KEY`; raw nomor HP tidak disimpan.

`CreateSubscriptionAction`:

1. resolve owner;
2. query subscription history;
3. join/check plan `is_trial`;
4. jika pernah trial → reject;
5. jika belum → create trial.

Trial history tidak boleh hilang dari histori individual.

---

## 18. Data Retention & Purge

Individual historical record tidak memiliki delete endpoint.

Tenant deletion:

```text
requested
→ approved
→ queued
→ retention
→ purge
```

Purge:

```sql
DELETE FROM tenants WHERE id = ?
```

dengan FK child menggunakan `ON DELETE CASCADE`.

Purge dieksekusi oleh satu scheduled command.

Tidak ada penghapusan manual tersebar per tabel.

Retention F10 adalah 30 hari. Owner dapat cancel sebelum approval dan Super Admin dapat cancel sebelum queued. Global `tenant.purged` tombstone tanpa PII bertahan setelah cascade.

---

## 19. Audit

Audit event minimal:

- login/logout;
- OTP verification;
- role changes;
- security setting;
- master data mutation;
- stock movement;
- stock opname;
- POS void;
- POS return;
- refund marking;
- billing changes;
- tenant suspension;
- support access;
- impersonation;
- deletion request;
- tenant purge.

Audit log immutable bagi pengguna aplikasi.

---

## 20. Architecture

- Laravel 12 monolith.
- Livewire/Filament.
- MySQL 8+.
- Redis.
- Queue/Horizon.
- Sanctum.
- S3-compatible storage.
- Docker.
- Action Pattern.
- Policy.
- Event/Listener hanya side effect.
- Tidak ada repository.
- Tidak ada service-to-UI dependency.

---

## 21. Testing Contract

Minimum:

- tenant isolation;
- ownership guard;
- stock race condition;
- deterministic locking;
- POS idempotency;
- manual payment duplicate/unique-key race;
- cash-vs-manual payment concurrency;
- pending expiry race;
- payment-stock reconciliation;
- refund marking;
- partial return;
- void;
- opname concurrency;
- time-aware opname;
- preferred supplier concurrency;
- trial reuse rejection;
- subscription capability;
- purge cascade;
- impersonation audit;
- role visibility.

---

## 22. Deployment

Environment:

- local;
- staging;
- production.

Database migration wajib backward-safe bila memungkinkan.

Backup:

- scheduled database backup;
- restore test berkala.

Queue failures harus terlihat.

Billing webhook failures harus memiliki monitoring setelah billing gateway aktif.
