# Remote CI dan Full Baseline

Tanggal eksekusi: 2026-08-22. SHA gate: `13ec9fde4b4e992250bd550a02f4887e7604c5e3`.

- CI utama: run [`32571655689`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32571655689), sukses.
- F9A Hardening Baseline manual: run [`32571664915`](https://github.com/aulaHamidin/InventoryPos-Laravel12/actions/runs/32571664915), sukses.
- Runner: GitHub-hosted Ubuntu, MySQL 8.4, Redis, PHP 8.3, tiga worker queue, dan k6 2.1.0.

## Full load baseline

Profile remote menjalankan 10 tenant × 2.000 item aktif × 2 kasir, 20 VU, 2 menit ramp-up, 10 menit steady-state, dan 1 menit drain.

| Gate | Hasil remote | Batas |
|---|---:|---:|
| Checks | 15.191/15.191 lulus | 100% |
| HTTP requests | 15.195 | Informasi |
| Valid-request error rate | 0% | <1% |
| Unexpected 3xx/4xx/5xx | 0/0/0 | 0 |
| Login p95 | 87,21 ms | ≤2.000 ms |
| Dashboard operational-ready p95 | 134,02 ms | ≤2.000 ms |
| Item search/scan/status p95 | 96,34 ms | ≤750 ms |
| Checkout p95 | 91,39 ms | ≤1.500 ms |
| Payment p95 | 107,88 ms | ≤1.500 ms |

Profile konflik menghasilkan 12/12 checks, masing-masing tepat satu expected `409`, `422`, dan `429`, `scenario_errors=0`, serta `unexpected_5xx=0`.

Rekonsiliasi sepuluh tenant lulus dengan seluruh indikator corruption, duplicate, actor mismatch, cross-tenant mismatch, negative stock, queue depth, dan failed job bernilai 0. Queue profile menyelesaikan 500 analytics job dan 5 export melalui tiga worker dalam 6,23 detik; queue akhir kosong, failed job 0, dan cross-tenant output 0.
