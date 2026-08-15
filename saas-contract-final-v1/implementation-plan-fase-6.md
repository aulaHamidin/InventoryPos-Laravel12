# Implementation Plan Fase 6 — POS Lengkap & Pembayaran Manual Non-Tunai

Status: **IMPLEMENTED — LOCAL/VISUAL PASS, CI REMOTE PENDING**

Baseline: commit `daefc43` — Fase 5 selesai, local/visual/CI hijau.

Document Delta: [`document-delta-f6-pos-manual-payment.md`](document-delta-f6-pos-manual-payment.md)

## 1. Scope

- Metode historis `cash|qris|transfer`; QRIS dan transfer dikonfirmasi manual.
- Checkout pending memiliki TTL 24 jam.
- Provider-neutral stock finalizer untuk cash dan manual non-tunai.
- Void, partial return, cumulative refund, refund due, audit, dan Owner notification.
- Histori, receipt, filter, queued PDF/XLSX, serta laporan per metode.
- Web Bluetooth ESC/POS beta di balik feature flag; print dialog/PDF menjadi default.
- Tidak ada Midtrans POS, dynamic QR, provider webhook, polling, atau automated refund.

## 2. Schema dan interface

- Migration additive: `transfer`, `confirmed_by`, `manual_reference`, `confirmation_note`, payment index, dan movement `sale_void`.
- Config: `POS_PENDING_TRANSACTION_EXPIRY_HOURS=24` dan `POS_BLUETOOTH_PRINT_ENABLED=false`.
- Command terjadwal `pos:expire-pending` setiap menit dengan `withoutOverlapping`.
- API baru: `pay-manual`, `void`, `return`, `mark-refunded`; status response diperluas.
- Manual payment memakai tenant-scoped `Idempotency-Key`; key sama lintas transaction menjadi `IDEMPOTENCY_CONFLICT`, termasuk pada concurrent unique-key race.

## 3. Domain behavior

- Lock order: transaction → payments → transaction items/item IDs → stock items.
- Cash stock failure menghasilkan transaction failed tanpa payment.
- Manual non-tunai stock failure merekam payment/transaction refund_required tanpa sale movement.
- Void menambah stok dengan `sale_void` dan full refund obligation.
- Return menambah stok dengan `customer_return` dan cumulative exact net-line obligation.
- `refunded_amount` cumulative; due dihitung dari obligation dikurangi refunded.
- Semua mutation melalui Action dan seluruh histori/audit immutable.

## 4. UI, report, dan print

- POS menampilkan Cash, QRIS Statis, Transfer Bank serta explicit manual-verification warning.
- History/detail/receipt/export menampilkan method, payment state, confirmer, reference, refund obligation/due sesuai permission.
- Owner report merangkum payment per metode; Staff tetap tidak memperoleh data financial.
- `npm test` menggunakan Node test runner untuk formatter, ESC/POS profiles, chunking, success/failure/fallback mocks.
- Bluetooth button hanya tampil ketika flag aktif; hardware validation bersifat conditional.

## 5. Release blocker

1. QRIS manual + stok tersedia → completed.
2. Transfer manual + stok tersedia → completed.
3. Dana diterima + stok gagal → refund_required.
4. Duplicate confirmation → satu payment/movement.
5. Concurrent cash vs manual → tepat satu diterapkan.
6. Void semua metode → full refund obligation.
7. Partial return → cumulative exact refund dan due benar.
8. Bluetooth unsupported → print fallback.
9. Pending melewati TTL → expired dan tidak dapat dibayar.
10. Histori/export/receipt menampilkan metode yang benar.

## 6. Exit gate

- Migration fresh dan upgrade dari F5 lulus.
- PHP, API, multi-process concurrency, reporting/security, dan JavaScript tests lulus.
- `npm test`, build, Pint, Composer checks, view cache, route list, dan CI remote hijau.
- Visual desktop/mobile serta fallback printing terdokumentasi di `docs/evidence/f6-pos-manual-payment-2026-08-XX/`.
- Tidak ada route/config/dependency/secret/placeholder Midtrans POS.
