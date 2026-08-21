# Product Requirements Document (PRD)
# Smart POS & Manajemen Stok untuk Ritel Komoditas Padat SKU

| Atribut | Nilai |
|---|---|
| Status | Product Contract |
| Versi kontrak | 1.0 |
| Bahasa | Indonesia |
| Produk | Smart POS & Manajemen Stok SaaS |
| Model deployment | Multi-tenant SaaS |
| Target utama | Ritel komoditas padat SKU |

---

## 1. Visi Produk

Produk adalah SaaS **Smart POS + Manajemen Stok** untuk toko ritel komoditas padat SKU seperti sparepart otomotif, material bangunan, alat listrik, dan kategori sejenis.

Produk harus menjadi:

1. **Kasir yang cepat dan dapat dipercaya** untuk operasional harian.
2. **Sistem stok yang akurat** karena seluruh perubahan stok memiliki histori immutable.
3. **Mesin rekomendasi belanja sederhana** yang membantu owner mengetahui barang yang perlu dibeli.
4. **Alat kontrol owner**, bukan software akuntansi atau ERP.

Prioritas produk, dalam urutan:

1. Inventory accuracy
2. POS speed
3. Stock recommendation
4. Simplicity untuk owner
5. Financial/reporting
6. Multi-store scalability

Jika dua kebutuhan bertentangan, kebutuhan dengan prioritas lebih tinggi menang selama tidak melanggar keamanan atau integritas data.

---

## 2. Problem Statement

Toko dengan ratusan hingga ribuan SKU sering mengalami:

- stok sistem tidak sama dengan stok fisik;
- stok barang laku habis tanpa peringatan;
- pembelian supplier dilakukan berdasarkan perkiraan;
- modal tertahan pada dead stock;
- barang rusak, hilang, atau retur tidak memiliki histori yang jelas;
- transaksi kasir tidak selalu mengurangi stok secara konsisten;
- sistem POS generik terlalu berat atau tidak nyaman untuk katalog SKU padat.

Produk menyelesaikan masalah tersebut melalui satu alur operasional:

`Barang → Stok → POS → Pergerakan Stok → Analitik → Rekomendasi Belanja`.

---

## 3. Tujuan Produk

### 3.1 Tujuan utama

- Membuat angka stok dapat dipercaya.
- Mempercepat transaksi kasir.
- Mengurangi kehabisan stok barang penting.
- Membantu owner menentukan apa yang perlu dibeli.
- Menyediakan audit trail untuk perubahan stok dan transaksi.
- Menyediakan SaaS yang dapat dioperasikan oleh satu developer tanpa arsitektur berlebihan.

### 3.2 KPI

| Metrik | Target indikatif |
|---|---:|
| Tenant aktif tahun pertama | 100 |
| Retensi bulanan | ≥ 85% |
| Tenant aktif harian | ≥ 60% |
| Dashboard pada koneksi mobile biasa | ≤ 2 detik |
| Cycle count 50–100 item | ≤ 15 menit |
| Insiden stok tidak akurat akibat bug sistem | Mendekati 0 |
| Trial conversion | Diukur dan menjadi KPI utama |
| Penggunaan POS harian | Diukur |

---

## 4. Persona

### 4.1 Owner

Owner memiliki kendali penuh terhadap tenant, harga, stok, supplier, laporan, staff, billing, dan pengaturan.

### 4.2 Kasir/Staff

Kasir adalah pengguna operasional harian. Ia menggunakan POS dan fungsi stok dasar yang memang diperlukan untuk pekerjaan toko.

Kasir tidak boleh melihat:

- harga beli;
- margin;
- nilai persediaan;
- laporan laba.

Kasir boleh:

- melihat harga jual;
- melakukan transaksi POS;
- melihat supplier;
- input stok dasar sesuai permission;
- menerima barang jika workflow tersebut diberikan.

Pada Fase 8, permission yang diberikan adalah POS lengkap dengan diskon existing, item/stok/low-stock/supplier read-only, analytics operasional non-finansial, dan histori transaksi kasir sendiri. Stock mutation, opname, receiving, Shopping List, report/export, void/return/refund, billing, dan staff management tetap Owner-only.

