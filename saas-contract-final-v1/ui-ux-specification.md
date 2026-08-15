# Frontend UI/UX Contract
## SaaS Manajemen Stok

**Status:** Implementation Contract  
**Scope:** Frontend / UI / UX  
**Backend Authority:** Product Requirements Document + Technical Blueprint + Business Rules  
**Primary Stack:** Laravel 12 · Filament · Livewire · Tailwind CSS  
**Design Target:** Desktop · Tablet · Mobile · Touchscreen · Barcode Scanner  
**Theme:** Light + Dark  
**Product Principle:** Behavior Before Beauty

---

# 1. Purpose

Dokumen ini mendefinisikan standar resmi untuk antarmuka dan pengalaman pengguna SaaS Manajemen Stok.

Dokumen ini menentukan:

- bagaimana functionality backend direpresentasikan kepada pengguna;
- bagaimana pengguna berinteraksi dengan sistem;
- bagaimana state sistem ditampilkan;
- bagaimana role dan permission divisualisasikan;
- bagaimana POS dirancang untuk operasi berkecepatan tinggi;
- bagaimana loading, error, empty, success, dan critical state ditangani;
- bagaimana responsive interface bekerja;
- bagaimana design system digunakan secara konsisten;
- bagaimana accessibility diterapkan;
- bagaimana UI harus berperilaku ketika terjadi conflict atau kegagalan operasional.

Dokumen ini **tidak mendefinisikan business rule baru**.

Backend dan business contract tetap menjadi **single source of truth**.

---

# 2. Fundamental Authority

## 2.1 Backend Is the Source of Truth

Frontend tidak memiliki authority untuk menentukan kebenaran bisnis.

Frontend hanya:

- menampilkan state yang diberikan backend;
- mengirimkan intent/action pengguna;
- melakukan validasi dasar untuk membantu UX;
- mencegah accidental duplicate interaction;
- menyembunyikan action yang tidak tersedia bagi role;
- memberikan feedback terhadap hasil operasi.

Frontend tidak boleh menentukan sendiri:

- apakah stok tersedia;
- apakah stok boleh menjadi negatif;
- apakah pembayaran berhasil;
- apakah transaksi boleh diselesaikan;
- apakah refund sudah selesai;
- apakah subscription aktif;
- apakah trial masih valid;
- apakah user memiliki akses terhadap tenant;
- apakah user berhak melihat atau mengubah data;
- apakah suatu movement valid;
- apakah suatu transaksi dapat di-void atau diretur.

Semua keputusan tersebut berasal dari backend.

---

# 3. Backend State ≠ UI State

Backend boleh memiliki state machine yang lebih detail daripada yang perlu diketahui pengguna.

Frontend harus menerjemahkan state teknis menjadi state yang dapat dipahami manusia.

Contoh:

| Backend State | UI Representation |
|---|---|
| Preparing | Memproses pembayaran |
| QR Ready | Silakan pindai QR |
| Waiting Payment | Menunggu pembayaran |
| Confirmed | Pembayaran diterima |
| Finalizing | Menyelesaikan transaksi |
| Completed | Transaksi selesai |
| Refund Required | Pembayaran diterima — refund diperlukan |

UI tidak boleh mengubah makna state backend.

UI hanya menyederhanakan representasinya.

---

# 4. Core UX Principles

## 4.1 Behavior Before Beauty

UI harus terlebih dahulu benar secara:

1. state;
2. permission;
3. workflow;
4. feedback;
5. error handling;
6. accessibility;
7. consistency.

Visual tidak boleh menyembunyikan status, risiko, atau konsekuensi sebuah tindakan.

---

## 4.2 POS Speed First

Kecepatan operasi POS adalah prioritas UX tertinggi.

Setiap interaksi POS harus dievaluasi dengan pertanyaan:

> Apakah tindakan ini bisa dilakukan lebih cepat tanpa mengorbankan correctness?

Hindari:

- form panjang;
- dropdown SKU panjang;
- navigasi berulang;
- modal yang tidak perlu;
- konfirmasi berlebihan;
- full-page reload;
- interaksi yang memerlukan mouse jika keyboard dapat digunakan lebih cepat.

---

## 4.3 Device-Adaptive

Produk tetap mobile-friendly, tetapi POS tidak diposisikan sebagai mobile-only application.

Prioritas perangkat:

1. Desktop/laptop + barcode scanner
2. Tablet/touchscreen
3. Mobile

Halaman non-POS tetap responsive penuh.

POS harus dioptimalkan berdasarkan karakter perangkat, bukan sekadar mengecilkan layout desktop.

---

## 4.4 Explicit States

Setiap asynchronous operation harus memiliki state visual yang jelas.

Minimum:

- idle;
- loading;
- success;
- warning;
- error;
- critical;
- empty;
- disabled.

Tidak boleh ada kondisi ketika user menekan tombol lalu tidak mengetahui apakah sistem sedang bekerja.

---

## 4.5 No Silent Failure

Setiap kegagalan yang memiliki konsekuensi bisnis harus diberitahukan secara eksplisit.

Critical business failure tidak boleh hanya menggunakan toast yang menghilang.

---

## 4.6 Progressive Disclosure

Informasi ditampilkan berdasarkan relevansi.

User tidak perlu melihat seluruh informasi backend sekaligus.

