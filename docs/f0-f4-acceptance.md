# Acceptance Gate Fase 0–4

Dokumen ini mencatat bukti gate setelah repair Fase 0–4. Checklist fase pada `implementation_plan.md` hanya boleh diaktifkan setelah seluruh bagian Automated, Runtime, Visual, dan CI lulus.

## Automated gate

- [x] Composer manifest valid dan platform requirement PHP 8.3 terpenuhi.
- [x] Migration Fase 0–4 dapat dibangun ulang pada database MySQL `testing`.
- [x] Seeder hanya membuat tenant demo, Owner, dan Staff untuk pengujian kontrak akses.
- [x] Pest mencakup tenant isolation, ownership, concurrency, inventory, POS, reports, dan Shopping List.
- [x] Laravel Pint lulus untuk seluruh source.
- [x] Vite/Tailwind 4 application build lulus tanpa warning.
- [x] Filament/Tailwind 3 custom theme build lulus tanpa warning.
- [x] Custom Filament theme dan Blade mengonsumsi `resources/css/design-tokens.css` yang sama.
- [x] POS receipt dan report surfaces memiliki print action.
- [x] Seluruh private API `/api/v1` memakai Sanctum lalu `SetTenantContext`.

## Runtime Sail

- [x] Image `sail-8.3/app` selesai dibangun dengan Node.js 20.
- [x] Container `laravel.test`, `queue`, MySQL, Redis, dan Mailpit berstatus running/healthy.
- [x] `sail php -v` menghasilkan PHP 8.3.x.
- [x] `sail node -v` menghasilkan Node.js 20.x.
- [x] `sail artisan migrate:status` berhasil.
- [x] `/app/login` tenant dan `/admin/login` platform menghasilkan HTTP 200; custom theme tenant termuat.

## Visual walkthrough

### Owner — desktop

- [x] Login dan dashboard dapat dibaca tanpa layout rusak.
- [x] Master data, item, supplier, dan stock movement dapat dinavigasi.
- [x] Stok hanya berubah melalui Stock In/Adjustment workflow.
- [x] POS scanner/search, cart, line discount, payment, receipt, dan Print dapat digunakan.
- [x] Report stok, movement, dan POS memiliki filter, Export, Print, serta progress download.
- [x] Shopping List dapat generate, submit, dan receive.

### Owner — mobile

- [x] Navigation dan POS tidak overflow secara horizontal.
- [x] Target tombol utama dapat disentuh dan modal pembayaran tetap usable.
- [x] Camera scanner menampilkan permission/fallback yang jelas.
- [x] Receipt dan Shopping List receive tetap terbaca.

### Staff contract

- [x] Login Staff ditolak tanpa mengungkap keberadaan akun dan tanpa membentuk sesi panel.
- [x] Staff tidak dapat membuka POS, report financial, atau private export Fase 0–4.
- [x] Tidak ada purchase cost, average cost, margin, profit, atau inventory value yang bocor.

## CI

- [x] Workflow GitHub Actions memakai PHP 8.3, Node 20, MySQL 8.4, dan Redis.
- [x] Workflow menjalankan Composer validation, platform check, build, fresh migration/seed, Pest, dan Pint.
- [x] Repository GitHub tersedia dan branch `main` sudah dipush.
- [x] Satu run workflow GitHub Actions berhasil.

## Manual test notes

Catat tanggal, browser/device, actor, hasil, dan screenshot/evidence untuk setiap walkthrough visual. Jangan mencentang gate berdasarkan HTTP test saja.

| Tanggal | Browser/device | Actor | Hasil | Evidence |
| --- | --- | --- | --- | --- |
| 2026-08-15 | Codex In-app Browser, desktop 1440×900 | Owner Demo | Dashboard, navigasi master data, Stock In/Adjustment, POS, laporan, export, dan Shopping List lulus. | [`01`–`06`](evidence/f0-f4-visual-2026-08-15/) |
| 2026-08-15 | Codex In-app Browser, mobile 390×844 | Owner Demo | POS dan receive tidak overflow; tombol, modal, fallback kamera, receipt, dan receive tetap usable. | [`07`–`11`](evidence/f0-f4-visual-2026-08-15/) |
| 2026-08-15 | Codex In-app Browser, desktop 1440×900 | Staff Visual | Login UI ditolak dengan pesan kredensial generik, bukan HTTP 403. Request Staff autentik ke panel/POS/laporan/export/download seluruhnya 403 dan tidak membocorkan data finansial. | [`12`–`13`](evidence/f0-f4-visual-2026-08-15/) |

