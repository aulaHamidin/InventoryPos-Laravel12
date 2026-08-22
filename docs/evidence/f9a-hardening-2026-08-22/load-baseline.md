# Full Load Baseline Lokal

Tanggal eksekusi: 2026-08-22. Profile: 10 tenant × 2.000 item aktif × 2 kasir, 20 VU, 2 menit ramp-up, 10 menit steady-state, dan 1 menit drain.

Distribusi VU: 12 catalog/search/scan/status, 6 checkout/payment, dan 2 login/session/dashboard. Metode pembayaran mengikuti 60% cash, 20% QRIS, dan 20% transfer.

| Gate | Hasil | Batas |
|---|---:|---:|
| Checks | 13.908/13.908 lulus | 100% |
| HTTP requests / iterations | 13.912 / 10.631 | Informasi |
| Valid-request error rate | 0% | <1% |
| Unexpected 3xx/4xx/5xx | 0/0/0 | 0 |
| Login p95 | 140,68 ms | ≤2.000 ms |
| Dashboard operational-ready p95 | 210,72 ms | ≤2.000 ms |
| Item search/scan/status p95 | 154,68 ms | ≤750 ms |
| Checkout p95 | 163,69 ms | ≤1.500 ms |
| Payment p95 | 191,00 ms | ≤1.500 ms |

Profile konflik terisolasi menghasilkan 12/12 checks, masing-masing tepat satu expected `409`, `422`, dan `429`, `scenario_errors=0`, serta `unexpected_5xx=0`. Status konflik ini tidak dihitung sebagai keberhasilan request valid.

Rekonsiliasi setelah baseline lulus untuk 10 tenant: stok negatif, stock-ledger mismatch, duplicate checkout/payment/movement, actor/cashier mismatch, relasi cross-tenant, failed job, serta queue analytics/export akhir semuanya 0.

Catatan host: minimum negatif sporadis pada custom duration berasal dari koreksi clock WSL/Docker Desktop dan dicatat sebagai `F9A-LOCAL-003`. Acceptance memakai p95, error counter, response server, serta rekonsiliasi. Workflow manual remote Linux native tetap wajib sebelum F9A ditutup.