Informasi sekunder dapat berada pada:

- detail panel;
- expandable section;
- drawer;
- modal;
- halaman detail;
- tooltip.

Namun informasi yang memengaruhi keputusan harus selalu mudah ditemukan.

---

# 5. Design System

## 5.1 Design Token

Semua UI harus menggunakan semantic design token.

Jangan menggunakan warna hardcoded secara langsung di component apabila semantic token tersedia.

Kategori minimum:

- background;
- foreground;
- muted foreground;
- border;
- primary;
- success;
- warning;
- danger;
- info;
- disabled;
- focus.

Token harus memiliki nilai untuk:

- Light Mode;
- Dark Mode.

---

# 6. Color Semantics

Warna tidak boleh digunakan hanya sebagai dekorasi.

### Success

Digunakan untuk:

- transaksi berhasil;
- pembayaran confirmed;
- stock operation berhasil;
- action berhasil.

### Warning

Digunakan untuk:

- low stock;
- pending state;
- koneksi tidak stabil;
- kondisi yang membutuhkan perhatian tetapi belum gagal.

### Danger

Digunakan untuk:

- void;
- critical failure;
- refund required;
- tindakan destructive.

### Info

Digunakan untuk:

- informasi sistem;
- guidance;
- explanatory message.

Warna tidak boleh menjadi satu-satunya indikator.

Setiap semantic state penting harus memiliki kombinasi:

**warna + icon + text/status**

---

# 7. Typography

Typography harus memprioritaskan:

- readability;
- numeric clarity;
- SKU density;
- scanability;
- hierarchy.

Default font dapat menggunakan **Inter**.

Hierarchy:

### Display / KPI
SemiBold 600.

### Heading
SemiBold 600.

### Body
Regular/Medium 400–500.

### Supporting information
Regular 400.

### SKU / metadata
Regular 400 dengan ukuran lebih kecil.

### Harga penting
SemiBold 600.

Angka finansial dan quantity harus mudah dipindai tanpa membuat interface terlihat terlalu berat.

---

# 8. Touch & Interaction Target

Target minimum touch:

**48px** untuk interactive control pada touchscreen/mobile.

Area klik harus cukup besar untuk penggunaan:

- sambil berdiri;
- satu tangan;
- touchscreen;
- lingkungan toko dengan kondisi penggunaan cepat.

Untuk desktop, target tidak harus selalu 48px secara visual jika density lebih tinggi diperlukan, tetapi hit area tetap harus usable.

---

# 9. Motion

Motion menggunakan prinsip:

**Subtle Functional Motion.**

Motion hanya digunakan untuk:

- feedback action;
- transition;
- loading;
- cart update;
- state change;
- success confirmation.

Tidak digunakan untuk dekorasi berlebihan.

Animasi tidak boleh:

- memperlambat transaksi;
- mengganggu scanner;
- menghalangi tombol;
- membuat critical state sulit dipahami.

Dark mode dan reduced-motion consideration harus tetap diperhatikan.

---

# 10. Navigation Architecture

## Desktop

Sidebar digunakan untuk navigasi utama.

Struktur mengikuti domain produk, bukan struktur database.

Contoh:

```text
Dashboard

Operasional
├── Barang
├── Stok
├── POS
├── Opname
└── Daftar Belanja

Analitik & Laporan
├── Analitik
└── Laporan

Pengaturan
├── Supplier
├── Pengguna
└── Pengaturan Toko

Billing
└── Langganan Saya
```

Menu harus mengikuti permission backend.

---

## Mobile

Mobile menggunakan navigation yang ringkas.

Bottom navigation dapat digunakan untuk menu paling sering digunakan:

```text
Dashboard
Barang
Stok
Belanja
Lainnya
```

Menu yang jarang digunakan masuk ke:

**Lainnya**

Billing dan administration tidak boleh ditampilkan kepada Staff/Kasir apabila role tersebut tidak memiliki permission.

---

# 11. Role-Based UI

## 11.1 Owner

Owner memiliki akses terhadap:

- operasional toko;
- inventory;
- supplier;
- laporan;
- analitik;
- financial information yang memang tersedia;
- billing;
- user/staff management sesuai contract;
- pengaturan toko.

---

## 11.2 Staff/Kasir

Staff/Kasir menggunakan interface yang berorientasi operasional.

Staff dapat melihat:

- harga jual;
- transaksi;
- quantity;
- low stock operational information;
- supplier information sesuai permission;
- POS.

Staff tidak boleh mendapatkan visualisasi:

- harga beli;
- margin;
- estimasi nilai persediaan;
- estimasi laba;
- laporan financial.

Frontend harus mengikuti permission backend.

**Hiding UI bukan security boundary.**

Backend tetap wajib menolak unauthorized access.

---

# 12. Owner Dashboard

Dashboard owner tidak menjadi halaman financial reporting.

Tujuan dashboard:

> memberikan gambaran cepat mengenai kondisi operasional stok.

KPI utama:

1. Total Barang/SKU
2. Total Qty Fisik
3. Low Stock
4. Fast Moving
5. Slow/Dead Stock

Financial information berada di halaman **Laporan**, bukan dashboard utama.

---

## 12.1 Dashboard Information Hierarchy

Urutan berdasarkan urgency:

