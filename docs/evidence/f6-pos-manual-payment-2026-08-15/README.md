# Evidence Fase 6 — POS Lengkap & Pembayaran Manual Non-Tunai

Tanggal walkthrough: 15 Agustus 2026

## Automated

- Fresh migration + seed, rollback migration F6, dan upgrade kembali dari baseline F5: lulus.
- Full PHP suite: **84 test / 474 assertion**, seluruhnya lulus.
- F6 targeted: **13 test / 119 assertion** setelah hardening cumulative refund, seluruhnya lulus.
- Multi-process suite: **14 test / 72 assertion**, termasuk duplicate manual key, cash-vs-manual, different manual keys, expiry-vs-payment, void-vs-return, dan return-vs-return.
- Node printing suite: **5 test**, mencakup formatter cash/QRIS/transfer, ESC/POS encoding, chunking, profile Nordic UART/HM-10/18F0, success, permission denial, disconnect, write failure, mismatch, dan unsupported browser.
- Formula-injection regression membuktikan manual reference ditulis sebagai explicit string pada XLSX.
- Stored-XSS, cross-tenant 404, Staff denial, TTL boundary, negative stock, idempotency conflict, exact rounding, obligation/refunded/due, notification after commit, dan immutable audit/movement tercakup automated tests.
- `npm test`, `npm run build`, Pint, Composer validation/platform check, view cache, route list, dan schedule list lulus.
- GitHub Actions implementation run [`31896657112`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/31896657112): `success`.

## Visual screenshot index

Walkthrough dijalankan sebagai Owner Demo pada aplikasi lokal. Data yang terlihat adalah data demo, tanpa credential atau token.

- `desktop-cash-payment-1440x900.png` — modal cash, backend total, uang diterima, dan kembalian.
- `desktop-qris-confirmation-1440x900.png` — QRIS statis, warning manual verification, reference/note, dan checkbox konfirmasi.
- `desktop-qris-receipt-1440x900.png` — receipt QRIS dengan label metode, “Dikonfirmasi Manual”, dan reference.
- `mobile-qris-receipt-390x844.png` — receipt QRIS responsif serta tombol print dialog/Save as PDF.
- `mobile-transfer-confirmation-390x844.png` — pilihan transfer dan manual-verification warning pada mobile.
- `desktop-history-method-summary-1440x900.png` — summary operasional per metode serta kolom transaction/payment terpisah.
- `desktop-history-payment-filter-1440x900.png` — filter Cash/QRIS Statis/Transfer.
- `desktop-transaction-detail-refund-actions-1440x900.png` — confirmer, reference, note, due, audit, void, return, dan print action.
- `desktop-return-refund-required-1440x900.png` — transaction Retur Penuh dengan payment Perlu Refund dan action Catat Refund.
- `desktop-refund-complete-1440x900.png` — recorded refund selesai; transaction tetap menjadi historical return outcome.
- `desktop-expired-unpaid-history-1440x900.png` — checkout 25 jam menjadi Kedaluwarsa dan Belum Dibayar.
- `desktop-export-payment-method-1440x900.png` — queued PDF/XLSX export dengan filter metode.

Browser console setelah walkthrough: tidak ada error.

## Printing gate

- `POS_BLUETOOTH_PRINT_ENABLED=false` pada default environment.
- Tombol Bluetooth tidak tampil pada walkthrough.
- Print dialog/Save as PDF menjadi metode printing resmi dan kegagalannya tidak mengubah state transaction/payment.
- Hardware printer belum tersedia dan belum divalidasi. Fase 6 tidak mengklaim kompatibilitas dengan model printer tertentu.
- Aktivasi flag pada pilot/production tetap memerlukan hardware validation record terpisah.

## Edge-state coverage

State stock-conflict setelah dana manual diterima, exact cumulative return lintas beberapa request, race condition, void semua metode, dan Staff denial dibuktikan melalui automated tests karena membutuhkan manipulasi waktu/stok atau proses paralel yang tidak aman direkayasa pada walkthrough UI biasa.