## Evidence — 2026-08-15

- Image Sail: `sail-8.3/app` (`a36809295b86`), PHP `8.3.33`, Node.js `20.20.2`.
- Stack: `laravel.test` dan `queue` running; MySQL 8.4, Redis, dan Mailpit healthy.
- Worker: `php artisan queue:work redis --queue=exports,default` aktif di service `queue`.
- Container gate: fresh migration/seed database MySQL `testing` lulus; 44 test dan 202 assertion lulus dari dalam Sail.
- HTTP gate terbaru: `/app/login` 200 (43191 byte), `/admin/login` 200 (43209 byte), dan `/api/v1/items` tanpa token menghasilkan 401.
- Browser in-app tersedia dan walkthrough visual diulang setelah `SetTenantContext` dijadikan persistent middleware Livewire.
- Regression check setelah pemisahan panel: seluruh 47 test dan 217 assertion lulus; Pint memeriksa 173 file.
- Fixture visual: kategori `Walkthrough Visual`, rak `Rak Walkthrough`, supplier `Supplier Walkthrough`, dan item `VIS-0001 / Barang Walkthrough`.
- Inventory: item dibuat dengan stok `0`; `Stok Masuk` menambah `10`, `Sesuaikan` mengurangi `6`, POS mengurangi stok melalui movement `sale`, dan receive Shopping List menambah stok melalui movement penerimaan. Form edit item tidak menyediakan field stok langsung.
- POS desktop: pencarian `Barang Walkthrough`, cart, validasi/clamp diskon baris, modal pembayaran tunai, transaksi `POS-1-20260815-01M01NFTHVJ9FKGYTE7JAX3JCE`, receipt, dan aksi `Cetak Struk` lulus.
- Reports: filter stok rendah, movement `Penjualan`, dan status POS `Selesai` lulus. Export Stok PDF, Pergerakan XLSX, dan POS PDF selesai `completed / 100%`; link `Unduh` tersedia dan dapat dipanggil; aksi `Cetak` tidak menghasilkan error aplikasi.
- Shopping List `#2`: generate otomatis menghasilkan `2` item, submit mengikat supplier dan qty, receive mobile menyelesaikan penerimaan, lalu status berubah `Selesai` dan stok diperbarui.
- Mobile POS `390×844`: `scrollWidth = clientWidth = 390`; target kamera `87×61`, navigasi bawah `78×63`, tombol modal `145×48`, dan tombol receipt `145×48` piksel. Fallback kamera memunculkan JavaScript alert dan mengembalikan fokus ke input barcode.
- Staff: login `staff.visual@demo.com` ditolak dengan pesan kredensial generik tanpa membentuk sesi. Setelah sesi uji didemote ke role Staff, seluruh panel tenant, POS, laporan, status export, dan download privat menghasilkan 403 tanpa purchase cost, average cost, margin, profit, atau inventory value. Kontrak ini diterima untuk Fase 0–4 karena Staff baru diaktifkan pada Fase 8.
- Panel dipisahkan setelah walkthrough: tenant memakai `/app` dengan guard `web` dan `SetTenantContext`; platform memakai `/admin` dengan guard `admin`, tanpa resource tenant. Route dan isolasi kedua guard diverifikasi otomatis.
- Screenshot walkthrough tersimpan di [`docs/evidence/f0-f4-visual-2026-08-15`](evidence/f0-f4-visual-2026-08-15/).
- Repository GitHub `aulaHamidin/InventoryPos-Laravel12` tersedia sebagai repository publik; refactor panel dan penutupan Fase 0–4 dipush pada commit `a8395c6`.
- GitHub Actions run `31865968011` untuk commit `a8395c6` selesai dengan status `success` pada 2026-08-15 WIB.
