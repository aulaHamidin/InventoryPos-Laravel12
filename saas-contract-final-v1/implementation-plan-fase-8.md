# Implementation Plan Fase 8 — Staff & Multi-Kasir

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Baseline: `ad07521fbdf81ccf5a3fe9185fecac5eb96fa01e`; Fase 7 telah masuk `main`. Deployment/backfill Fase 7 tetap menjadi gate release v1, bukan blocker Fase 8.

Referensi utama:

1. `prd-saas-manajemen-stok.md` §4.1–4.2.
2. `blueprint-saas-stok.md` §4.
3. `software-architecture-document.md` §17.
4. `ui-ux-specification.md` §11, §12.2, dan §40.
5. `development-roadmap.md` §10.
6. `master-plan-fase-5-12.md` §8.

## 1. Sasaran dan batas fase

Fase ini mengaktifkan user dengan role `staff` yang sudah ada pada tenant panel `/app`, menyediakan pengelolaan Staff yang tenant-scoped bagi Owner, serta membuka POS multi-kasir tanpa mengubah invariant stok, payment, tenant isolation, ataupun audit ledger yang sudah ditutup pada Fase 1–7.

Hasil yang harus tersedia:

- Staff aktif dapat login melalui `/app/login` dengan guard `web`; kredensial Staff tidak pernah valid pada `/admin/login`.
- Owner dapat membuat, mengubah data non-role, mereset akses, mengaktifkan, dan menonaktifkan Staff tenantnya sendiri.
- Staff dapat menjalankan checkout dan pembayaran POS `cash`, `qris`, dan `transfer`; `cashier_id` selalu berasal dari actor terautentikasi dan `confirmed_by` pada pembayaran manual menunjuk actor tersebut.
- Staff hanya melihat data operasional yang diizinkan: harga jual, stok/low-stock, transaksi sesuai scope, supplier read-only, dan insight analytics non-finansial.
- Semua surface terlarang—resource Filament, Livewire, API, HTML, export, print, notification, job, dan private download—tetap ditolak server-side dan tidak membocorkan harga beli, average cost, margin, valuation, laba, billing, atau staff management.
- Beberapa Staff dapat melakukan checkout/payment secara paralel tanpa stok negatif, movement ganda, pertukaran cashier, atau konflik idempotency lintas kasir.

Di luar Fase 8: role baru selain `owner|staff`, perubahan billing, refund/void/return oleh Staff, penghapusan master oleh Staff, serta perubahan formula analytics/POS Fase 6–7.

## 2. Decision gate dan Document Delta

CD-8.1 telah disahkan melalui `document-delta-f8-staff-multi-cashier.md` dan disinkronkan ke source of truth terkait. Keputusan implementasi dikunci sebagai berikut.

| Keputusan | Rekomendasi implementasi aman | Alasan |
|---|---|---|
| Lifecycle Staff | `is_active` + `auth_version`; deactivate/reset revoke token dan menaikkan version session. | Driver-independent untuk session database/Redis. |
| Pembuatan dan reset | Owner mengatur password minimal 12 karakter; tidak ada invitation email atau one-time display. | Tidak menambah delivery channel baru. |
| Cakupan transaksi | Staff hanya melihat/membayar transaksi dengan `cashier_id` sendiri; out-of-scope `404`. | Meminimalkan kebocoran antarkasir. |
| Stock operation Staff | Item/stok/low-stock/supplier read-only; seluruh mutation/opname/receiving tetap Owner-only. | Menghindari purchase cost dan ledger sensitif. |
| Idempotency multi-kasir | Tetap unique per tenant; key milik actor lain menghasilkan `409`. | Sesuai DDD/Blueprint dan tidak mengembalikan transaksi actor lain. |
| Diskon dan insight | Diskon existing diizinkan; insight hanya widget/kolom operasional non-finansial. | Staff dapat menjalankan POS penuh tanpa analytics mutation. |

UI menggunakan istilah **Buat Staff** dan **Atur Ulang Akses**; tidak ada istilah invite.

## 3. Desain domain, migration, dan akses sesi

