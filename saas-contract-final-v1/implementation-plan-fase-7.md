# Implementation Plan Fase 7 — Analytics & Smart Threshold

Status: **DISETUJUI UNTUK IMPLEMENTASI**

Baseline: commit `e72ef07` — CD-7.1 disetujui dan seluruh source of truth tersinkron.

Document Delta: [`document-delta-f7-analytics-smart-threshold.md`](document-delta-f7-analytics-smart-threshold.md)

## 1. Scope dan boundary

- Business time analytics selalu `Asia/Jakarta`; storage aplikasi tetap UTC dan konversi hanya dilakukan query adapter.
- Net POS demand memakai `max(0, sale - sale_void - customer_return)` pada half-open window.
- Pure calculator mengunci eligibility, `unclassified|fast|normal|slow|dead`, dan exact integer threshold tanpa database.
- Fase 7 hanya Owner; Staff dan endpoint analytics summary tetap di luar scope.
- Tidak ada forecasting, perubahan stok langsung, atau mutation immutable ledger.

## 2. Schema dan domain

- Migration additive memperluas enum class, menambah calculation timestamp dan index analytics, lalu menormalkan baseline class/mode tanpa mengubah nilai threshold atau stok.
- `AnalyticsClock`, immutable DTO, calculator, snapshot query, preview/recalculate/apply Actions memakai satu formula canonical.
- Generic item mutation tidak dapat mengaktifkan auto threshold atau mengubah persisted analytics fields.
- Apply dan recalculation mengunci item; mode manual memperoleh class/timestamp tanpa perubahan `stok_minimal`.
- Audit canonical: `analytics.smart_threshold_applied`, `analytics.recalculated`, dan `tenant.analytics_settings_updated`.

## 3. Trigger, queue, dan schedule

- Sale, void, customer return, item analytics config, preferred supplier lifecycle, dan tenant dead-days men-dispatch recalculation setelah commit.
- Item job memakai queue `analytics`, `ShouldBeUniqueUntilProcessing`, key `tenant:item`, TTL 300 detik, dan tenant context eksplisit.
- Daily sweep berjalan pukul 00:15 `Asia/Jakarta`, single-server, tanpa overlap 180 menit, serta memproses item aktif per chunk.
- Command recalculate/status menyediakan backfill, preflight distributed lock, queue depth, failed job, dan completion checks.

## 4. API dan UI

- `POST /api/v1/items/{id}/smart-threshold` strict tiga-field, Owner-only, tenant-safe, dan menghasilkan breakdown canonical atau zero-mutation `422 INSUFFICIENT_HISTORY`.
- Preview Filament hanya berjalan setelah tombol **Hitung Preview**; Apply selalu menghitung ulang di transaction.
- Owner memperoleh halaman Pengaturan Analytics untuk `dead_stock_days`.
- Dashboard mengikuti critical stock → shopping recommendation → class insight → operational summary dan menampilkan populated/unclassified/no-movement/dead/loading/error state.

## 5. Release blocker

1. Boundary waktu, 7/8, 29/30, dead override, clamp, excluded movements, dan ceil lulus tanpa DB.
2. Fresh/upgrade/rollback migration menjaga threshold, stok, average cost, dan ledger.
3. API sukses/422/idempotency/permission/tenant isolation/audit lulus.
4. Seluruh trigger after-commit dan rollback behavior lulus.
5. Redis runtime membuktikan unique job, follow-up saat processing, dan distributed scheduler locks.
6. Manual threshold tidak pernah ditimpa; auto threshold menggunakan preferred zero/fallback dengan benar.
7. Dashboard query count konstan dan tidak membocorkan data finansial.
8. Backfill habis, eligible-unclassified/null timestamp nol, dan failed analytics jobs nol.
9. Desktop 1440×900 serta mobile 390×844 meliputi seluruh visual state.
10. Full local quality gate dan CI remote hijau.

## 6. Rollout dan rollback

