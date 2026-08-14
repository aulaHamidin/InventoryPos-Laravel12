# Acceptance Gate Fase 0–4

Dokumen ini mencatat bukti gate setelah repair Fase 0–4. Checklist fase pada `implementation_plan.md` hanya boleh diaktifkan setelah seluruh bagian Automated, Runtime, Visual, dan CI lulus.

## Automated gate

- [x] Composer manifest valid dan platform requirement PHP 8.3 terpenuhi.
- [x] Migration Fase 0–4 dapat dibangun ulang pada database MySQL `testing`.
- [x] Seeder hanya membuat tenant demo dan Owner.
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
- [x] `/admin/login` dan custom theme menghasilkan HTTP 200 dari container.

## Visual walkthrough

### Owner — desktop

- [ ] Login dan dashboard dapat dibaca tanpa layout rusak.
- [ ] Master data, item, supplier, dan stock movement dapat dinavigasi.
- [ ] Stok hanya berubah melalui Stock In/Adjustment workflow.
- [ ] POS scanner/search, cart, line discount, payment, receipt, dan Print dapat digunakan.
- [ ] Report stok, movement, dan POS memiliki filter, Export, Print, serta progress download.
- [ ] Shopping List dapat generate, submit, dan receive.

### Owner — mobile

- [ ] Navigation dan POS tidak overflow secara horizontal.
- [ ] Target tombol utama dapat disentuh dan modal pembayaran tetap usable.
- [ ] Camera scanner menampilkan permission/fallback yang jelas.
- [ ] Receipt dan Shopping List receive tetap terbaca.

### Staff contract

- [ ] Login Staff ke tenant panel ditolak 403.
- [ ] Staff tidak dapat membuka POS, report financial, atau private export Fase 0–4.
- [ ] Tidak ada purchase cost, average cost, margin, profit, atau inventory value yang bocor.

## CI

- [x] Workflow GitHub Actions memakai PHP 8.3, Node 20, MySQL 8.4, dan Redis.
- [x] Workflow menjalankan Composer validation, platform check, build, fresh migration/seed, Pest, dan Pint.
- [x] Repository GitHub tersedia dan branch `main` sudah dipush.
- [ ] Satu run workflow GitHub Actions berhasil.

## Manual test notes

Catat tanggal, browser/device, actor, hasil, dan screenshot/evidence untuk setiap walkthrough visual. Jangan mencentang gate berdasarkan HTTP test saja.

## Evidence — 2026-08-15

- Image Sail: `sail-8.3/app` (`a36809295b86`), PHP `8.3.33`, Node.js `20.20.2`.
- Stack: `laravel.test` dan `queue` running; MySQL 8.4, Redis, dan Mailpit healthy.
- Worker: `php artisan queue:work redis --queue=exports,default` aktif di service `queue`.
- Container gate: fresh migration/seed database MySQL `testing` lulus; 44 test dan 202 assertion lulus dari dalam Sail.
- HTTP gate: `/admin/login` 200; custom theme 200 (109890 byte), semantic Indigo token dan print CSS terdeteksi; `/api/v1/items` tanpa token menghasilkan 401.
- Browser in-app tidak tersedia pada sesi ini (`No browser is available`), sehingga seluruh checkbox Visual walkthrough tetap terbuka.
- Repository GitHub `aulaHamidin/InventoryPos-Laravel12` tersedia; branch `main` telah dipush pada commit `204c0fa` dan memicu workflow. Bukti hasil satu run CI remote tetap terbuka karena repository privat belum dapat diperiksa tanpa sesi GitHub terautentikasi.
