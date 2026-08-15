# Document Delta Declaration — Pemisahan Panel Tenant dan Platform

Tanggal persetujuan: 2026-08-15

## Affected documents

- `saas-contract-final-v1/software-architecture-document.md`
- `saas-contract-final-v1/ui-ux-specification.md`
- `saas-contract-final-v1/development-roadmap.md`
- `saas-contract-final-v1/implementation_plan.md`
- `docs/f0-f4-acceptance.md`

## Reason

Satu panel `/admin` sebelumnya memakai model `User` tenant, sementara tabel/model `Admin` platform sudah terpisah. Penamaan tersebut mencampur boundary identitas Owner/Staff dengan Super Admin/Support dan akan membuat fitur admin pusat Fase 10 berisiko mengekspos resource tenant.

## Current contract

- Owner dan Staff/Kasir adalah identity tenant dengan `tenant_id`.
- Super Admin dan Support adalah identity platform tanpa `tenant_id`.
- Fase 0 menyediakan fondasi auth; fitur admin pusat dan billing diselesaikan pada Fase 10.

## Proposed contract

- `/app/login` dan `/app/*` memakai guard `web`, model `User`, serta `SetTenantContext`.
- `/admin/login` dan `/admin/*` memakai guard `admin` serta model `Admin`; resource tenant tidak didiscover.
- Super-admin awal dibuat melalui command interaktif `php artisan admin:create`, bukan seeder/default password.
- Fase 0 hanya menyediakan shell platform dan pemisahan identity boundary; tenant/subscription/billing/support UI tetap Fase 10.
- Staff belum memperoleh sesi panel pada Fase 0–4. Penolakan login memakai pesan generik; request Staff autentik ke fitur terlarang tetap 403.

## Migration impact

Tidak ada perubahan schema. Tabel `users` dan `admins` yang sudah ada tetap digunakan. Perubahan hanya pada guard, panel provider, route web tenant, navigasi, tes, dan dokumentasi.

## Backward compatibility

URL tenant berubah dari `/admin/*`, `/pos`, dan `/reports/*` menjadi `/app/*`. API `/api/v1/*` tidak berubah. `/admin/*` kini secara eksklusif merupakan platform surface.

## Test impact

- Uji isolasi guard `web` dan `admin`.
- Uji Owner dapat membuka `/app` dan tidak mengautentikasi `/admin`.
- Uji Admin dapat membuka `/admin`, tidak mengautentikasi `/app`, dan tidak menemukan resource tenant.
- Uji command provisioning super-admin dan password hashing.
- Seluruh kontrak tenant isolation, Staff 403, POS, report, export, dan Shopping List tetap wajib lulus.