- Deploy migration dan code, validasi Redis/worker/scheduler, jalankan `analytics:recalculate`, lalu tutup dengan `analytics:status --fail-on-incomplete`.
- Queue `analytics` harus kosong dan failed job harus dapat diamati/retry sebelum fase ditutup.
- Rollback enum bersifat lossy: `unclassified` kembali menjadi `normal`, baseline auto tidak dapat direkonstruksi, dan backup diperlukan untuk restore nilai pra-F7 secara persis.

## 7. Exit gate

- Migration fresh, baseline upgrade, rollback sandbox, unit/feature/Redis/concurrency/security tests lulus.
- `npm test`, `npm run build`, Pint, Composer checks, view cache, route list, dan schedule list lulus.
- Evidence disimpan di `docs/evidence/f7-analytics-YYYY-MM-DD/`; keputusan akhir dicatat di `docs/f7-acceptance.md` setelah CI remote lulus.

## 8. Detail calculator dan persistence yang dikunci

- `AnalyticsClock::now()` menghasilkan `CarbonImmutable` `Asia/Jakarta` pada presisi detik; `as_of` tidak pernah didefinisikan oleh UTC.
- Query adapter adalah satu-satunya lapisan yang mengubah `[as_of - duration, as_of)` ke UTC storage. Calculator tidak mengenal Eloquent, DB, atau UTC.
- Input/result calculator immutable. Net demand adalah `max(0, sale - sale_void - customer_return)`; class boundaries adalah `8` dan `30` unit; threshold memakai integer ceiling atas rational demand/30 tanpa float.
- Persistence mengunci item sebelum membaca ledger, tenant setting, dan preferred supplier. Batch mengurutkan item ID ascending.
- Eligible calculation memperbarui `movement_class` dan `analytics_calculated_at`; ineligible tetap `unclassified|null`. Mode manual tidak mengubah `stok_minimal`.
- Generic create menghasilkan `manual|unclassified`. Generic update tidak menerima class/timestamp, tidak dapat mengaktifkan auto, dan perubahan threshold item auto wajib sekaligus memilih manual.
- Audit canonical:
  - `analytics.smart_threshold_applied` — actor Owner;
  - `analytics.recalculated` — actor null/system dan tidak dibuat untuk timestamp-only refresh;
  - `tenant.analytics_settings_updated` — actor Owner.

## 9. Trigger matrix final

| Source Action | Kondisi dispatch setelah commit | Reason |
|---|---|---|
| `FinalizePosTransactionAction` | sale sukses; item ID unik | `sale` |
| `VoidPosTransactionAction` | `sale_void` committed | `sale_void` |
| `ReturnPosTransactionAction` | `customer_return` committed | `customer_return` |
| `UpdateItemAction` | lead time, safety days, atau mode berubah | `item_configuration_changed` |
| `UpsertItemSupplierAction` | lead time preferred berubah | `preferred_supplier_lead_time_changed` |
| `SetPreferredSupplierAction` | preferred baru committed | `preferred_supplier_set` |
| `UnsetPreferredSupplierAction` | preferred dilepas | `preferred_supplier_unset` |
| `DeleteItemSupplierAction` | link terhapus adalah preferred | `preferred_supplier_deleted` |
| `UpdateTenantAnalyticsSettingsAction` | `dead_stock_days` berubah | tenant-wide `dead_stock_days_changed` |
| `ApplySmartThresholdAction` | direct calculation dalam transaction | tidak bergantung queue |
| Daily sweep | aging/window/recovery | `daily_sweep` |

Event membawa tenant ID dan sorted item IDs serta memakai `ShouldDispatchAfterCommit`; rollback tidak menerbitkan job. Item inactive/deleted adalah safe no-op dan analytics tidak dapat me-rollback transaksi POS yang telah committed.

## 10. Queue, command, dan scheduler final

