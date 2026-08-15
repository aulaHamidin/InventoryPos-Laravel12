# Acceptance Gate Fase 6 — POS Lengkap & Pembayaran Manual Non-Tunai

Tanggal eksekusi lokal: 15 Agustus 2026

Status: **LOCAL DAN VISUAL LULUS — CI REMOTE MENUNGGU COMMIT IMPLEMENTASI**

## Revision

- Baseline: `daefc43 Record-phase-5-CI-gate-closure`.
- Document Delta: `saas-contract-final-v1/document-delta-f6-pos-manual-payment.md`.
- Implementation plan: `saas-contract-final-v1/implementation-plan-fase-6.md`.

## Contract decision

- POS hanya memiliki `cash`, QRIS statis/manual, dan transfer manual.
- Tidak ada route, config, dependency, secret, adapter, webhook, polling, dynamic QR, atau placeholder Midtrans POS.
- Midtrans tetap disebut hanya untuk Billing Fase 11.
- Pending checkout memiliki TTL inklusif 24 jam dan command `pos:expire-pending` berjalan setiap menit tanpa overlap.
- Web Bluetooth beta tetap nonaktif secara default; printer fisik belum divalidasi dan bukan blocker F6.

## Release blocker

1. QRIS manual + stok tersedia → completed: **PASS**.
2. Transfer manual + stok tersedia → completed: **PASS**.
3. Dana diterima + stok gagal → refund_required tanpa sale movement: **PASS**.
4. Duplicate manual confirmation → satu payment/movement: **PASS**.
5. Concurrent cash vs manual → tepat satu diterapkan: **PASS**.
6. Void cash/QRIS/transfer → full refund obligation: **PASS**.
7. Partial return → cumulative exact refund dan due: **PASS**.
8. Bluetooth unsupported → print dialog/PDF fallback: **PASS**.
9. Pending melewati TTL → expired dan tidak dapat dibayar: **PASS**.
10. Histori/export/receipt menampilkan metode yang benar: **PASS**.

## Automated dan quality gate

- Full PHP suite: **84 test / 474 assertion**, lulus.
- Node printing suite: **5 test**, lulus.
- Migration fresh, rollback F6, upgrade dari baseline F5, dan command expiry: lulus.
- Multi-process concurrency, API, tenant/security, report/export, XSS, formula injection, dan Fase 0–5 regression: lulus.
- `npm run build`, `vendor/bin/pint --test`, `composer validate --strict`, `composer check-platform-reqs`, `php artisan view:cache`, `php artisan route:list`, dan `php artisan schedule:list`: lulus.
- CI sekarang menjalankan `npm test` sebelum build.

## Visual walkthrough

- Desktop: 1440 × 900.
- Mobile: 390 × 844.
- Cash, QRIS, transfer, receipt, history/method summary, filter, transaction detail, return/refund-required, refund selesai, expired/unpaid, export filter, dan print fallback tervalidasi.
- Evidence: [`evidence/f6-pos-manual-payment-2026-08-15/`](evidence/f6-pos-manual-payment-2026-08-15/).

## Pending closure gate

- Commit dan push implementasi.
- GitHub Actions implementation run harus `success`.
- Setelah itu master checklist Fase 6 dan status acceptance diubah menjadi selesai melalui closure commit terpisah.