```text
Critical Stock
↓
Shopping Recommendation
↓
Fast/Slow/Dead Stock Insight
↓
Operational Summary
```

Contoh:

```text
┌─────────────────────────────┐
│ 17 Barang Low Stock         │
│ Segera periksa daftar beli  │
│ [Lihat Daftar Belanja]      │
└─────────────────────────────┘

┌─────────────────────────────┐
│ Rekomendasi Belanja         │
│                             │
│ Indomie      50 pcs         │
│ Minyak       20 pcs         │
│ Beras        10 pcs         │
│                             │
│ [Buka Daftar Belanja]       │
└─────────────────────────────┘

┌─────────────────────────────┐
│ Fast / Slow / Dead Stock    │
│          chart              │
└─────────────────────────────┘
```

Dashboard tidak boleh dipenuhi chart hanya demi terlihat modern.

Visualization type ditentukan oleh informasi yang ingin disampaikan.

---

# 13. Financial Terminology

Produk bukan software accounting.

UI tidak boleh menggunakan istilah yang mengimplikasikan accounting completeness.

Gunakan:

- **Estimasi Nilai Persediaan**
- **Estimasi Laba Kotor**
- **Estimasi Margin**

Jangan gunakan:

- Laba Bersih
- Net Profit
- Accounting Balance
- laporan keuangan lengkap

kecuali functionality accounting memang tersedia di backend.

---

# 14. Financial Reporting

Financial-related information ditempatkan di halaman laporan.

Contoh:

```text
Laporan

[Stok] [Pergerakan] [Nilai Persediaan] [Penjualan]

Estimasi Nilai Persediaan
Rp84.200.000

Estimasi Laba Kotor
Rp12.500.000

Periode
[ 01 Aug 2026 ] — [ 10 Aug 2026 ]

[Export Excel]
[Export PDF]
[Print]
```

Jika angka merupakan estimasi, UI harus menyatakan bahwa angka tersebut adalah estimasi.

---

# 15. Inventory Philosophy

UI harus mempertahankan mental model:

> **Stock is the result of movements.**

User tidak boleh mendapatkan impression bahwa current stock adalah angka bebas yang dapat diedit.

Backend memang menetapkan stock movement sebagai histori permanen dan koreksi dilakukan melalui adjustment.

Karena itu tidak boleh ada action:

**Hapus Stok**

atau:

**Edit Current Stock**

Gunakan terminology:

- Stok Masuk
- Stok Keluar
- Penyesuaian Stok
- Retur
- Barang Rusak/Expired
- Stock Opname
- Riwayat Pergerakan

---

# 16. Item Management

Item screen harus mendukung:

- nama barang;
- SKU/barcode;
- kategori;
- satuan;
- harga jual;
- harga beli sesuai permission;
- stok;
- stok minimum;
- supplier;
- informasi relevan lainnya yang tersedia backend.

UI tidak boleh menampilkan field yang tidak tersedia atau tidak diizinkan oleh backend.

---

# 17. Stock Movement

Form movement harus menjelaskan:

```text
Jenis Pergerakan
Barang
Qty
Supplier / Reference bila relevan
Alasan
Catatan
```

Current stock ditampilkan sebagai context, bukan field yang dapat diedit.

Contoh:

```text
Stok Saat Ini
95 pcs

Stok Masuk
Qty
[ + 20 ]

Stok Setelah Transaksi
115 pcs
```

Nilai final tetap ditentukan backend.

---

# 18. Stock History

History harus mudah ditelusuri.

Minimum information:

```text
Tanggal
Barang
Jenis Movement
Qty
Actor
Reference
Catatan
```

Contoh:

```text
10 Aug 2026 14:32
Indomie Goreng

STOK KELUAR
-5 pcs

Oleh: Budi
Reference: POS-000123
```

Tidak ada action untuk mengedit atau menghapus historical movement.

Jika terjadi kesalahan, UI mengarahkan user ke workflow koreksi yang sah, bukan mengedit histori.

---

# 19. Low Stock

Low stock harus menjadi actionable information.

Contoh:

```text
⚠ 17 barang di bawah stok minimum

[ Lihat ]
```

Detail:

```text
Indomie Goreng
Stock     8
Minimum  20

[Tambahkan ke Daftar Belanja]
```

UI tidak membuat keputusan stok baru.

Threshold dan classification tetap berasal dari backend.

---

# 20. Shopping List

Shopping List adalah alat kontrol pembelian, bukan procurement system.

UI tidak boleh memperluasnya menjadi:

- purchase order;
- multi-level approval;
- procurement workflow.

Workflow tetap sederhana:

```text
Low Stock
↓
Shopping List
↓
Belanja
↓
Barang Datang
↓
Stock In
```

Nama CTA utama:

**Buat Daftar Belanja**

bukan:

**Ajukan Pengadaan**

---

# 21. Supplier

Supplier dapat ditampilkan dalam konteks global maupun per item.

Untuk item dengan beberapa supplier:

```text
INDOMIE GORENG

Preferred Supplier
PT ABC
Rp3.000
Lead Time 2 hari

Supplier Lain
CV DEF
Rp3.050
Lead Time 2 hari

CV GHI
Rp3.100
Lead Time 3 hari
```

UI harus membedakan:

- preferred supplier;
- alternative supplier.

