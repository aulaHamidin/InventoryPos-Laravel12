# Query Profile

`Phase9QueryProfileTest` membandingkan dataset 200 dan 2.000 item. Dashboard/widget, item index/search/exact scan, checkout/payment, dan analytics menggunakan normalized signature/count budget versioned pada `tests/Performance/query-baseline-f9a.json`.

Hasil lokal: **1 test, 67 assertions, lulus**. Seluruh skenario menjaga query count dan normalized SQL shape konstan ketika volume naik.

| Operasi | Query 200 | Query 2.000 | Waktu 200/2.000 (ms) |
|---|---:|---:|---:|
| Item index/search/scan | 3/3/2 | 3/3/2 | 5,42/5,12/3,15 → 5,08/14,09/3,00 |
| Checkout/payment | 11/19 | 11/19 | 20,05/28,33 → 13,40/25,67 |
| Analytics | 1 | 1 | 1,23 → 1,18 |
| Dashboard widgets | 1–2 per widget | 1–2 per widget | 1,10–2,78 → 1,12–2,36 |

Durasi query dilaporkan sebagai informasi; gate latency authoritative berasal dari HTTP/browser. Artifact JSON menyertakan normalized SQL tanpa binding data.

Perubahan baseline count memerlukan review eksplisit. Kebutuhan index/schema baru memerlukan Document Delta sebelum migration.
