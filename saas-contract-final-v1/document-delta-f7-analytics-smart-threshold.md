# Document Delta — Fase 7 Analytics & Smart Threshold

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Tanggal: **16 Agustus 2026**

Document Delta ini menutup **CD-7.1**.

## Affected documents

- `prd-saas-manajemen-stok.md`
- `blueprint-saas-stok.md`
- `database-design-document.md`
- `software-architecture-document.md`
- `api-specification.md`
- `ui-ux-specification.md`
- `development-roadmap.md`
- `implementation_plan.md`
- `master-plan-fase-5-12.md`
- `.agents/AGENTS.md`

## Reason

Kontrak awal hanya menetapkan SMA 30 hari, fast/slow/dead, dan formula Smart Threshold. Kontrak tersebut belum mengunci boundary waktu, movement pembentuk demand, ambang kelas numerik, representasi item yang belum mempunyai histori penuh, prioritas dead stock, persistence, recalculation, response API, serta permission UI.

Tanpa delta ini, implementasi dapat menghasilkan klasifikasi berbeda untuk ledger yang sama, menganggap adjustment sebagai demand, atau menampilkan rekomendasi otomatis sebelum data mencukupi.

## Current contract

- Window movement 30 hari.
- `avg_daily_out = total_out_30_days / 30`.
- `threshold = ceil(avg_daily_out × (lead_time_days + safety_stock_days))`.
- Lead time preferred supplier menjadi prioritas dan `items.lead_time_days` menjadi fallback.
- History yang belum cukup menggunakan mode manual atau menampilkan recommendation unavailable.
- Enum `movement_class` hanya `fast|normal|slow|dead`.
- Endpoint publik yang tersedia adalah `POST /api/v1/items/{id}/smart-threshold`.

## Proposed contract

### Window dan eligibility

- Seluruh boundary bisnis kalkulasi memakai zona waktu `Asia/Jakarta` dan half-open window `[as_of - duration, as_of)`.
- Timestamp yang keluar melalui API memakai offset `+07:00`. Representasi penyimpanan internal tidak boleh mengubah definisi boundary bisnis `Asia/Jakarta`.
- Item eligible setelah `as_of >= items.created_at + 30×24 jam`.
- Item aktif yang belum eligible memakai `movement_class=unclassified`.
- Item baru tidak diprorata dan tidak diberi kelas velocity palsu.

### Net POS demand

```text
gross_sale = Σ qty movement sale
reversal = Σ qty movement sale_void + Σ qty movement customer_return
net_out = max(0, gross_sale - reversal)
avg_daily_out = net_out_30_days / 30
```

Movement `stock_in`, `stock_out`, `supplier_return`, `damaged`, `adjustment`, dan `opname_adjustment` tidak membentuk demand.

### Classification

Untuk item eligible:

| Kelas | Aturan |
|---|---|
| `fast` | `avg_daily_out >= 1.00` |
| `normal` | `0.25 <= avg_daily_out < 1.00` |
| `slow` | `0 <= avg_daily_out < 0.25` |
| `dead` | Override bila item cukup umur dan Net POS demand pada window `dead_stock_days` adalah nol |
| `unclassified` | Umur histori belum mencapai 30 hari |

Precedence:

1. history belum 30 hari → `unclassified`;
2. `dead_stock_days > 0`, umur item sekurangnya sepanjang dead window, dan net demand dead window nol → `dead`;
3. selain itu → `fast|normal|slow` berdasarkan SMA 30 hari.

`tenant.dead_stock_days=0` menonaktifkan kelas `dead`. Item eligible tanpa movement menghasilkan threshold `0` dan kelas `slow`, kecuali memenuhi aturan `dead`.

### Smart Threshold

```text
threshold = ceil(
    (net_out_30_days / 30)
    × (effective_lead_time_days + safety_stock_days)
)
```

- Preferred supplier dengan `lead_time_days` non-null, termasuk nol, menjadi sumber utama.
- Jika tidak tersedia, gunakan `items.lead_time_days`.
- `stok_minimal` hanya diperbarui otomatis untuk `threshold_mode=auto_velocity`.
- Mode manual tidak boleh ditimpa oleh event job atau daily sweep.

### Recalculation