UI tidak boleh menyiratkan bahwa satu item hanya memiliki satu supplier jika backend mendukung lebih dari satu.

---

# 22. Stock Opname

Stock opname diperlakukan sebagai **counting workflow**, bukan CRUD biasa.

Flow:

```text
Buka Opname
↓
Pilih / buat sesi
↓
Scan / input item
↓
Masukkan qty fisik
↓
Save & Next
↓
Item berikutnya
↓
Review
↓
Finalisasi
```

---

## 22.1 Counting Interface

Contoh:

```text
STOCK OPNAME

23 / 120 item

██████░░░░░░

Indomie Goreng

Stok Sistem
100

Stok Fisik
[ 95 ]

Selisih
-5

[ SIMPAN & NEXT ]
```

Input qty harus dominan secara visual.

User tidak boleh dipaksa kembali ke list setelah setiap item.

---

## 22.2 Finalization

Finalisasi adalah action consequential.

Gunakan confirmation:

```text
Finalisasi Stock Opname?

Sistem akan mencatat penyesuaian berdasarkan
selisih antara stok sistem dan stok fisik.

[Batalkan]
[Finalisasi]
```

Setelah finalisasi berhasil, histori movement tetap immutable.

---

# 23. POS Architecture

POS merupakan interface paling performance-sensitive.

Layout desktop:

```text
┌───────────────────────────────────────────────┐
│ Scan Barcode / Cari Barang                    │
├─────────────────────────┬─────────────────────┤
│ Product / Search        │ Keranjang           │
│                         │                     │
│                         │ Item                │
│                         │ Qty                 │
│                         │ Price               │
│                         │                     │
│                         │ Total               │
├─────────────────────────┴─────────────────────┤
│ Cash                         QRIS              │
└───────────────────────────────────────────────┘
```

---

# 24. Barcode Scanner

Scanner harus diperlakukan sebagai continuous input.

Flow:

```text
Scanner Ready
↓
Scan
↓
Product Found
↓
Add / Increment Cart
↓
Feedback
↓
Scanner Ready
```

Scanner tidak perlu dibuka ulang setelah setiap scan.

Blueprint sebelumnya memang menetapkan kamera/scan dapat kembali aktif untuk item berikutnya tanpa membuka ulang menu.

---

## 24.1 Product Found

Jika product ditemukan:

- tambahkan ke cart;
- jika sudah ada, increment quantity;
- berikan feedback singkat;
- tetap siap menerima scan berikutnya.

Tidak boleh membutuhkan konfirmasi manual untuk setiap barcode.

---

## 24.2 Product Not Found

```text
Barcode tidak ditemukan

[Tambah Barang]
[Scan Lagi]
```

Jangan hanya menampilkan:

> Data tidak ditemukan.

---

# 25. POS Keyboard Contract

Keyboard shortcut resmi:

| Shortcut | Action |
|---|---|
| F2 | Focus scan/search |
| Enter | Confirm/add |
| + | Increase quantity |
| - | Decrease quantity |
| Delete | Remove selected cart item |
| Esc | Close dialog |

Shortcut tidak boleh bypass backend validation.

Keyboard hanya mempercepat intent yang sama dengan UI biasa.

---

# 26. Cart

Cart harus mendukung operasi cepat:

```text
Indomie Goreng
Qty: 5
Rp3.500
Rp17.500

[-] [5] [+]
```

Jika item sudah ada, scan berikutnya otomatis menambah quantity.

Remove item merupakan cart operation dan bukan stock deletion.

---

# 27. Cash Payment

Cash payment harus memprioritaskan numeric input.

```text
Total
Rp750.000

Uang Diterima
[Rp800.000]

Kembalian
Rp50.000

[ SELESAI TRANSAKSI ]
```

Kembalian harus dihitung oleh backend/source of truth.

UI dapat memberikan preview untuk UX, tetapi hasil final berasal dari backend.

---

# 28. QRIS Payment

QRIS menggunakan explicit visual state.

Primary flow:

```text
Memproses pembayaran
↓
Silakan pindai QR
↓
Menunggu pembayaran
↓
Pembayaran diterima
↓
Menyelesaikan transaksi
↓
Transaksi selesai
```

UI tidak boleh menganggap QR code yang tampil sebagai bukti pembayaran sukses.

Pembayaran hanya dianggap sukses berdasarkan state backend.

---

# 29. QRIS Network Failure

Jika terjadi timeout/network error setelah payment request:

```text
⚠ Koneksi terputus

Pembayaran mungkin sudah diproses.

Jangan membuat pembayaran baru.

[Periksa Status Pembayaran]
```

Tidak boleh ada automatic duplicate payment.

User harus dapat memeriksa status payment berdasarkan backend.

---

# 30. QRIS Stock Conflict

Sistem tidak menggunakan stock reservation untuk flow QRIS ini.

Jika payment diterima tetapi validasi stok final gagal:

```text
⚠ PEMBAYARAN DITERIMA

Transaksi tidak dapat diselesaikan karena
stok berubah sebelum transaksi selesai.

Jumlah Dibayar
Rp150.000

Refund
Rp150.000

Status
MENUNGGU REFUND MANUAL

[ Lihat Detail ]
```

UI tidak boleh menawarkan:

**Bayar Lagi**

pada state ini.

Tujuannya mencegah duplicate payment.

---

# 31. Manual Refund

