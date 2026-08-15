# Document Delta Declaration — Fase 6 POS Manual Non-Tunai

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Tanggal: **15 Agustus 2026**

## Affected documents

- `prd-saas-manajemen-stok.md`
- `blueprint-saas-stok.md`
- `database-design-document.md`
- `software-architecture-document.md`
- `api-specification.md`
- `ui-ux-specification.md`
- `development-roadmap.md`
- `implementation_plan.md`
- `master-plan-fase-5-12.md`
- `.agents/AGENTS.md`

## Reason

Integrasi Midtrans untuk pembayaran POS belum pernah diimplementasikan pada baseline Fase 0–5. Kebutuhan toko adalah mencatat metode pembayaran historis dan melakukan konfirmasi manual melalui media milik toko, bukan membuat QR dinamis atau memverifikasi pembayaran POS melalui provider.

Midtrans untuk billing SaaS Fase 11 tetap dipertahankan sebagai domain terpisah.

## Current contract

- POS mendukung cash dan QRIS dinamis Midtrans.
- Fase 6 merencanakan QR generation, provider metadata, webhook, signature verification, expiry QR, status polling, dan reconciliation event terlambat.
- Release blocker berpusat pada duplicate/out-of-order webhook dan Midtrans sandbox.

## Proposed contract

### Metode dan konfirmasi

- POS mendukung `cash`, `qris`, dan `transfer`.
- `qris` berarti QRIS statis/manual milik toko.
- `qris|transfer` dikonfirmasi manual oleh operator berwenang setelah memeriksa aplikasi merchant atau rekening toko.
- Aplikasi tidak memverifikasi bank/provider, tidak menerima amount dari client, dan tidak menganggap screenshot pelanggan sebagai bukti pembayaran.
- Pada Fase 6 operator hanya Owner; `confirmed_by` disiapkan untuk permission Staff pada Fase 8.

### Expiry

- Semua `pending_payment` berlaku maksimal 24 jam berdasarkan `POS_PENDING_TRANSACTION_EXPIRY_HOURS`.
- Pada boundary `created_at <= now() - 24 jam`, transaction dapat berubah menjadi `expired` dan tidak dapat dibayar.
- Expiry bersifat umum untuk checkout POS, bukan QR/provider expiry.

### State dan refund

- Cash dengan stok gagal: transaction `failed`, tidak ada payment/refund.
- Manual non-tunai yang sudah diterima tetapi stok gagal: transaction/payment `refund_required`, tidak ada sale movement.
- Void dan return mengubah payment `paid` menjadi `refund_required`.
- `refunded_amount` adalah nilai kumulatif aktual.
- `partially_refunded` berarti payment belum direfund sebesar full amount; kewajiban terbuka ditentukan oleh `refund_due_amount = max(0, refund_obligation_amount - refunded_amount)`.
- Transaction `refund_required` tetap menjadi historical outcome setelah refund diselesaikan.

### Histori dan printing

- Histori, receipt, queued PDF/XLSX, dan laporan Owner menampilkan metode pembayaran.
- Laporan Owner dapat merangkum jumlah payment, amount, refund tercatat, dan net operasional per metode.
- Staff tetap tidak boleh mengakses financial report/export pada Fase 6.
- Web Bluetooth adalah beta, default nonaktif melalui `POS_BLUETOOTH_PRINT_ENABLED=false`.
- Print dialog/Save as PDF adalah jalur resmi sampai hardware tervalidasi.

### Removed POS scope

- Midtrans POS adapter, credential, QR generation, provider metadata, webhook, signature verification, polling, provider reconciliation, dan sandbox gate.
- Monitoring webhook POS pada fase downstream.

### Preserved billing scope

- Midtrans billing Fase 11.
- Billing webhook, signature, duplicate/out-of-order handling, dan observability billing.

## Migration impact

- Migration additive menambahkan `transfer` pada payment method.
- Menambahkan `confirmed_by`, `manual_reference`, dan `confirmation_note` pada `pos_payments`.
- Menambahkan index pencarian payment tenant/transaction/method/status.
- Menambahkan movement type `sale_void`.
- Migration Fase 0–5 tidak diubah.

## API impact

- Menghapus endpoint POS yang baru direncanakan tetapi belum diimplementasikan: QR generate dan Midtrans POS webhook.
- Menambahkan `POST /api/v1/pos/transactions/{id}/pay-manual` dengan `Idempotency-Key`.
- Menambahkan endpoint void, return, dan mark-refunded sesuai kontrak yang sudah ada.
- Memperluas status response dengan lifecycle payment, confirmation metadata, obligation, refunded, dan due amount.
- Perubahan additive tetap pada `/api/v1`.

## Backward compatibility

- Cash POS Fase 2 tetap kompatibel.
- Record cash lama tetap valid; metadata manual confirmation nullable.
- Tidak ada provider/QR record yang perlu dimigrasikan.
- Billing Fase 11 tidak berubah.
- Feature Bluetooth baru default nonaktif.

## Test impact

- Hapus release gate Midtrans POS/webhook.
- Tambahkan manual confirmation, cross-transaction idempotency, unique-key race, TTL expiry, cash-vs-manual concurrency, void, return, refund due, history/export/receipt, dan print fallback.
- Billing webhook tests tetap wajib ketika Fase 11 diimplementasikan.