### 4.3 Super Admin

Super Admin adalah pengguna internal SaaS. Ia mengelola tenant, subscription, support, dan keamanan platform.

Akses support terhadap data tenant selalu tercatat.

---

## 5. Scope Produk

### 5.1 Termasuk

- Multi-tenant.
- Authentication dan OTP onboarding.
- Owner dan Staff/Kasir.
- Master barang.
- Kategori.
- Rak/lokasi.
- Supplier.
- Relasi barang-supplier dan preferred supplier.
- Stock in/out.
- Adjustment.
- Customer return.
- Supplier return.
- Damaged/expired stock.
- Immutable stock movement ledger.
- Cycle counting per rak.
- Full opname sebagai opsi.
- Smart POS.
- Cash payment.
- QRIS statis/manual milik toko.
- Transfer bank manual.
- Partial return.
- Void.
- Manual refund workflow.
- Audit log.
- Low stock alert.
- Shopping list otomatis.
- Fast/slow/dead stock.
- Smart Threshold berbasis moving average sederhana 30 hari.
- Estimasi nilai persediaan.
- Export PDF/Excel.
- Billing subscription.
- Trial 14 hari.
- Admin pusat.
- Self-service onboarding pada v1.
- Manual billing pada MVP dan gateway billing pada v1.
- Web Bluetooth printing dengan fallback print dialog/PDF.

### 5.2 Di luar scope

- Accounting penuh.
- Jurnal, neraca, dan pembukuan formal.
- Pajak.
- Piutang/utang.
- Purchase order approval multi-level.
- Batch/lot tracking.
- Multi-unit conversion kompleks.
- Offline-first penuh.
- Multi-outlet dalam satu tenant pada v1.
- Custom role/permission pada v1.
- Manager role.
- Forecasting statistik, regresi, seasonality.
- Weighted Moving Average.
- Lifetime license.
- Refund otomatis payment gateway pada v1.
- Microservices/CQRS/Event Sourcing penuh.

---

## 6. Fitur dan Aturan Produk

### 6.1 Master barang

Setiap barang memiliki:

- SKU unik dalam tenant;
- barcode opsional;
- kategori;
- rak;
- satuan;
- harga beli referensi;
- average cost;
- harga jual;
- stok saat ini;
- stok minimum atau Smart Threshold;
- status aktif.

`harga_beli` bukan basis utama valuasi. `average_cost` adalah basis estimasi nilai stok.

### 6.2 Stok

Setiap perubahan stok wajib memiliki sumber movement.

Tidak ada UI yang boleh mengubah `stok_saat_ini` secara langsung.

Sumber stok meliputi:

- stock in;
- stock out;
- sale;
- customer return;
- supplier return;
- damaged;
- adjustment;
- opname adjustment.

### 6.3 Smart POS

POS wajib:

- scan barcode;
- memasukkan item ke cart;
- menghitung harga di server;
- mendukung diskon per baris;
- menghitung total di server;
- mendukung cash;
- mendukung QRIS statis dan transfer manual;
- mendukung void;
- mendukung partial return;
- mempertahankan histori transaksi.

Client tidak boleh mengirim total yang dipercaya server.

### 6.4 Payment dan transaksi

Lifecycle transaksi dan lifecycle uang adalah dua konsep berbeda.

`pos_transactions` menjawab:

> transaksi barang berada di tahap apa?

`pos_payments` menjawab:

> uang transaksi berada di tahap apa?

QRIS POS adalah QRIS statis/manual milik toko dan transfer adalah transfer bank manual. Operator berwenang memeriksa aplikasi merchant atau rekening toko sebelum mengonfirmasi dana diterima. Aplikasi tidak memverifikasi bank/provider dan screenshot pelanggan bukan bukti pembayaran.

Refund cash, QRIS, dan transfer dicatat manual oleh Owner di aplikasi. Midtrans hanya digunakan untuk billing SaaS Fase 11.

### 6.5 Pembayaran manual tanpa reservation

Produk tidak melakukan stock reservation.

Alurnya:

1. checkout membuat transaksi pending dengan harga server;
2. customer membayar melalui media milik toko;
3. operator memeriksa aplikasi merchant/rekening;
4. operator mengonfirmasi pembayaran dengan request idempotent;
5. stok divalidasi ulang;
6. jika stok cukup → payment `paid` dan transaksi `completed`;
7. jika stok tidak cukup → transaction/payment `refund_required` tanpa sale movement.

Checkout `pending_payment` lebih dari 24 jam menjadi `expired` dan harus dibuat ulang agar harga tidak memakai snapshot lama.

### 6.6 Return dan void

Return wajib mengacu pada transaksi POS asli.

Partial return didukung.

Return tanpa transaksi POS tidak tersedia.

Jika owner menerima barang tanpa bukti transaksi, owner menggunakan adjustment workflow yang memiliki alasan.

Void tidak menghapus transaksi.

Void membuat reversal stock movement.

Untuk seluruh metode yang memiliki payment sudah dibayar, pengembalian uang masuk ke workflow refund manual dan cumulative.

### 6.7 Inventory valuation

Valuasi adalah **estimasi operasional**, bukan accounting.

Produk menggunakan simplified moving average.

Untuk stock in:

`new_average_cost = ((old_stock × old_average_cost) + (in_qty × in_unit_cost)) / (old_stock + in_qty)`

Jika stok awal nol:

`new_average_cost = in_unit_cost`

Untuk stock out:

`average_cost` tidak berubah.

Untuk customer return, supplier return, damaged, dan adjustment:

- `average_cost` tidak dihitung ulang;
- movement menggunakan `average_cost` yang berlaku pada saat movement;
- return customer dinilai menggunakan current average cost;
- return supplier dan adjustment tidak mengubah average cost.

Keterbatasan ini disengaja karena produk bukan accounting/ERP dan tidak menggunakan batch tracking.

### 6.8 Cycle counting

Cycle counting default menggunakan scope `partial` per rak.

Snapshot sistem dibuat **saat item dihitung**, bukan saat sesi dibuat.

Setiap detail opname memiliki:

- `qty_sistem_at_count`;
- `qty_fisik`;
- `counted_at`.

Selisih:

`selisih = qty_fisik - qty_sistem_at_count`

Selisih diterapkan pada stok saat finalize menggunakan transaction + lock.

Opname tidak memblokir transaksi normal.

Sesi `full` tidak boleh berjalan bersamaan dengan partial.

Partial boleh paralel jika rak berbeda.

Membership sesi diambil dari item aktif dan non-deleted saat sesi dibuat, lalu dibekukan sampai finalisasi. Item baru atau perubahan rak/status setelah create tidak mengubah daftar detail.

Save pertama mengunci `qty_sistem_at_count` dan `counted_at`. Koreksi berikutnya hanya mengubah `qty_fisik` dan `note`. `qty_fisik` adalah integer non-negatif dan scope tanpa item valid ditolak.

### 6.9 Supplier

Satu item dapat memiliki banyak supplier.

Tepat satu supplier dapat menjadi preferred supplier aktif.

Preferred supplier ditetapkan melalui Action dalam transaction dengan locking.

Shopping list tidak boleh menebak supplier dari movement terakhir.

Jika preferred supplier belum ada, item masuk shopping list dengan supplier kosong dan harus ditentukan owner sebelum submit.

### 6.10 Analytics dan Smart Threshold

- Demand menggunakan Net POS: `max(0, sale - sale_void - customer_return)`.
- Window velocity memakai zona waktu bisnis `Asia/Jakarta`, half-open `[as_of - 30×24 jam, as_of)`, dan denominator tetap 30.
- Item dengan histori kurang dari 30×24 jam berstatus `unclassified`; tidak ada prorata hari aktif.
- Ambang: fast `>=1.00`, normal `>=0.25 dan <1.00`, slow `<0.25` unit/hari.
- Dead mengoverride velocity jika item cukup umur dan Net POS demand selama `tenant.dead_stock_days` nol.
- `dead_stock_days=0` menonaktifkan klasifikasi dead.
- Smart Threshold memakai preferred supplier lead time non-null, termasuk nol, lalu fallback item lead time, ditambah safety stock days.
- Item eligible tanpa movement menghasilkan threshold `0` dan kelas `slow`, kecuali memenuhi dead override.
- History yang belum cukup tidak boleh mengubah threshold manual.
- Recalculation terjadi setelah commit movement demand, perubahan input terkait, daily sweep, dan explicit Smart Threshold action.
- Klasifikasi dipersist untuk seluruh item aktif; update otomatis `stok_minimal` hanya berlaku pada mode `auto_velocity`.
- Analytics adalah insight operasional sederhana, bukan forecasting statistik.