Refund QRIS pada flow ini adalah manual.

UI harus membedakan:

```text
Payment
CONFIRMED

Refund
REQUIRED

Refund Status
PENDING MANUAL REFUND
```

Setelah owner melakukan refund di luar sistem:

```text
[ TANDAI SUDAH REFUND ]
```

Button tersebut harus memiliki confirmation.

Contoh:

```text
Tandai pembayaran sebagai sudah direfund?

Pastikan refund telah benar-benar dilakukan
sebelum melanjutkan.

[Batalkan]
[Ya, Sudah Refund]
```

UI tidak boleh mengklaim refund berhasil hanya karena tombol ditekan tanpa pencatatan backend.

---

# 32. POS Return

Return harus menggunakan transaksi POS asli.

UI harus memungkinkan partial return.

Contoh:

```text
POS-000123

Indomie Goreng
Purchased: 5
Already Returned: 1

Return Qty
[ 2 ]

Remaining Returnable
2
```

Status dapat direpresentasikan sebagai:

- Returnable
- Partially Returned
- Fully Returned

Return tanpa transaksi asli bukan flow standar POS.

Jika owner membutuhkan koreksi stok tanpa transaksi return, gunakan workflow adjustment yang sesuai backend.

---

# 33. Void

Void adalah action consequential.

Gunakan:

- danger styling;
- explicit confirmation;
- reason jika diwajibkan backend;
- clear effect preview.

Contoh:

```text
Void transaksi?

POS-000123
Total Rp150.000

Tindakan ini akan mengubah status transaksi
dan memproses konsekuensi stok sesuai aturan sistem.

[Batalkan]
[Void Transaksi]
```

Void tidak sama dengan refund.

UI harus membedakan lifecycle:

**transaction lifecycle**

dan

**payment lifecycle**.

---

# 34. Loading States

Gunakan skeleton untuk:

- dashboard;
- table/list;
- detail;
- analytics.

Untuk button action:

```text
[ Menyimpan... ]
```

Button disabled selama request berjalan untuk mencegah duplicate submission.

Jangan menggunakan full-screen loading untuk action kecil.

Blueprint sebelumnya memang menetapkan skeleton untuk dashboard/list serta button-level loading untuk operation seperti stock in/out.

---

# 35. Optimistic UI

Optimistic UI hanya boleh digunakan jika aman secara semantics.

Frontend tidak boleh menampilkan business operation sebagai completed sebelum backend mengonfirmasi apabila action memiliki konsekuensi kritis.

Contoh aman:

- temporary UI interaction;
- local cart update.

Contoh tidak boleh dianggap final secara optimistic:

- stock movement;
- payment;
- return;
- void;
- subscription change.

---

# 36. Error Taxonomy

## Success

Action berhasil.

Contoh:

> Barang berhasil ditambahkan.

---

## Info

Informasi tanpa error.

> Export sedang diproses.

---

## Warning

Kondisi yang membutuhkan perhatian.

> Koneksi tidak stabil.

---

## Critical

Kondisi yang membutuhkan tindakan atau memiliki konsekuensi finansial/data.

Contoh:

> Pembayaran diterima tetapi transaksi tidak dapat diselesaikan. Refund manual diperlukan.

Critical state harus persistent sampai user mengetahui/menangani kondisi tersebut.

---

# 37. Toast Rules

Toast digunakan untuk feedback ringan:

- saved;
- updated;
- copied;
- deleted from cart;
- preference changed.

Toast tidak digunakan sebagai satu-satunya feedback untuk:

- payment conflict;
- stock conflict;
- refund required;
- subscription suspension;
- security issue;
- destructive business failure.

---

# 38. Empty State

Empty state harus contextual.

## No Items

```text
Belum ada barang

Tambahkan barang pertama untuk mulai
mengelola stok.

[ + Tambah Barang ]
[ Import Excel ]
```

## No Shopping List

```text
Semua stok aman

Belum ada barang yang perlu dibeli.
```

## Search Not Found

```text
Barang tidak ditemukan

Coba kata kunci atau barcode lain.

[Tambah Barang Baru]
[Scan Lagi]
```

## No Transaction

```text
Belum ada transaksi

Mulai transaksi pertama melalui POS.

[ Buka POS ]
```

Empty state tidak boleh terasa seperti error apabila memang tidak ada data.

---

# 39. Onboarding

Onboarding harus guided tetapi tidak unnecessarily blocking.

Flow:

```text
Account
✓

Store Setup
✓

Tambah Barang
○

Tambah Supplier
○

Transaksi Pertama
○
```

Requirement wajib tetap mengikuti backend.

Setelah requirement wajib selesai, user tidak dipaksa menyelesaikan semua optional setup sebelum menggunakan sistem.

Contoh:

```text
Setup Toko Anda

✓ Akun
✓ Nama Toko
○ Tambahkan Barang
○ Tambahkan Supplier
○ Coba POS

40% complete
```

Guidance tidak boleh menciptakan business requirement baru.

---

# 40. Authentication & Security UX

Authentication surface dipisahkan berdasarkan identity boundary:

- `/app/login` untuk Owner dan, setelah Fase 8, Staff/Kasir tenant;
- `/admin/login` untuk Super Admin/Support platform;
- pesan penolakan login tidak boleh mengungkap keberadaan akun;
- unauthorized state setelah autentikasi tetap mengikuti policy HTTP 403.