1. Buat migration additive `users.is_active`, `users.auth_version`, dan index `(tenant_id, role, is_active, id)`. Data User historis aktif/version `1`; Owner tidak dapat dinonaktifkan/dihapus melalui workflow ini.
2. Pertahankan unique index POS `(tenant_id, idempotency_key)`; F8 tidak memigrasikan atau backfill transaksi/payment.
3. Perbarui `User` casts/fillable/query scope serta `TenantUserProvider` sehingga login tidak melewati status aktif. Tambahkan middleware setelah autentikasi yang memeriksa tenant operable dan actor aktif pada request web, API, serta Livewire.
4. Nonaktifkan Staff secara atomik: lock user tenant-scoped, set status, revoke Sanctum tokens, catat audit, lalu pastikan sesi web aktif tidak dapat melanjutkan pada request berikutnya. Reactivation tidak memulihkan token lama.
5. Buat Actions terpisah untuk create/update/reset/activate/deactivate Staff. Semua menerima actor Owner, melakukan ownership guard sebelum mutation, membatasi `role=staff`, dan menulis audit canonical dengan actor Owner serta target Staff. Tidak membuka mass-assignment `tenant_id`, `role=owner`, atau kolom keamanan di luar workflow yang diizinkan.
6. Tambahkan fresh, baseline-upgrade, dan security-lossy rollback harness untuk migration Fase 8; rollback tidak menghapus User atau histori POS.

## 4. Matriks otorisasi yang dikunci

Policy adalah security boundary. Navigasi, button, field, dan URL hanya mengikuti Policy—bukan menggantikannya.

| Surface/capability | Owner | Staff aktif | Staff nonaktif / tenant lain |
|---|---:|---:|---:|
| Login `/app/login` | Ya | Ya | Ditolak generik |
| Login `/admin/login` | Ditolak oleh guard admin | Ditolak oleh guard admin | Ditolak |
| Kelola Staff | Ya, tenant sendiri | Tidak | 403/404 sesuai ownership |
| Item, harga jual, stok, low-stock | Ya | Read-only | Ditolak |
| Harga beli, average cost, margin, valuation, laba | Ya sesuai surface yang ada | Tidak pernah diserialisasi | Ditolak/tidak tampil |
| Supplier | Ya | Read-only | Ditolak |
| POS checkout dan cash/QRIS/transfer | Ya | Ya, actor sendiri | Ditolak |
| Status/riwayat POS | Semua transaksi tenant | Hanya transaksi scope yang disahkan Delta | 404 untuk foreign ID, 403 untuk capability |
| Void, return, mark refunded | Ya | Tidak | Ditolak |
| Smart Threshold apply/settings | Ya | Tidak | Ditolak |
| Insight analytics operasional | Ya | Ya, read-only/non-finansial | Ditolak |
| Report, export, print financial, private download | Ya sesuai Policy | Tidak | Ditolak |
| Stock mutation dan receiving bernilai biaya | Ya | Owner-only kecuali Delta mengunci capability aman | Ditolak |

Implementasi policy tidak boleh melonggarkan `TenantOwnerPolicy` secara global. Buat policy/capability khusus Staff pada model yang memang diizinkan, lalu pertahankan policy Owner-only pada master mutation, report/export, audit, billing, void, return, dan refund.

## 5. Urutan implementasi

### 5.1 Kontrak dan fondasi akses

1. Audit seluruh endpoint, resource Filament, page, widget, Livewire action, queued job, notification, report renderer, dan private download dari baseline Fase 7.
2. Tulis dan sahkan Document Delta Fase 8 untuk enam decision gate di atas; perbarui PRD/Blueprint/DDD/SAD/API/UI/Roadmap hanya pada bagian yang benar-benar berubah.
3. Implementasikan migration, model, middleware active-user, provider login, Actions lifecycle, dan audit. Uji login generic failure, tenant status, active/inactive, reset access, cross-tenant ID, serta token/session revocation terlebih dahulu.

### 5.2 Authorization dan data minimization

