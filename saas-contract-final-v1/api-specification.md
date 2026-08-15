# API Specification
# Smart POS & Manajemen Stok — SaaS Ritel Komoditas Padat SKU

## 1. Konvensi

Base:

`/api/v1`

Authentication:

`Authorization: Bearer {sanctum_token}`

Tenant ID tidak pernah diterima sebagai parameter untuk menentukan scope.

Standard response:

```json
{
  "status": "success",
  "message": "...",
  "data": {}
}
```

Error:

```json
{
  "status": "error",
  "message": "...",
  "error_code": "...",
  "errors": {}
}
```

---

## 2. Idempotency

Endpoint command yang dapat di-retry wajib menerima:

`Idempotency-Key: UUID`

Minimal:

- POST `/pos/checkout`
- POST `/pos/transactions/{id}/qris/generate`
- webhook payment processing menggunakan provider reference/idempotency rule
- subscription payment creation jika endpoint command tersebut tersedia

Duplicate request harus menghasilkan hasil business operation yang sama, bukan record ganda.

---

## 3. Authentication

### `POST /auth/register`

v1 self-service only.

Request:

```json
{
  "nama_toko": "Toko Jaya",
  "owner_name": "Budi",
  "email": "budi@example.com",
  "no_hp": "081234567890",
  "password": "..."
}
```

Response:

```json
{
  "status": "success",
  "data": {
    "otp_token": "otp|...",
    "requires_otp_verification": true
  }
}
```

Jika nomor pernah digunakan untuk trial:

`422 PHONE_ALREADY_USED_FOR_TRIAL`.

### `POST /auth/register/verify-otp`

Request:

```json
{
  "otp_token": "otp|...",
  "otp": "123456"
}
```

Jika valid:

- create/activate owner;
- create tenant;
- create 14-day trial;
- issue Sanctum token.

### `POST /auth/login`

### `POST /auth/2fa/verify`

---

## 4. Items

### `GET /items`

Filter:

- search;
- category_id;
- rack_id;
- sort;
- pagination.

Foreign filter IDs harus melewati ownership validation.

### `GET /items/scan/{barcode}`

Optimized lookup untuk POS.

### `POST /items/{id}/smart-threshold`

Request:

```json
{
  "threshold_mode": "auto_velocity",
  "lead_time_days": 5,
  "safety_stock_days": 2
}
```

### Rack CRUD

`GET /racks`

`POST /racks`

`PUT /racks/{id}`

Rack delete hanya jika tidak melanggar active opname/data integrity.

---

## 5. POS

### `POST /pos/checkout`

Header:

`Idempotency-Key`

Request:

```json
{
  "items": [
    {
      "item_id": 10,
      "qty": 2,
      "discount_amount": 5000
    }
  ]
}
```

Server calculates:

- price;
- subtotal;
- discount;
- total.

Response:

```json
{
  "status": "success",
  "data": {
    "id": 104,
    "status": "pending_payment",
    "subtotal_amount": 100000,
    "discount_amount": 5000,
    "total_amount": 95000
  }
}
```

### `POST /pos/transactions/{id}/pay-cash`

Request:

```json
{
  "cash_received": 100000
}
```

Response:

```json
{
  "status": "success",
  "data": {
    "transaction_id": 104,
    "status": "completed",
    "total_amount": 95000,
    "cash_received": 100000,
    "change_amount": 5000
  }
}
```

### `POST /pos/transactions/{id}/qris/generate`

Header:

`Idempotency-Key`

Body kosong.

Rules:

- transaction must be pending;
- no client amount;
- active QR is reused;
- expired QR may be replaced;
- gateway reference stored.

Response:

```json
{
  "status": "success",
  "data": {
    "qris_reference": "MIDTRANS-123",
    "qr_image_url": "...",
    "expires_at": "2026-08-10T10:15:00Z"
  }
}
```

### `GET /pos/transactions/{id}/status`

Response:

```json
{
  "status": "success",
  "data": {
    "transaction_status": "pending_payment",
    "payment": {
      "method": "qris",
      "status": "pending"
    }
  }
}
```

Transaction status dan payment status dikembalikan sebagai dua field berbeda.

### `POST /webhooks/midtrans`

Internal provider endpoint.

Wajib:

- verify signature;
- resolve gateway reference;
- idempotency;
- state validation;
- duplicate safe;
- out-of-order safe.

Provider webhook tidak menerima Bearer token dari tenant client.

### `POST /pos/transactions/{id}/void`

Request:

```json
{
  "reason": "Salah input"
}
```

Jika QRIS payment sudah paid:

response menunjukkan refund required/payment status.

### `POST /pos/transactions/{id}/return`

Request:

```json
{
  "items": [
    {
      "pos_transaction_item_id": 501,
      "qty_returned": 1,
      "reason": "Barang cacat"
    }
  ]
}
```

Return quantity kumulatif tidak boleh melebihi sold quantity.

---

## 6. POS Payment Refund

### `POST /pos/payments/{id}/mark-refunded`

Owner only.

Request:

```json
{
  "refunded_amount": 95000,
  "note": "Refund manual via Midtrans"
}
```

Validation:

- payment must be `refund_required` or `partially_refunded`;
- refunded amount cannot exceed amount;
- actor must be authorized.

Response:

```json
{
  "status": "success",
  "data": {
    "payment_id": 10,
    "status": "refunded",
    "refunded_amount": 95000
  }
}
```

---

## 7. Stock Movement

### `POST /stock/movements/in`

