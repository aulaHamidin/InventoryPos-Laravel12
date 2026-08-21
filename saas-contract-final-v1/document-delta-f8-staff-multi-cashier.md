# Document Delta Fase 8 — Staff & Multi-Kasir

Status: **DISETUJUI**

Tanggal: 2026-08-21 (`Asia/Jakarta`)

Baseline: `ad07521fbdf81ccf5a3fe9185fecac5eb96fa01e`

## 1. Tujuan

Delta ini mengunci lifecycle Staff, invalidasi akses, permission operasional, projection bebas purchase cost, ownership transaksi, dan idempotency multi-kasir yang sebelumnya belum cukup rinci untuk diimplementasikan secara deterministik.

## 2. Lifecycle Staff

- `users` memperoleh `is_active BOOLEAN NOT NULL DEFAULT TRUE` dan `auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1`.
- Data historis tetap aktif dengan auth version `1`.
- Owner membuat Staff aktif dengan nama, email, nomor HP, dan password awal minimal 12 karakter yang dikonfirmasi. Tidak ada invitation email atau password yang ditampilkan ulang.
- Owner hanya dapat mengubah profil Staff tenant sendiri, mengatur ulang password, mengaktifkan, dan menonaktifkan. Role dan tenant tidak dapat diubah melalui workflow ini; Owner tidak dapat menjadi target.
- Deactivate dan reset password menaikkan `auth_version` serta menghapus seluruh Sanctum token. Activate mempertahankan version terbaru sehingga akses lama tidak pulih.
- Session web menyimpan auth version saat login. Version hilang atau berbeda memaksa logout dan invalidasi session pada request berikutnya, terlepas dari database/Redis session driver.
- Staff nonaktif tidak dapat login. Pesan login tetap generik. API token lama menghasilkan `401`; session web lama diarahkan ke `/app/login`.
- Audit action: `staff.created`, `staff.profile_updated`, `staff.access_reset`, `staff.activated`, dan `staff.deactivated`. Password/token tidak pernah disimpan pada audit atau log.

## 3. Permission dan Projection

- Staff aktif dapat mengakses dashboard operasional, POS, Barang read-only, Supplier read-only, dan transaksi dengan `cashier_id` miliknya.
- Staff tidak mendapat stock movement/opname/receiving/adjustment, Shopping List, master mutation, report/export/print, aggregate payment summary, audit, analytics settings/apply, void, return, refund, billing, atau staff management.
- Staff boleh memakai diskon baris POS existing: nilai non-negatif dan tidak melebihi bruto baris.
- Data Staff boleh memuat harga jual, jumlah, stok, threshold minimum, movement class/timestamp, total/payment/kembalian transaksi miliknya, dan informasi supplier non-harga.
- Data Staff tidak boleh memuat `harga_beli`, `average_cost`, `harga_beli_terakhir`, margin, valuation, profit, billing, atau projection finansial turunannya.
- Item API/Livewire menggunakan projection eksplisit. Supplier link Staff menghilangkan harga beli terakhir. Hiding UI bukan security boundary.
- Tidak ada endpoint publik baru untuk staff management; workflow Owner hanya melalui Filament dan Actions.

## 4. POS Multi-Kasir

- Unique constraint idempotency tetap `UNIQUE(tenant_id, idempotency_key)` pada transaksi dan payment.
- Same actor + key + payload sama mengembalikan hasil lama. Payload berbeda menghasilkan `409 IDEMPOTENCY_CONFLICT`.
- Key yang sudah dipakai kasir lain menghasilkan `409 IDEMPOTENCY_CONFLICT` tanpa mengembalikan identitas atau payload resource kasir pertama.
- Canonical manual payment comparison mencakup `confirmed_by`.
- Staff hanya dapat melihat dan membayar transaksi miliknya. Record tenant/kasir lain disamarkan `404`; capability terlarang pada record sendiri menghasilkan `403`.
- Owner tetap dapat melihat dan menangani seluruh transaksi tenant.
- `cashier_id`, `confirmed_by`, stock movement `user_id`, dan audit actor selalu berasal dari authenticated actor, bukan request.
- Lock order, stock revalidation, payment/refund lifecycle, dan no-reservation invariant Fase 6 tidak berubah.

## 5. Rollback dan Release

- Rollback F8 tidak menghapus User atau histori POS, tetapi security-lossy karena status aktif dan auth version hilang. Rollback hanya dalam maintenance mode dengan backup.
- Deployment ditunda ke release v1. Release gate wajib menjalankan migration, memverifikasi session/cache/queue Redis, worker, login Staff, dan revocation behavior.

## 6. Acceptance Minimum

- Migration fresh/upgrade/rollback, lifecycle, audit, tenant isolation, session/token revocation, projection leakage, permission matrix, idempotency, dan multi-process concurrency lulus.
- CI memiliki Redis-backed `staff-runtime`; job F7 `analytics-runtime` tetap aktif.
- Evidence desktop/mobile Owner/Staff tidak memuat credential atau data sensitif.