1. Refactor `UserPolicy`, `PosTransactionPolicy`, `PosPaymentPolicy`, `ItemPolicy`, `SupplierPolicy`, dan policy lain yang dilalui Staff berdasarkan matriks, tanpa menjadikan `TenantOwnerPolicy` sebagai allow-all Staff.
2. Tambahkan API Resource/DTO atau serializer khusus agar response Staff secara struktural tidak mengirim `harga_beli`, `average_cost`, field margin/valuation/profit, metadata export, atau relasi sensitif. Jangan mengandalkan `hidden` model global jika Owner masih membutuhkan field tersebut.
3. Pastikan `OwnershipGuard`, action layer, controller, queue, dan renderer memverifikasi actor/tenant sendiri; ID milik tenant lain tetap disamarkan sebagai 404 bila kontrak endpoint demikian.
4. Batasi Staff pada transaksi yang boleh diaksesnya, termasuk transaksi pending yang dapat dibayar. Validasi actor secara ulang di `CheckoutPosAction`, finalizer payment, dan endpoint status; jangan hanya bergantung pada route/controller policy.

### 5.3 Workflow Owner dan UI Staff

1. Tambahkan Resource/Page **Staff** khusus Owner di panel `/app`: daftar, create/invite sesuai Delta, edit profil yang aman, reset akses, activate/deactivate dengan confirmation, status, serta feedback tanpa credential rahasia.
2. Ubah `User::canAccessPanel()` dan panel `/app` agar Staff aktif dapat masuk. `/admin` tetap menggunakan guard `admin` dan tidak pernah menemukan User tenant.
3. Pisahkan navigasi Owner/Staff melalui `shouldRegisterNavigation`, `canView`, `canAccess`, dan `visible`; Staff hanya memperoleh POS dan resource read-only/insight yang sudah disahkan.
4. Audit seluruh form/table/infolist/widget dashboard. Sembunyikan field finansial, action destructive, staff management, reports/exports, analytics setting/apply, void/return/refund; pastikan direct URL dan Livewire request tetap ditolak.
5. Sesuaikan `PosScreen` untuk Staff tanpa menerima cashier dari browser. Receipt boleh menyebut nama cashier actor; tidak boleh membuka cost. Uji desktop dan mobile.

### 5.4 Multi-kasir dan idempotency

1. Pertahankan database unique per tenant. Same actor + same key + payload sama mengembalikan hasil business yang sama; payload berbeda atau actor berbeda menghasilkan `409 IDEMPOTENCY_CONFLICT` tanpa mengembalikan transaksi actor pertama.
2. Pertahankan lock ordering item ascending dan lock transaksi sebelum payment. Semua mutation sale memakai actor authenticated sebagai `cashier_id`, `confirmed_by` untuk manual payment, `stock_movements.user_id`, dan actor audit.
3. Pastikan transaksi milik kasir A tidak dapat diselesaikan, void, return, atau direfund oleh kasir B kecuali Delta secara eksplisit mengizinkan hand-off (baseline rekomendasi: tidak diizinkan).
4. Jangan mengubah cek stok authoritative di checkout dan finalizer. Jika dua kasir sudah memiliki checkout pending untuk item terbatas, paling banyak satu finalisasi yang mengurangi stok; hasil lain konsisten (`INSUFFICIENT_STOCK` atau state terminal yang sudah dikunci) tanpa payment/movement ganda.

## 6. Rencana test dan quality gate

### Automated contract