UI harus mendukung:

- login;
- OTP/2FA sesuai backend;
- password reset;
- security settings;
- session feedback;
- unauthorized state.

Security action harus menggunakan clear confirmation.

Frontend tidak boleh menyimpan atau memperlakukan credential sebagai business data.

---

# 41. Billing UI

Billing harus mengikuti subscription state backend.

UI harus mampu merepresentasikan minimal:

```text
ACTIVE
PAST_DUE
SUSPENDED
```

UI tidak boleh mengubah subscription status.

---

## 41.1 Active

```text
Langganan Aktif

Paket
Premium

Berlaku sampai
31 Aug 2026
```

---

## 41.2 Past Due

```text
⚠ Pembayaran diperlukan

Langganan Anda memiliki tagihan yang belum
diselesaikan.

Akses operasional terbatas sesuai kebijakan akun.

[Bayar Sekarang]
```

---

## 41.3 Suspended

```text
Langganan Ditangguhkan

Akses perubahan data saat ini tidak tersedia.

Data toko tetap tersimpan.

[Hubungi Admin]
```

UI hanya merepresentasikan restriction yang dikirim backend.

---

# 42. Permission & Disabled State

Ada perbedaan antara:

**Hidden**

dan

**Disabled**.

### Hidden

Digunakan jika user sama sekali tidak memiliki akses terhadap feature.

### Disabled

Digunakan jika:

- user memiliki akses terhadap feature;
- tetapi action sementara tidak valid karena state tertentu.

Contoh:

```text
[ Finalisasi ]
```

disabled ketika prerequisite belum terpenuhi.

Tooltip atau explanatory text harus menjelaskan alasannya jika tidak obvious.

---

# 43. Admin / Support Access

Jika Super Admin memiliki kemampuan support atau impersonation sesuai backend contract, UI harus membuat mode tersebut sangat jelas.

Contoh:

```text
⚠ SUPPORT MODE

Anda sedang melihat sebagai:
Toko ABC

[Keluar dari Support Mode]
```

Tidak boleh ada kesan bahwa admin sedang menggunakan akun tersebut secara normal.

Alasan akses sensitif harus dicatat oleh backend/audit system.

UI hanya menampilkan state dan context.

---

# 44. Audit Awareness

User tidak perlu melihat technical audit log di setiap halaman.

Namun UI harus memberikan context ketika action memiliki konsekuensi audit.

Contoh detail movement:

```text
Dicatat oleh
Aula

Tanggal
10 Aug 2026 14:32

Reference
POS-000123
```

Tidak boleh menyediakan action yang menghapus audit history jika backend menetapkannya immutable.

---

# 45. Responsive Strategy

## Desktop

Memaksimalkan information density.

Cocok untuk:

- dashboard;
- reports;
- tables;
- POS;
- analytics.

## Tablet

Menjaga touch interaction.

Cocok untuk:

- POS;
- stock opname;
- stock movement;
- item management.

## Mobile

Memprioritaskan:

- scanning;
- quick actions;
- alerts;
- dashboard summary;
- simple forms.

Layout mobile bukan sekadar desktop yang diperkecil.

---

# 46. Tables

Table digunakan ketika user perlu:

- membandingkan banyak row;
- melihat historical data;
- melakukan filtering;
- sorting;
- scanning numeric information.

Mobile table dapat berubah menjadi:

- card;
- horizontal scroll;
- condensed row;
- detail drawer.

Transformation tidak boleh menghilangkan informasi penting.

---

# 47. Search

Search harus mendukung workflow cepat.

Untuk item:

- nama;
- SKU;
- barcode;
- identifier yang didukung backend.

Search state:

```text
Typing
↓
Searching
↓
Results
```

Empty result harus memberikan next action.

---

# 48. Filtering

Filter harus digunakan terutama pada:

- stock history;
- POS history;
- reports;
- items;
- supplier;
- analytics.

Filter state harus terlihat.

Contoh:

```text
Filter aktif: 3

[Reset]
```

Jangan menyembunyikan filter yang sedang memengaruhi data.

---

# 49. Pagination & Data Density

Pagination atau lazy loading digunakan berdasarkan volume data.

Frontend tidak boleh meminta seluruh historical movement jika dataset besar.

Dashboard dan analytics mengikuti strategi backend untuk precomputed/cached data. Blueprint memang mengantisipasi query dashboard/analytics tidak menghitung raw movements secara berat secara real-time.

---

# 50. Reports & Export

Export tidak boleh memblokir UI.

Flow:

```text
[Export Excel]

↓
Menyiapkan laporan...

↓
Export sedang diproses

↓
Notifikasi

Laporan siap diunduh.
```

Untuk proses background, UI menampilkan status job dan menyediakan download ketika backend menyatakan file siap.

Blueprint memang menetapkan export besar diproses melalui queue dan notifikasi in-app, bukan synchronous request.

---

# 51. Accessibility Contract

Accessibility adalah bagian dari implementation contract.

Minimum:

- visible focus state;
- keyboard navigation;
- sufficient contrast;
- semantic HTML;
- icon + text untuk critical state;
- tidak bergantung pada warna saja;
- keyboard-accessible modal;
- focus trap untuk modal;
- ESC untuk close ketika aman;
- Enter untuk confirmation ketika tidak berisiko accidental destructive action;
- readable text;
- touch target memadai;
- reduced-motion consideration.