- Dispatch setelah commit `sale|sale_void|customer_return` untuk item terdampak.
- Recalculate setelah preferred supplier, preferred lead time, item lead time, safety days, threshold mode, atau `tenant.dead_stock_days` berubah.
- Daily sweep menangani aging 30 hari, aging dead, window shift, dan recovery job yang tertinggal.
- `POST /items/{id}/smart-threshold` menghitung langsung dengan calculator backend yang sama.
- `movement_class` dan `analytics_calculated_at` diperbarui untuk seluruh item aktif yang eligible, terlepas dari threshold mode; `stok_minimal` tetap hanya berubah pada mode `auto_velocity`.
- Event job, sweep, dan endpoint harus memakai pure calculator/domain service yang sama dan bersifat idempotent.

### Permission

- Owner dapat melihat insight dan menerapkan Smart Threshold.
- Fase 7 tidak mengaktifkan login atau akses Staff.
- Setelah Fase 8, Staff hanya dapat membaca insight operasional yang bebas cost, margin, valuation, profit, dan billing sesuai Policy.

## Migration impact

Migration Fase 7 harus additive dan:

- memperluas `items.movement_class` menjadi `unclassified|fast|normal|slow|dead`;
- menjadikan `unclassified` default item baru;
- menambahkan `items.analytics_calculated_at` nullable;
- menambahkan index `items(tenant_id, is_active, movement_class)`;
- menambahkan index movement untuk `(tenant_id, item_id, movement_type, created_at)`;
- mengubah seluruh kelas baseline menjadi `unclassified` karena belum pernah dihitung oleh kontrak ini;
- mengubah record baseline `auto_velocity` menjadi `manual` tanpa mengubah `stok_minimal`;
- tidak mengubah stock, average cost, atau immutable movement ledger.

## API impact

Tidak ada endpoint summary baru. Endpoint tetap:

`POST /api/v1/items/{id}/smart-threshold`

Request hanya menerima:

- `threshold_mode=auto_velocity`;
- `lead_time_days` integer non-negatif;
- `safety_stock_days` integer non-negatif.

Tenant dan supplier ID tidak diterima. Jika history belum cukup, response adalah HTTP 422 `INSUFFICIENT_HISTORY` dengan `eligible_at`; semua perubahan dibatalkan.

Response sukses mengembalikan window, gross sale, reversal, net demand, average harian, sumber dan effective lead time, safety days, dead-window input, threshold, class, dan calculation time.

Input dan ledger yang sama menghasilkan business values yang sama. Audit hanya dibuat jika business fields benar-benar berubah.

## UI impact

- Dashboard Owner menampilkan populated, `unclassified`, no-movement, `dead`, loading, dan error state.
- `unclassified` menampilkan progress history dan tidak berpura-pura `normal`.
- Smart Threshold action menjelaskan sumber lead time dan hasil formula backend.
- Preview memakai calculator backend melalui internal Filament surface; frontend tidak menghitung formula.
- Staff tidak diaktifkan pada Fase 7.

## Backward compatibility

- Schema dan enum berubah secara additive melalui migration baru.
- Endpoint tetap `/api/v1`; response field baru bersifat additive.
- Existing manual `stok_minimal` dipertahankan.
- Baseline class direset ke `unclassified` untuk mencegah nilai lama/default dianggap hasil analytics.
- Tidak ada mutation pada stock atau ledger historis.

## Test impact

Future implementation wajib mencakup:

- boundary 29/30 unit dan 7/8 unit;
- umur tepat 30 hari;
- movement tepat pada awal/akhir half-open window;
- void/return dan clamp nol;
- seluruh movement yang dikecualikan;
- dead disabled, override, dan aging;
- eligible item tanpa movement;
- preferred lead time nol/null/cross-tenant;
- HTTP 422 tanpa mutation;
- identical request/ledger idempotency;
- after-commit job dan daily sweep;
- threshold manual tidak tertimpa;
- tenant isolation, ownership, dan audit;
- dashboard query tanpa N+1;
- visual desktop/mobile untuk populated, unclassified, no-movement, dead, loading, dan error.
- Fase 7 tetap menolak akses Staff; kontrak operational read-only baru diuji saat Fase 8 mengaktifkan Staff.

Pure calculation tests tidak boleh membutuhkan database.

## Approval record

- Diajukan dan disetujui oleh user pada **16 Agustus 2026**.
- Status keputusan: **DISETUJUI UNTUK IMPLEMENTASI**.
- CD-7.1: **DITUTUP**.
- Increment ini hanya mengesahkan kontrak; migration, calculator, endpoint, job, widget, dan test implementasi Fase 7 belum dibuat.