### 6.11 Trial

Trial adalah 14 hari.

Satu nomor HP hanya berhak mendapatkan trial gratis satu kali sepanjang masa.

`UNIQUE(no_hp)` mencegah registrasi user baru dengan nomor yang sama, tetapi bukan satu-satunya mekanisme trial abuse prevention.

`CreateSubscriptionAction` wajib menolak trial baru jika histori subscription tenant/pemilik menunjukkan pernah ada subscription dengan `plans.is_trial = true`, apa pun statusnya.

---

## 7. Billing dan Onboarding

### MVP

- Tenant dibuat manual oleh Super Admin.
- Trial dan subscription dibuat melalui admin workflow.
- Pembayaran subscription diverifikasi manual.
- Tidak ada self-service onboarding.

### v1

- Owner registrasi sendiri.
- OTP nomor HP.
- Trial 14 hari otomatis.
- Payment subscription terhubung gateway.
- Webhook digunakan untuk konfirmasi payment.
- Activation subscription otomatis berdasarkan payment yang tervalidasi.

Billing subscription bukan bagian dari POS payment.

---

## 8. Data Deletion dan Retention

Record historis individual tidak dapat dihapus:

- stock movements;
- POS transactions;
- POS payments;
- invoices;
- subscription events;
- audit logs.

Owner dapat mengajukan penghapusan tenant.

Penghapusan tenant tidak dilakukan per tabel.

Alurnya:

`request → review/approval → deletion queue → retention period → tenant purge`.

Purge dilakukan sebagai satu operasi level tenant dan mengandalkan `ON DELETE CASCADE` dari `tenants.id` ke seluruh child records.

Tidak ada endpoint `DELETE` untuk record transaksi individual.

---

## 9. Security

- Tenant isolation wajib.
- `tenant_id` tidak pernah berasal dari client.
- Ownership Guard wajib memvalidasi semua foreign key tenant-scoped.
- Owner dan Admin wajib 2FA sesuai policy.
- Support access wajib audit.
- Impersonation wajib:
  - alasan;
  - waktu kedaluwarsa;
  - indikator UI;
  - audit event.
- Super Admin tidak boleh mengedit stok secara langsung.
- Koreksi stok harus melalui Action/movement yang sama dan tercatat sebagai actor admin.

---

## 10. Prinsip UX

- Mobile-first.
- POS adalah alur tercepat.
- Primary color Indigo-600.
- Success Emerald-500.
- Warning Amber-500.
- Danger Rose-600.
- Font Inter.
- Bottom navigation: Dashboard, Kasir, Barang, Stok, Belanja.
- Semua operasi memiliki loading, empty, success, error, dan permission state.
- Tidak ada silent failure.
- Web Bluetooth memiliki fallback.

---

## 11. Non-Functional Requirements

- Shared-schema multi-tenant.
- MySQL 8+.
- Laravel 12.
- Transaction boundary untuk mutasi stok.
- Row locking.
- Idempotency untuk command penting.
- Manual payment idempotency dan unique-key race safe.
- Billing webhook signature verification.
- Billing webhook duplicate/out-of-order safe.
- Audit trail.
- Automated testing.
- Backup.
- Monitoring.
- Graceful network failure.
- Tidak mengklaim offline-first.

---

## 12. Prinsip Kontrak

Jika implementasi membutuhkan:

- kolom baru;
- endpoint baru;
- perubahan status;
- business rule baru;

maka dokumen kontrak harus diperbarui terlebih dahulu melalui Document Delta Declaration.

Tidak boleh ada keputusan bisnis yang hanya hidup di kode.