```json
{
  "item_id": 10,
  "qty": 50,
  "harga_satuan": 45000,
  "supplier_id": 2,
  "note": "Kulakan"
}
```

### `POST /stock/movements/out`

```json
{
  "item_id": 10,
  "qty": 5,
  "note": "Barang rusak"
}
```

### `POST /stock/movements/adjustment`

```json
{
  "item_id": 10,
  "qty": 2,
  "direction": "decrease",
  "note": "Selisih manual"
}
```

---

## 8. Cycle Counting

### `POST /opname`

Partial:

```json
{
  "scope_type": "partial",
  "rack_id": 3
}
```

Full:

```json
{
  "scope_type": "full"
}
```

Session membership dibuat dari item aktif dan non-deleted dalam scope lalu dibekukan. Scope tanpa item menghasilkan validation error 422.

### `GET /opname`

### `PUT /opname/{id}/details`

```json
{
  "items": [
    {
      "item_id": 10,
      "qty_fisik": 23,
      "note": "..."
    }
  ]
}
```

Response includes:

```json
{
  "item_id": 10,
  "qty_sistem_at_count": 25,
  "qty_fisik": 23,
  "counted_at": "..."
}
```

`qty_fisik` wajib integer `>= 0` dan `item_id` tidak boleh duplikat dalam satu request. Save pertama mengunci snapshot/count timestamp; save berikutnya hanya mengoreksi physical quantity dan note.

### `POST /opname/{id}/finalize`

Requires every detail counted.

---

## 9. Suppliers

### `GET /items/{id}/suppliers`

### `POST /items/{id}/suppliers`

### `PUT /item-suppliers/{id}`

### `DELETE /item-suppliers/{id}`

`is_preferred=true` invokes `SetPreferredSupplierAction`.

---

## 10. Shopping Lists

### `GET /shopping-lists`

### `POST /shopping-lists/generate`

Uses preferred supplier only.

If no preferred supplier:

`supplier_id = null`.

### `POST /shopping-lists/{id}/submit`

All selected items require supplier and quantity.

### `POST /shopping-lists/{id}/receive`

Receiving calls stock-in Action.

---

## 11. Billing

### MVP

Admin-only endpoints may create tenant/subscription/payment records through admin panel.

### v1

Self-service and gateway billing endpoints are enabled.

Representative:

`GET /billing/subscription`

`GET /billing/invoices`

`POST /billing/invoices/{id}/pay`

Gateway webhook endpoint:

`POST /webhooks/midtrans-billing`

Billing webhook is separate from POS QRIS webhook even if provider is the same.

---

## 12. Tenant Deletion

### `POST /tenant/deletion-request`

Owner only.

Request:

```json
{
  "reason": "Tidak lagi menggunakan layanan"
}
```

### `GET /tenant/deletion-request`

### `POST /admin/tenant-deletion-requests/{id}/approve`

### `POST /admin/tenant-deletion-requests/{id}/reject`

No endpoint exists for deleting individual transactions.

Purge is command/scheduler driven, not public API.

---

## 13. Admin Support

Representative endpoints:

- `GET /admin/tenants`
- `GET /admin/tenants/{id}`
- `POST /admin/tenants`
- `POST /admin/tenants/{id}/ban`
- `POST /admin/tenants/{id}/unban`
- `POST /admin/tenants/{id}/extend`
- `POST /admin/tenants/{id}/reset-owner`
- `POST /admin/impersonation/start`
- `POST /admin/impersonation/end`

Impersonation requires:

```json
{
  "tenant_id": 10,
  "user_id": 21,
  "reason": "Investigasi tiket support #1234"
}
```

Session expiry enforced server-side.

---

## 14. Error Codes

Minimum:

- `UNAUTHENTICATED`
- `FORBIDDEN`
- `TENANT_ACCESS_DENIED`
- `OWNERSHIP_VIOLATION`
- `INSUFFICIENT_STOCK`
- `ITEM_INACTIVE`
- `INVALID_STATE_TRANSITION`
- `IDEMPOTENCY_CONFLICT`
- `QR_ALREADY_ACTIVE`
- `PAYMENT_NOT_FOUND`
- `PAYMENT_ALREADY_REFUNDED`
- `REFUND_AMOUNT_EXCEEDED`
- `REFUND_REQUIRED`
- `OPNAME_SCOPE_CONFLICT`
- `OPNAME_INCOMPLETE`
- `SUPPLIER_REQUIRED`
- `PREFERRED_SUPPLIER_CONFLICT`
- `PHONE_ALREADY_USED_FOR_TRIAL`
- `TRIAL_ALREADY_CONSUMED`
- `SUBSCRIPTION_NOT_ACTIVE`
- `DELETION_REQUEST_EXISTS`

---

## 15. API Versioning

Breaking changes require `/api/v2`.

Non-breaking:

- new response field;
- new endpoint;
- optional query parameter.

Breaking:

- remove/rename field;
- change field type;
- change existing required request field;
- incompatible status semantics.

Deprecation uses `Sunset` header and documented migration period.


Canonical lifecycle rules:

- `generate` returns `data = null` and does not persist a list when no active low-stock item exists.
- `submit` performs `draft -> purchased`; every selected row requires `supplier_id` and `qty_dibeli > 0`.
- `receive` performs `purchased -> completed`; it is one-time, locks list/items deterministically, and requires positive actual quantity for every purchased row.
- Recommended quantity is `max(1, stok_minimal - stok_saat_ini)`.