- Item job: `ShouldBeUniqueUntilProcessing`, queue `analytics`, `uniqueId=tenant_id:item_id`, `uniqueFor=300`, attempts `3`, bounded backoff `5/30/120` detik.
- Lock dilepas ketika processing dimulai agar event baru dapat membuat follow-up job. Tenant context selalu dibentuk dari payload.
- Tenant job melakukan `chunkById(200)` pada active item dan hanya mengantrekan item job, bukan satu transaction tenant besar.
- Worker memproses `exports,analytics,default`. Production `CACHE_STORE` wajib Redis atau distributed-lock driver yang disetujui; command/status melakukan production preflight.
- `analytics:recalculate` memiliki `--tenant` dan `--sync`. `analytics:status --fail-on-incomplete` melaporkan active/eligible/ineligible, eligible-unclassified, eligible timestamp-null, queue depth, failed jobs, last sweep, dan last job.
- Schedule dikunci ke `dailyAt('00:15')->timezone('Asia/Jakarta')->withoutOverlapping(180)->onOneServer()`.

## 11. API dan Owner UI final

- Satu endpoint publik: `POST /api/v1/items/{id}/smart-threshold`; request strict hanya `threshold_mode=auto_velocity`, `lead_time_days`, dan `safety_stock_days`.
- Ownership/Owner divalidasi sebelum calculation. Preferred supplier cross-tenant/corrupt fail-closed; preferred lead `0` mengalahkan fallback.
- Ineligible menghasilkan exact `422 INSUFFICIENT_HISTORY`, `eligible_at +07:00`, zero mutation, dan zero audit. Success response memakai `+07:00` serta `avg_daily_out` enam desimal.
- Preview Filament hanya berjalan lewat tombol **Hitung Preview**, bersifat sekali pakai, dan tidak menghitung saat mengetik. Apply menghitung ulang secara authoritative di dalam transaction.
- Owner mendapat halaman **Pengaturan Analytics** untuk integer non-negatif `dead_stock_days`; `0` menonaktifkan dead stock dan update memicu tenant refresh.
- Dashboard memakai query aggregate konstan dalam urutan critical → recommendation → class → operational, dengan populated, unclassified/progress, no-movement, dead, empty, loading, dan persistent error state.

## 12. Harness acceptance final

- Suite unit/feature mencakup boundary waktu, movement, formula, API/action, actor/audit, trigger/no-trigger, permission, tenant isolation, corrupt preferred, dashboard state, dan query-count invariance.
- Job CI `analytics-runtime` memakai MySQL + Redis cache/queue, cache prefix unik, dan worker nyata. Multi-process harness membuktikan duplicate coalescing, TTL 300, follow-up saat processing, after-commit/rollback, overlap mutex, dan single-server mutex.
- Migration harness memakai dua database sementara. Migration baseline diambil langsung dari tree commit `e72ef07`, data `normal|auto_velocity` dan immutable movement disemai, lalu migration F7 upgrade/rollback diverifikasi.
- Rollback bersifat lossy: `unclassified → normal`, mode auto baseline tidak dapat direkonstruksi, timestamp/index dihapus; backup wajib untuk restore nilai pra-F7 secara exact.
- Workflow CI menjalankan `schedule:list` selain fresh migration, test/build, Pint, Composer, view cache, dan route list.

## 13. Deployment verification final

1. Verifikasi Redis cache/queue dan distributed locking.
2. Verifikasi worker queue `analytics` dan scheduler aktif.
3. Verifikasi `schedule:list` menunjukkan sweep F7.
4. Jalankan `analytics:recalculate` dan tunggu queue `analytics` habis.
5. Observasi/retry failed jobs setelah akar masalah diperbaiki.
6. Jalankan `analytics:status --fail-on-incomplete`.

Fase baru boleh ditutup jika failed analytics job nol, eligible-unclassified nol, eligible timestamp-null nol, null timestamp hanya item belum eligible, worker/scheduler sehat, serta recovery sweep manual/daily terbukti.
