# Inventori-Q

Inventori-Q adalah aplikasi SaaS multi-tenant untuk manajemen stok, POS tunai, laporan, dan Shopping List. Implementasi saat ini mencakup Fase 0–4 dari kontrak produk.

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

Aplikasi tersedia di `http://127.0.0.1:8000` dan Mailpit di `http://127.0.0.1:8025`.

Jika UID/GID Linux bukan `1000`, sesuaikan `WWWUSER` dan `WWWGROUP` di `.env` sebelum membangun image.
`NODE_VERSION=20` dipertahankan sebagai build argument Sail agar container sama dengan runtime Node.js kontraktual.

## Akun demo

- Email: `owner@demo.com`
- Password: `password`
- Role: Owner

Akun ini hanya untuk development. Ganti atau hapus credential demo sebelum deployment production.

## Development melalui WSL host

MySQL, Redis, dan Mailpit dapat dijalankan terpisah melalui Docker, kemudian jalankan:

```bash
composer run dev
```

Pada mode ini `.env` memakai hostname `127.0.0.1`. Container `laravel.test` mendapat override hostname `mysql`, `redis`, dan `mailpit` dari `compose.yaml`.

## Queue

Export PDF/XLSX berjalan di queue `exports`. `docker compose up -d` otomatis menyalakan service `queue` untuk queue `exports,default`. Untuk development langsung melalui host WSL, jalankan:

```bash
php artisan queue:work redis --queue=exports,default --tries=3 --timeout=120
```

File export disimpan pada disk private Laravel dan hanya dapat diunduh melalui route yang melewati policy serta tenant ownership guard.

## Quality gate

```bash
composer validate --strict
composer check-platform-reqs
APP_ENV=testing DB_DATABASE=testing php artisan migrate:fresh --seed --force
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list -v --path=api/v1
```

`npm run build` menghasilkan dua asset terpisah:

- aplikasi Blade/Livewire dengan Tailwind 4;
- custom theme Filament 3 dengan compiler Tailwind 3 yang terisolasi.

Keduanya mengimpor `resources/css/design-tokens.css` sebagai sumber semantic token yang sama.

## Struktur fitur Fase 0–4

- Fase 0: tenant context, auth Sanctum/session, policy, ownership guard, audit, design system, CI.
- Fase 1: master data, item supplier, stock movement immutable, MAC, stock in/out/adjustment.
- Fase 2: POS tunai, scanner, diskon baris, idempotency, payment, receipt/print.
- Fase 3: laporan stok/pergerakan/POS dan queued PDF/XLSX export.
- Fase 4: low-stock widget serta Shopping List generate/submit/receive.

Fase 5 dan seterusnya belum boleh diimplementasikan sebelum seluruh repair gate Fase 0–4 dinyatakan lulus di `saas-contract-final-v1/implementation_plan.md`.

## Dokumen kontrak

Source of truth berada di folder `saas-contract-final-v1/`. Urutan otoritas dan aturan enforcement dijelaskan dalam `.agents/AGENTS.md`.

Checklist dan bukti penutupan gate Fase 0–4 dicatat di `docs/f0-f4-acceptance.md`.
