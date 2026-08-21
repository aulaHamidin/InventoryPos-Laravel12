# Inventori-Q

Inventori-Q adalah aplikasi SaaS multi-tenant untuk manajemen stok, POS, laporan, cycle counting, Shopping List, serta Analytics & Smart Threshold. Implementasi aktif mencakup Fase 0–7 dari kontrak produk.

## Runtime

- PHP 8.3
- Laravel 12
- Filament 3
- Livewire 3
- MySQL 8.4
- Redis
- Node.js 20 LTS
- Laravel Sail

## Menjalankan dengan Sail

```bash
cp .env.example .env
composer install
npm ci
npm run build
./vendor/bin/sail build laravel.test
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
```

Aplikasi tenant tersedia di `http://127.0.0.1:8000/app`, panel platform di `http://127.0.0.1:8000/admin`, dan Mailpit di `http://127.0.0.1:8025`.

Jika UID/GID Linux bukan `1000`, sesuaikan `WWWUSER` dan `WWWGROUP` di `.env` sebelum membangun image.
`NODE_VERSION=20` dipertahankan sebagai build argument Sail agar container sama dengan runtime Node.js kontraktual.

## Akun tenant demo

- Owner: `owner@demo.com` / `password`
- Staff kontrak: `staff@demo.com` / `password`

Keduanya hanya untuk development. Staff belum boleh masuk panel pada Fase 0–4. Ganti atau hapus credential demo sebelum deployment production.

Super-admin tidak pernah dibuat oleh seeder. Provisioning awal dilakukan secara interaktif:

```bash
php artisan admin:create --name="Platform Admin" --email="admin@example.com"
```

Command meminta password minimal 12 karakter secara tersembunyi. Fitur pengelolaan tenant, subscription, dan billing pada panel platform tetap diselesaikan pada Fase 10.

## Development melalui WSL host

MySQL, Redis, dan Mailpit dapat dijalankan terpisah melalui Docker, kemudian jalankan:

```bash
composer run dev
```

Pada mode ini `.env` memakai hostname `127.0.0.1`. Container `laravel.test` mendapat override hostname `mysql`, `redis`, dan `mailpit` dari `compose.yaml`.

## Queue

Export PDF/XLSX berjalan di queue `exports`; recalculation analytics berjalan di queue `analytics`. `docker compose up -d` menyalakan service `queue` untuk queue `exports,analytics,default` dan service `scheduler` untuk sweep terjadwal. Untuk development langsung melalui host WSL, jalankan:

```bash
php artisan queue:work redis --queue=exports,analytics,default --tries=3 --timeout=120
```

File export disimpan pada disk private Laravel dan hanya dapat diunduh melalui route yang melewati policy serta tenant ownership guard.

Runtime production wajib memakai Redis (atau distributed-lock store yang disetujui) untuk cache dan queue. Scheduler menjalankan sweep analytics pukul `00:15 Asia/Jakarta`. Setelah deployment F7, jalankan:

```bash
php artisan analytics:recalculate
php artisan analytics:status --fail-on-incomplete
php artisan queue:failed
# Setelah akar masalah diperbaiki: php artisan queue:retry <uuid>
php artisan schedule:list
```

## Quality gate

```bash
composer validate --strict
composer check-platform-reqs
APP_ENV=testing DB_DATABASE=testing php artisan migrate:fresh --seed --force
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list -v --path=api/v1
php artisan schedule:list
```

`npm run build` menghasilkan dua asset terpisah:

- aplikasi Blade/Livewire dengan Tailwind 4;
- custom theme Filament 3 dengan compiler Tailwind 3 yang terisolasi.

Keduanya mengimpor `resources/css/design-tokens.css` sebagai sumber semantic token yang sama.

## Struktur fitur Fase 0–7

- Fase 0: tenant context, auth Sanctum/session, policy, ownership guard, audit, design system, CI.
- Fase 1: master data, item supplier, stock movement immutable, MAC, stock in/out/adjustment.
- Fase 2: POS tunai, scanner, diskon baris, idempotency, payment, receipt/print.
- Fase 3: laporan stok/pergerakan/POS dan queued PDF/XLSX export.
- Fase 4: low-stock widget serta Shopping List generate/submit/receive.
- Fase 5: cycle counting/stock opname dengan immutable adjustment ledger.
- Fase 6: pembayaran manual, expiry, void, return, dan refund POS.
- Fase 7: analytics movement class, Smart Threshold, trigger queue, daily sweep, serta pengaturan dead stock Owner.

Fase berikutnya hanya boleh dimulai setelah gate fase aktif dinyatakan lulus di `saas-contract-final-v1/master-plan-fase-5-12.md`.

## Dokumen kontrak

Source of truth berada di folder `saas-contract-final-v1/`. Urutan otoritas dan aturan enforcement dijelaskan dalam `.agents/AGENTS.md`.

Checklist dan bukti penutupan gate Fase 0–4 dicatat di `docs/f0-f4-acceptance.md`.
