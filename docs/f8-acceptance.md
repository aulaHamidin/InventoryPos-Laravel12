# Fase 8 Acceptance — Staff & Multi-Kasir

Status: **FASE 8 SELESAI — DEPLOYMENT DITUNDA KE RELEASE V1**

Penutupan CI: 2026-08-22 (`Asia/Jakarta`)

## Baseline dan kontrak

- Baseline Fase 7: `ad07521fbdf81ccf5a3fe9185fecac5eb96fa01e` pada `main`.
- Merge Fase 8 ke `main`: `98e1d8775265ab7e00e8cd29c2f7fd8148aabf98`; SHA ini menjadi baseline keputusan CD-9.1. Setelah CD-9.1 disahkan, baseline resmi implementasi F9A berpindah ke merge `ac6b7bf7ab630ba061b69a37a816804152b7695b`.
- Document Delta: `saas-contract-final-v1/document-delta-f8-staff-multi-cashier.md`.
- Implementation plan: `saas-contract-final-v1/implementation-plan-fase-8.md` dengan status **DISETUJUI UNTUK IMPLEMENTASI**.
- Commit kontrak F8: `0550bc1`.
- Commit implementasi inti: `840b61ba2aa6117ed9139883d35be2daa002e287`.
- SHA implementasi/evidence final: `008eab265f3f9a7550932990178e34d7af560ec8` pada branch `codex/fase-8-staff-multi-cashier`.
- CI remote final implementasi: workflow `CI` run `32509889724`; job `test`, `staff-runtime`, dan `analytics-runtime` sukses.

## Gate yang lulus

- Migration additive `users.is_active`, `users.auth_version`, dan index tenant/role/status/id; fresh, upgrade dari baseline F7, serta rollback security-lossy pada database terpisah.
- Lifecycle Owner-only: create, update profil, reset akses, activate/deactivate safe no-op, password minimal 12 karakter, global unique email/no HP, target Staff tenant sendiri, dan audit canonical tanpa credential.
- Web/API access: provider menolak akun nonaktif, session version diperiksa sebelum tenant context, token direvoke, sesi lama tidak pulih setelah activate, dan pre-F8 session tanpa version dipaksa login ulang.
- Authorization defense-in-depth: Policy, controller, middleware, dan Action memeriksa actor persisten; record kasir/tenant lain 404 dan capability terlarang pada record sendiri 403.
- Staff projection: Item, ItemSupplier, Livewire POS, HTML, dan JSON tidak membawa purchase cost, average cost, margin, valuation, profit, tenant/config internal, report/export, atau mutation Owner.
- POS Staff: cash, QRIS statis, transfer, diskon baris, actor/cashier server-side, own-history scope, idempotency unique per tenant, cross-cashier conflict generik, dan manual canonical comparison termasuk `confirmed_by`.
- Concurrency multi-process: dua Staff membayar stok terakhir; tepat satu berhasil, stok tidak negatif, dan tidak ada payment/movement/actor tertukar.
- Redis runtime: session/token revocation untuk deactivate dan reset, activation tidak memulihkan akses lama, serta regresi analytics queue/lock F7 tetap hijau.
- UI: Owner management Staff, dashboard/menu operasional Staff, Barang/Supplier read-only, tiga metode pembayaran, histori sendiri, dan unauthorized state.
- Walkthrough memperbaiki dua gap UI sebelum penutupan: navbar POS Staff kini menuju transaksi sendiri (bukan Riwayat Stok), dan diskon cart di-commit pada blur sebelum total/modal authoritative dihitung.

## Hasil test dan quality gate

| Gate | Hasil |
|---|---:|
| PHPUnit/Pest cepat | 111 passed, 730 assertions; 5 runtime tests sengaja skipped pada suite cepat |
| Redis Staff runtime | 2 passed, 27 assertions |
| Redis analytics runtime | 3 passed, 25 assertions |
| Node test | 5 passed |
| Migration F8 upgrade/rollback | Passed |
| Composer validate/platform, npm build, Pint, Blade cache, route/schedule list | Passed |
| CI remote | 3/3 job sukses |

## Evidence visual

Index dan 17 screenshot desktop 1440×900/mobile 390×844 berada di `docs/evidence/f8-staff-2026-08-22/`.

Evidence memakai identitas demo sintetis dan tidak memuat password, token, email/nomor nyata, atau QRIS produksi.

## Checklist penutupan dan handoff F9A

- [x] Document Delta dan seluruh source of truth F8 sinkron.
- [x] Migration/lifecycle/session-token revocation lulus, termasuk Redis runtime.
- [x] Permission/projection matrix lulus tanpa financial leakage.
- [x] POS cash/QRIS/transfer, diskon, idempotency tenant, dan actor scope lulus.
- [x] Concurrency multi-kasir lulus tanpa stok negatif atau duplicate state.
- [x] Evidence Owner/Staff desktop/mobile lengkap.
- [x] Full local gate, `staff-runtime`, `analytics-runtime`, dan CI remote hijau.
- [x] Commit implementasi/evidence dan SHA final tercatat.
- [x] Fase 8 selesai; perencanaan F9A boleh dimulai sesuai CD-9.1.

## Rollback dan deployment release v1 yang ditunda

- Rollback migration F8 bersifat security-lossy: status nonaktif dan revocation version hilang. Rollback hanya boleh dilakukan dalam maintenance mode dengan backup.
- Deployment F8 ke environment target ditunda sampai release v1. Saat release, migration, Redis session/cache/queue, worker, scheduler, health, dan revocation smoke test wajib diverifikasi.
- Deployment/backfill F7 tetap wajib dijalankan dan lulus `analytics:status --fail-on-incomplete` sebelum release v1 dinyatakan siap.

Keputusan: Fase 8 selesai untuk implementasi dan CI. CD-9.1 kemudian memisahkan F9A/F9B; deployment F7/F8 bukan blocker perencanaan F9A, tetapi tetap wajib diverifikasi pada F9B dan gate release v1.