---

# 52. Modal Rules

Modal hanya digunakan untuk:

- confirmation;
- short form;
- contextual detail;
- focused action.

Jangan menggunakan modal untuk workflow panjang.

Destructive modal harus menjelaskan:

1. apa yang akan terjadi;
2. objek yang terkena;
3. konsekuensi;
4. action confirmation.

---

# 53. Confirmation Rules

Tidak semua action membutuhkan confirmation.

### Tidak perlu confirmation

- add item to cart;
- increase quantity;
- decrease quantity;
- search;
- filter;
- navigation.

### Perlu confirmation

- void;
- finalisasi stock opname;
- destructive account/data action;
- sensitive security action;
- mark manual refund completed;
- action yang secara irreversible mengubah lifecycle bisnis.

Tujuannya mengurangi confirmation fatigue.

---

# 54. Frontend Validation

Client-side validation digunakan untuk:

- required field;
- format;
- numeric input;
- obvious range;
- immediate feedback.

Namun client-side validation tidak menggantikan server validation.

Contoh:

```text
Qty harus lebih besar dari 0.
```

Frontend boleh memberitahukan ini sebelum submit.

Tetapi backend tetap menentukan apakah movement tersebut valid.

---

# 55. Duplicate Submission Prevention

Untuk action yang mengubah state:

- disable button saat request;
- tampilkan loading;
- cegah double click;
- jangan membuat duplicate local action;
- gunakan backend idempotency apabila backend menyediakan mekanisme tersebut.

Frontend prevention adalah UX safeguard.

Backend idempotency tetap menjadi correctness safeguard.

---

# 56. Offline

Produk tidak menggunakan offline-first architecture.

UI harus mengasumsikan sistem membutuhkan koneksi.

Jika koneksi hilang:

```text
⚠ Tidak ada koneksi

Beberapa perubahan belum dapat diproses.

[ Coba Lagi ]
```

Jangan memberikan kesan bahwa data sudah tersimpan jika backend belum mengonfirmasi.

PRD menetapkan mode offline berada di luar scope versi awal.

---

# 57. Dark Mode

Light dan Dark Mode didukung sejak awal.

Semua component harus memiliki semantic token untuk:

- background;
- text;
- border;
- primary;
- success;
- warning;
- danger;
- info;
- focus.

Jangan menggunakan warna yang hanya terlihat benar pada Light Mode.

Critical state harus tetap dapat dibedakan pada kedua mode.

Chart juga harus tetap terbaca pada kedua mode.

---

# 58. Visual Consistency

Component yang memiliki fungsi sama harus terlihat sama.

Contoh:

Semua primary CTA:

```text
[ Simpan ]
[ Tambah Barang ]
[ Buka POS ]
```

harus menggunakan visual language yang sama.

Danger action:

```text
[ Void ]
[ Batalkan ]
```

mengikuti danger token.

Tidak boleh setiap halaman memiliki style button sendiri.

---

# 59. Information Hierarchy

Setiap halaman harus memiliki:

1. page title;
2. primary objective;
3. primary action;
4. supporting information;
5. secondary actions.

Contoh:

```text
Barang

Kelola semua barang toko.

[ + Tambah Barang ]

Search
Filter

Table
```

Jangan menempatkan primary CTA lebih rendah daripada secondary actions.

---

# 60. Page-Specific UX Priorities

| Page | Primary UX Goal |
|---|---|
| Dashboard | Memahami kondisi toko dengan cepat |
| POS | Menyelesaikan transaksi secepat mungkin |
| Barang | Menemukan dan mengelola item |
| Stok | Mencatat movement dengan benar |
| Stock History | Menelusuri histori |
| Opname | Menghitung fisik secara cepat |
| Shopping List | Menentukan apa yang perlu dibeli |
| Supplier | Menentukan sumber pembelian |
| Analytics | Memahami pola stok |
| Reports | Mengambil informasi operasional |
| Billing | Memahami status subscription dan payment |
| Settings | Mengelola konfigurasi |
| Admin | Support dan SaaS management |

---

# 61. Frontend Non-Goals

Frontend tidak boleh berkembang menjadi:

- accounting UI;
- procurement suite;
- ERP dashboard;
- CRM;
- multi-outlet management sebelum backend mendukung;
- offline POS;
- arbitrary stock editor;
- custom permission builder jika backend tidak mendukung;
- alternative payment gateway workflow tanpa backend support.

PRD memang secara eksplisit menempatkan accounting, formal purchase order, multi-outlet, dan offline mode di luar scope awal.

---

# 62. Feature Parity Rule

Frontend harus merepresentasikan functionality yang benar-benar tersedia pada backend.

Frontend tidak boleh membuat:

- fake functionality;
- mock success;
- fake stock state;
- fake payment confirmation;
- fake subscription status.

Jika backend belum mendukung suatu capability:

UI harus:

- tidak menampilkan feature tersebut; atau
- menampilkan planned/disabled state hanya jika product requirement secara eksplisit mengharuskannya.

---

# 63. Backend Contract Compatibility

Setiap frontend feature harus dapat dipetakan ke backend capability.

Mapping minimum:

```text
UI Action
    ↓
Frontend Component
    ↓
Livewire / Action / Request
    ↓
Backend Business Rule
    ↓
Database State
    ↓
Backend Response
    ↓
UI State
```

Frontend tidak boleh memotong business layer hanya untuk mempercepat implementasi.

---

# 64. Testing Contract

UI implementation harus diuji pada workflow yang memiliki business consequence.

Minimum test scenario:

### POS

- scan item;
- duplicate scan;
- cart quantity;
- cash payment;
- QRIS payment;
- payment timeout;
- payment confirmed;
- stock conflict;
- refund required;
- void;
- partial return.

### Inventory

- stock in;
- stock out;
- adjustment;
- low stock;
- history;
- concurrent conflict.

### Opname

- create session;
- scan;
- physical qty;
- save & next;
- review;
- finalize.

### Billing

- active;
- past due;
- suspended;
- payment action.

### Permission

- owner;
- staff;
- unauthorized action.

---

# 65. UI Acceptance Criteria

A feature dianggap UI-complete hanya jika:

- state loading jelas;
- success feedback jelas;
- error feedback jelas;
- empty state tersedia;
- permission state benar;
- responsive pada target device;
- keyboard/touch interaction dapat digunakan;
- critical action memiliki confirmation yang tepat;
- backend response direpresentasikan dengan benar;
- tidak ada fake state;
- tidak ada business rule yang hanya hidup di frontend.

---

# 66. Definition of Done — POS

POS dianggap selesai apabila:

- barcode scanner dapat digunakan secara continuous;
- search dapat digunakan sebagai fallback;
- duplicate scan menambah quantity;
- cart dapat diedit dengan cepat;
- keyboard shortcut bekerja;
- cash workflow jelas;
- QRIS state jelas;
- payment timeout tidak menyebabkan duplicate payment;
- stock conflict memiliki recovery state;
- refund-required state tidak dapat disalahartikan sebagai completed;
- transaction success memiliki final confirmation.

---

# 67. Definition of Done — Inventory

Inventory UI dianggap selesai apabila:

- tidak ada direct current-stock editing;
- semua stock mutation diarahkan ke movement workflow;
- movement history dapat ditelusuri;
- adjustment memiliki context;
- low-stock state actionable;
- empty state tersedia;
- loading/error state tersedia;
- permission diterapkan pada UI;
- backend tetap menjadi authority.

---

# 68. Definition of Done — Stock Opname

Stock opname dianggap selesai apabila:

- session dapat dibuat;
- item dapat discan/diproses berurutan;
- qty fisik mudah dimasukkan;
- Save & Next tersedia;
- progress terlihat;
- discrepancy terlihat;
- finalization membutuhkan confirmation;
- finalization result jelas;
- UI tidak memungkinkan edit histori setelah finalization.

---

# 69. Definition of Done — Reporting

Reporting dianggap selesai apabila:

- laporan dapat dibaca;
- financial wording tidak misleading;
- estimasi ditandai sebagai estimasi;
- filter jelas;
- loading state jelas;
- export progress jelas;
- hasil export dapat diakses setelah backend selesai memproses.

---

# 70. Final Frontend Principles

Implementasi frontend harus selalu memegang prinsip berikut:

1. **Backend is the source of truth.**
2. **Behavior before beauty.**
3. **POS speed first.**
4. **Device-adaptive, not mobile-only.**
5. **No silent failure.**
6. **Critical state must be persistent and explicit.**
7. **Stock is represented as movement, not an editable number.**
8. **Payment lifecycle and transaction lifecycle are separate concepts.**
9. **UI state may simplify backend state but must never change its meaning.**
10. **Permission visibility is UX; authorization remains backend responsibility.**
11. **Financial information must be presented as estimation where applicable.**
12. **Dashboard prioritizes operational decisions, not visual decoration.**
13. **Every destructive or consequential action must be explicit.**
14. **Empty, loading, success, warning, error, and critical states are first-class UI states.**
15. **Accessibility is part of correctness.**
16. **Light and dark mode are both first-class themes.**
17. **Frontend must not create business rules that do not exist in the backend contract.**
18. **The interface must optimize the user's actual workflow, not the database structure.**
19. **A beautiful interface that misrepresents backend state is considered incorrect.**
20. **When frontend convenience conflicts with backend correctness, backend correctness wins.**

---

# 71. Contract Boundary

Dokumen ini berfungsi sebagai **frontend implementation contract**.

Apabila terjadi konflik:

```text
Backend / Business Contract
        ↓
Product Requirements
        ↓
Frontend UI/UX Contract
        ↓
Visual Design
        ↓
Implementation Detail
```

Urutan authority tersebut wajib dipertahankan.

Frontend boleh meningkatkan:

- clarity;
- speed;
- accessibility;
- responsiveness;
- visual hierarchy;
- discoverability;
- error recovery;
- interaction efficiency.

Frontend tidak boleh mengubah:

- business rule;
- data authority;
- permission;
- transaction lifecycle;
- payment lifecycle;
- stock invariants;
- subscription state;
- tenant isolation;
- auditability;
- immutability.

**Tujuan akhir frontend bukan membuat sistem terlihat kompleks atau canggih, tetapi membuat business system yang kompleks terasa sederhana, cepat, aman, dan dapat dipahami oleh pengguna toko.**
