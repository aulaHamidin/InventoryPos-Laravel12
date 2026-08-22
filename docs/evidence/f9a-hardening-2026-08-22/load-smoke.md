# Load Smoke dan Rekonsiliasi

Tanggal eksekusi: 2026-08-22. Profile: 2 tenant × 200 item × 2 kasir, total 5 VU, 60 detik.

| Gate | Hasil |
|---|---:|
| Valid request checks | 222/222 lulus |
| Unexpected 5xx | 0 |
| Login p95 | 188,54 ms |
| Item search/scan/status p95 | 135,83 ms |
| Checkout p95 | 147,71 ms |
| Payment p95 | 185,00 ms |
| Valid-request error rate | 0% |
| Stok negatif | 0 |
| Stock/ledger mismatch | 0 |
| Duplicate payment | 0 |
| Duplicate sale movement | 0 |
| Cashier/movement/item tenant mismatch | 0 |
| Failed jobs | 0 |
| Queue analytics/export akhir | 0/0 |

Credential/token tidak ditulis ke output. Profile ini juga membuktikan status transaksi milik kasir melalui fixture pending yang tenant-scoped. Full latency baseline lokal dicatat terpisah; workflow manual remote `F9A Hardening Baseline` tetap menjadi gate penutupan.
