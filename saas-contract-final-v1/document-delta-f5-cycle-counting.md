# Document Delta — Fase 5 Cycle Counting

Tanggal: 15 Agustus 2026

## Affected documents

- `prd-saas-manajemen-stok.md`
- `blueprint-saas-stok.md`
- `database-design-document.md`
- `api-specification.md`
- `ui-ux-specification.md`

## Reason

Kontrak awal sudah mengunci scope, snapshot per item, rekonsiliasi time-aware, dan state `draft → completed`, tetapi belum menetapkan membership sesi, perilaku koreksi count, validasi kuantitas fisik, dan empty scope secara eksplisit.

## Current contract

- Partial opname menggunakan satu rak; full opname mencakup seluruh toko.
- Snapshot diambil ketika item dihitung.
- Semua detail wajib dihitung sebelum finalisasi.
- Tidak ada cancel, abandoned, atau delete endpoint.

## Proposed contract

- Saat sesi dibuat, detail diisi dari item aktif dan non-deleted yang berada dalam scope.
- Membership sesi dibekukan. Item baru serta perubahan rak/status setelah create tidak menambah atau menghapus detail.
- Save pertama mengunci `qty_sistem_at_count` dan `counted_at`. Save berikutnya hanya boleh mengoreksi `qty_fisik` dan `note`.
- `qty_fisik` adalah integer non-negatif; nilai nol valid.
- Scope tanpa item valid ditolak dengan HTTP 422 dan tidak membuat sesi.

## Migration impact

- Tidak ada perubahan pada nama tabel atau kolom DDD.
- Tambahkan check constraint untuk pasangan scope/rack, status/timestamp, konsistensi tiga field count, dan kuantitas fisik non-negatif.
- Tambahkan index untuk conflict lookup dan histori sesi.

## Backward compatibility

- Perubahan bersifat additive pada schema dan API v1.
- Tidak ada endpoint, enum, atau field lama yang diubah.
- Rollback aplikasi boleh mempertahankan tabel opname; migration `down` hanya untuk environment uji atau setelah backup terverifikasi.

## Test impact

- Test membership beku, correction/retry snapshot, empty scope, zero quantity, scope conflict, time-aware reconciliation, completed immutability, dan multi-process finalize wajib tersedia.