- Owner dapat mengelola hanya Staff tenant sendiri; tidak dapat mengubah user tenant lain menjadi Staff atau Owner, dan Staff tidak dapat mengelola user.
- Staff aktif login `/app/login`; Staff gagal generik di `/admin/login`; Staff nonaktif gagal login dan session/token lama gagal pada request berikutnya.
- Matrix negative permission mencakup direct API/HTTP/Livewire/Filament URL untuk master mutation, staff management, report/export/download/print, analytics setting/apply, void, return, dan refund.
- Scan response HTML dan JSON, render report/export/receipt, notification payload, serta private file untuk memastikan field finansial tidak muncul pada seluruh route yang dapat diakses Staff.
- Staff dapat checkout lalu menyelesaikan cash, QRIS statis, dan transfer. `cashier_id`, `stock_movements.user_id`, dan audit harus menunjuk Staff yang benar; `confirmed_by` pada QRIS/transfer harus menunjuk Staff tersebut dan tetap `null` untuk cash sesuai constraint Fase 6.
- Tenant isolation tetap 404 untuk foreign record dan idempotency tenant/kasir terpisah. Test same key same cashier, same key different cashier, retry, dan payload conflict.
- Concurrency memakai minimal dua akun Staff pada tenant sama: checkout/payment cash vs cash, cash vs QRIS/transfer, stok terakhir, dan retry paralel. Assert stok tidak negatif, jumlah movement/payment benar, transaksi tidak tertukar, dan audit actor tepat.
- Regresi Owner Fase 0–7, migration fresh/upgrade/rollback, serta existing F7 analytics runtime tetap hijau.

### Visual/manual

- Desktop 1440×900: Owner membuat Staff, reset/nonaktifkan, Staff login, navigasi Staff, POS tiga metode, dan unauthorized state.
- Mobile 390×844: login, menu Staff, scanner/search, cart, modal cash/QRIS/transfer, receipt, serta tidak ada overflow horizontal.
- Perbandingan menu Owner vs Staff dan pemeriksaan manual bahwa data finansial/administratif tidak tampil.
- Simpan evidence tanpa password, token, email sensitif, atau QRIS produksi pada `docs/evidence/f8-staff-YYYY-MM-DD/`.

### Gate lokal dan CI

Jalankan `composer validate --strict`, `composer check-platform-reqs`, `npm test`, `npm run build`, `php artisan migrate:fresh --seed --force`, seluruh `php artisan test`, `php artisan view:cache`, `php artisan route:list`, `php artisan schedule:list`, dan `vendor/bin/pint --test`. Perluas CI bila harness migration Fase 8 atau test multi-process memerlukan service/env khusus; job `analytics-runtime` Fase 7 tidak boleh dihapus atau dilemahkan.

## 7. Deliverable dan exit criteria

Fase 8 hanya ditandai selesai setelah seluruh item berikut lulus:

- [ ] Document Delta Fase 8 disahkan dan dokumen sumber tersinkron.
- [ ] Lifecycle, create/invite, reset, activate/deactivate Staff Owner-only berfungsi dan teraudit.
- [ ] Login/guard, session/token revocation, tenant isolation, dan seluruh negative permission matrix lulus.
- [ ] POS Staff cash, QRIS, dan transfer mencatat actor yang benar tanpa kebocoran finansial.
- [ ] Concurrency multi-kasir dan idempotency tenant/kasir lulus tanpa stok negatif, payment/movement duplikat, atau transaksi tertukar.
- [ ] Walkthrough Owner/Staff desktop dan mobile serta evidence lengkap tersedia.
- [ ] Full local quality gate dan CI remote hijau; regresi Fase 7 analytics runtime tetap hijau.
- [ ] `docs/f8-acceptance.md` mencatat baseline, hasil test, evidence, SHA, CI run, serta catatan deployment yang memang ditunda.

## 8. Risiko utama dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Mengubah base policy membuat Staff mendapat mutation yang tidak dimaksud | Gunakan policy/capability spesifik per model, lalu uji allow dan deny matrix secara eksplisit. |
| UI sudah tersembunyi tetapi data tetap muncul di JSON/export/Livewire | Bangun serializer yang role-aware dan test semua representasi, bukan hanya screenshot. |
| Nonaktif hanya memblokir login, tetapi session/token lama tetap hidup | Middleware per request, revocation token, dan regression test session + Sanctum. |
| Unique idempotency global mengonflikkan kasir berbeda | Migration + lookup composite sesuai Delta serta test paralel multi-kasir. |
| Membuka stock input lama membocorkan harga beli | Tetap Owner-only sampai capability, payload, dan serializer bebas biaya disahkan. |
| Perubahan POS merusak invariant Fase 6–7 | Pertahankan transaction lock/order, action-level authorization, audit, dan seluruh regression/concurrency harness. |
