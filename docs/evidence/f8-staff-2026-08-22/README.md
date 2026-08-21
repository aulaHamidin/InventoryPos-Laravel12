# Evidence Fase 8 — Staff & Multi-Kasir

Tanggal walkthrough: 2026-08-22 (`Asia/Jakarta`)

Environment: aplikasi lokal Docker Compose di `http://localhost:8000`, MySQL, Redis, queue, dan scheduler aktif. Seluruh identitas pada screenshot adalah data demo sintetis. Tidak ada password, token, email/nomor nyata, atau QRIS produksi yang direkam.

## Desktop 1440×900

| File | Bukti |
|---|---|
| `login-desktop-1440x900.png` | Form login tenant tanpa credential terisi. |
| `owner-staff-management-desktop-1440x900.png` | Owner melihat daftar Staff tenant dan workflow tambah/edit. |
| `owner-reset-access-desktop-1440x900.png` | Dialog reset akses dengan password dan konfirmasi kosong. |
| `owner-deactivate-confirmation-desktop-1440x900.png` | Confirmation gate untuk deactivation Staff. |
| `staff-dashboard-desktop-1440x900.png` | Dashboard operasional Staff: low stock dan insight analytics; widget finansial/pembelian tidak tampil. |
| `staff-items-readonly-desktop-1440x900.png` | Projection Barang read-only dengan harga jual/stok/class, tanpa purchase cost atau mutation. |
| `staff-pos-cash-desktop-1440x900.png` | POS tunai dengan diskon baris dan total authoritative Rp14.000. |
| `staff-pos-qris-desktop-1440x900.png` | QRIS statis manual dengan warning dan confirmation checkbox. |
| `staff-pos-transfer-desktop-1440x900.png` | Transfer bank manual menggunakan guard konfirmasi yang sama. |
| `staff-own-history-desktop-1440x900.png` | Histori hanya transaksi Staff demo, mencakup cash/QRIS/transfer. |
| `staff-unauthorized-desktop-1440x900.png` | Direct URL Staff management menghasilkan HTTP 403. |

## Mobile 390×844

| File | Bukti |
|---|---|
| `login-mobile-390x844.png` | Login responsif tanpa overflow horizontal. |
| `staff-dashboard-mobile-390x844.png` | Dashboard Staff tersusun vertikal pada viewport mobile. |
| `staff-menu-mobile-390x844.png` | Menu hanya Dashboard, POS, Barang, Supplier, dan transaksi sendiri. |
| `staff-pos-cart-mobile-390x844.png` | Scanner/search dan cart POS responsif dengan bottom navigation aman. |
| `staff-pos-qris-mobile-390x844.png` | Modal QRIS responsif, warning dan konfirmasi terlihat utuh. |
| `staff-own-transaction-mobile-390x844.png` | Detail transaksi milik kasir sendiri tanpa mutation refund/void/return. |

## Catatan verifikasi

- Owner-only menu muncul pada walkthrough Owner dan hilang pada walkthrough Staff.
- Navbar POS kustom Staff tidak menampilkan Riwayat Stok; slot tersebut mengarah ke transaksi sendiri.
- Diskon baris di-commit saat field kehilangan fokus, lalu total cart dan modal pembayaran sama-sama menampilkan Rp14.000.
- Screenshot modal tidak mengeksekusi transaksi baru. Tiga transaksi demo yang tampil di histori dibuat melalui backend lokal dengan actor Staff dan key idempotency sintetis.
- Reset/deactivate hanya dibuka sampai confirmation dialog; behavior mutation, revocation, audit, dan safe no-op dibuktikan automated test.
