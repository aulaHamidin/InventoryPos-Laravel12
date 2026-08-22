# Concurrency dan Idempotency

Status lokal: **LULUS**. Remote runtime tetap wajib sebelum penutupan.

Gate multi-process menggunakan MySQL serta Redis nyata dan mencakup:

- stok terakhir dan dua checkout concurrent dengan idempotency key sama;
- same-key retry, payload conflict, dan key lintas kasir;
- dua cash payment, dua manual confirmation, serta cash versus manual;
- payment versus expiry, void versus return, dan return versus return;
- dua Staff pada stok terakhir dengan verifikasi payment/movement/actor;
- preferred supplier, receiving Shopping List, dan rangkaian opname concurrent;
- Redis session revocation setelah reset/deactivate;
- analytics uniqueness, follow-up saat processing, dan dispatch after-commit/rollback.

Load reconciliation kemudian memeriksa stok-ledger, duplicate transaction/payment/movement, actor/cashier, dan seluruh relasi tenant transaction-line-payment-item. Expected `409`, `422`, dan `429` dijalankan pada profile konflik terpisah dari baseline request valid.

Hasil lokal final:

- full unit/feature termasuk concurrency: 124 lulus, 922 assertions, 6 runtime-only skipped pada suite cepat;
- analytics Redis runtime: 3 lulus, 25 assertions;
- Staff Redis/session/multi-kasir runtime: 2 lulus, 27 assertions;
- rate limiter Redis multi-process: 1 lulus, 18 assertions;
- rekonsiliasi full baseline: seluruh mismatch/duplicate/leakage/failed-job/queue-depth bernilai 0.
