# Development Roadmap & Milestones
# Smart POS & Manajemen Stok — SaaS Ritel Komoditas Padat SKU

## 1. Prinsip Roadmap

Urutan dibangun berdasarkan dependency dan risiko:

1. Integritas data.
2. Core inventory.
3. POS.
4. Operasional toko.
5. Payment.
6. Analytics.
7. Staff dan multi-kasir.
8. Hardening pre-deploy.
9. SaaS billing dan Admin pusat.
10. Self-service.
11. Observability code-complete.
12. Deployment pilot, recovery, dan runtime acceptance.
13. Public release.

Tidak ada fase dianggap selesai hanya karena kode berjalan. Acceptance Criteria + test contract wajib terpenuhi.

---

## 2. Fase 0 — Fondasi

### Scope

- Laravel 12.
- Docker/Sail.
- MySQL.
- Redis.
- Auth.
- tenant context.
- base policies.
- audit foundation.
- CI.
- pemisahan guard dan shell panel tenant `/app` dari platform `/admin`;
- provisioning awal super-admin tanpa kredensial default.

### Acceptance

- test suite berjalan;
- tenant context tersedia;
- unauthorized access ditolak;
- migration reproducible.

---

## 3. Fase 1 — Master Data & Inventory Ledger

### Scope

- categories;
- racks;
- suppliers;
- items;
- item_suppliers;
- stock movements;
- average cost;
- ownership guard.

### Wajib test

- tenant isolation;
- ownership violation;
- stock race;
- multi-item lock ordering;
- MAC unit test;
- preferred supplier concurrency.

### DoD

Tidak ada UI yang dapat mengubah stock langsung.

---

## 4. Fase 2 — Smart POS Dasar — MVP

### Scope

- barcode;
- cart;
- server price calculation;
- line discount;
- cash payment;
- pos_payments baseline untuk cash;
- POS transaction;
- sale movement;
- receipt basic.

### Acceptance

- concurrent sale aman;
- duplicate checkout aman;
- stock tidak dapat menjadi negatif jika policy false;
- transaction history immutable;
- total tidak dipercaya dari client.

### MVP release gate

Owner-only.

Billing masih admin-managed.

Tenant onboarding masih manual.

---

## 5. Fase 3 — Reports & Export

### Scope

- stock report;
- movement history;
- POS history;
- PDF;
- Excel;
- print.

### Acceptance

Semua report tenant-scoped.

Staff tidak melihat financial data.

---

## 6. Fase 4 — Low Stock & Shopping

### Scope

- low stock alert;
- shopping list;
- item suppliers;
- preferred supplier;
- receive purchase → stock in.

### Acceptance

- preferred supplier max one;
- unknown supplier explicit;
- shopping list cannot submit without supplier;
- receiving creates stock movement.

---

## 7. Fase 5 — Cycle Counting

### Scope

- partial rack count;
- full count;
- time-aware snapshot;
- finalize adjustment.

### Acceptance

- partial different racks can run simultaneously;
- same rack cannot overlap;
- full conflicts with partial;
- time-aware reconciliation test passes;
- all details required before finalize.

---

## 8. Fase 6 — POS Lengkap & Pembayaran Manual Non-Tunai

### Scope

- perluasan `pos_payments` Fase 2 untuk `qris|transfer`, metadata konfirmasi, dan refund;
- QRIS statis dan transfer manual;
- manual payment idempotency/unique-key race;
- pending checkout expiry 24 jam;
- refund_required;
- manual refund marking;
- void;
- partial return;
- histori, receipt, laporan, dan export per metode;
- Bluetooth print;
- print fallback.

### Release blocker tests

1. Manual QRIS paid, stock remains available → completed.
2. Manual transfer paid, stock remains available → completed.
3. Payment confirmed, stock disappears → refund_required.
4. Duplicate confirmation → one payment/movement.
5. Concurrent cash vs manual payment → exactly one applied.
6. Void all methods → full refund obligation.
7. Partial return → cumulative exact refund and correct due.
8. Bluetooth unsupported → print dialog/PDF fallback.
9. Pending transaction passes TTL → expired and cannot be paid.
10. History/export/receipt show the correct method.

---

## 9. Fase 7 — Analytics & Smart Threshold

### Scope

- net POS demand dari `sale - sale_void - customer_return`, clamp minimum nol;
- half-open window 30×24 jam dalam zona waktu `Asia/Jakarta`;
- persisted `unclassified|fast|normal|slow|dead` dengan dead override;
- Smart Threshold dari effective preferred-supplier/item lead time dan safety days;
- after-commit recalculation, relevant-config recalculation, dan daily sweep;
- Owner dashboard recommendations dan backend-powered preview/apply.

### Acceptance

- CD-7.1 ditutup oleh `document-delta-f7-analytics-smart-threshold.md` sebelum coding.
- Pure calculator menguji semua numeric/classification boundary tanpa DB.
- Item belum berumur penuh 30×24 jam tetap `unclassified`; endpoint menghasilkan `422 INSUFFICIENT_HISTORY` dan zero mutation.
- Query boundary awal inklusif dan akhir eksklusif terbukti dalam `Asia/Jakarta`.
- Hanya movement POS yang dikontrak membentuk demand; movement operasional lain dikecualikan.
- `dead_stock_days=0`, eligible no-movement, preferred lead time nol, fallback, dan manual/auto threshold teruji.
- Recalculation tidak mengubah stock, average cost, atau immutable movement ledger.
- Endpoint, dashboard, policy, tenant isolation, no-N+1, dan responsive visual states lulus.
- Staff tidak diaktifkan oleh Fase 7; kontrak read-only operasional baru berlaku setelah Fase 8.

---

## 10. Fase 8 — Staff & Multi-Kasir

### Scope

- staff role;
- cashier access;
- visibility restrictions;
- concurrent POS.

### Acceptance

Staff cannot access:

- purchase cost;
- margin;
- inventory value;
- profit;
- billing;
- staff management.

Staff can operate POS.

CD-8.1 mengunci: Owner-provisioned password, active/auth-version revocation, transaksi kasir sendiri, diskon existing, inventory read-only, idempotency tetap unique per tenant, serta projection bebas purchase cost. Redis-backed session revocation dan multi-process multi-kasir menjadi release blocker Fase 8.

---

## 11. Fase 9 — Hardening Pre-Deploy & Pilot Split

CD-9.1 mengunci F9A sebagai entry gate F10 dan menunda F9B sampai seluruh kode F0–F12 code-complete.

### F9A — Hardening Pre-Deploy

- load/concurrency test lokal/CI;
- security review dan dependency audit;
- query/queue profiling;
- browser/device matrix lokal;
- draft deployment, rollback, incident, backup, restore, dan support runbook.

### F9A acceptance

- status **HARDENING PRE-DEPLOY SELESAI**;
- no unresolved P0 security/data/financial issue;
- seluruh P1 memiliki mitigasi dan keputusan eksplisit;
- CI utama hijau;
- Fase 9 belum ditandai selesai.

### F9B — Setelah F12 Code-Complete

- deployment pilot production-like dan non-public;
- migration/backfill tertunda;
- worker/scheduler/queue/webhook/storage health;
- backup terjadwal, restore drill, serta RPO/RTO aktual;
- integrated F0–F12 regression dan pilot;
- runtime acceptance F12.

Fase 9 dan Fase 12 baru selesai setelah status **F9B/RUNTIME ACCEPTANCE SELESAI** tercapai.

---

## 12. Fase 10 — Billing MRR & Admin Pusat

Fase ini melanjutkan shell `/admin` dari Fase 0 dengan resource operasional platform. Panel tenant tetap terisolasi di `/app`.

Entry gate: F9A selesai, tidak ada P0, setiap P1 memiliki mitigasi/keputusan eksplisit, dan CI utama hijau.

### MVP billing capability

- plan;
- subscription;
- invoice;
- manual payment;
- admin verification;
- subscription state machine;
- subscription events.

### Acceptance

- trial 14 days;
- `ends_at` never null;
- billing status independent from operational ban;
- audit semua subscription mutation.

---

## 13. Fase 11 — Self-Service Onboarding & Automated Billing

### Scope

- self-registration;
- OTP;
- trial eligibility check;
- Midtrans billing;
- billing webhook;
- automatic activation;
- trial abuse invariant.

Provider OTP dan Midtrans wajib diuji pada sandbox nyata. HTTPS local tunnel diperbolehkan sebelum deployment; secret dan URL tunnel aktif tidak boleh masuk repository/evidence.

### Acceptance

- previously trialed phone cannot receive second trial;
- duplicate webhook safe;
- out-of-order billing events safe;
- payment creates exactly one subscription transition;
- no manual approval required for normal onboarding.

---

## 14. Fase 12 — Observability Code-Complete & Runtime Acceptance

### Scope

- application monitoring;
- queue monitoring;
- webhook monitoring;
- alerting;
- audit review;
- backup verification.

### Gate

- Setelah F11, implementasi dan simulasi lokal/CI dapat memperoleh status **OBSERVABILITY CODE-COMPLETE**.
- Fase 12 belum selesai sampai health deployment, worker/scheduler, alert delivery, backup/restore, dan runtime evidence lulus bersama F9B.

---

## 15. Fase 13 — Public v1

Public v1 hanya setelah:

- staff;
- QRIS;
- return/void/refund;
- cycle counting;
- analytics;
- billing;
- self-service;
- observability;
- security;
- F9B deployment pilot;
- recovery test dan runtime acceptance F12

lulus.

---

## 16. Definition of Done Global

Sebuah fase selesai jika:

- Acceptance Criteria lulus;
- automated tests lulus;
- tenant isolation lulus;
- relevant concurrency tests lulus;
- API contract updated;
- UI states lengkap;
- audit events tersedia;
- documentation synchronized;
- no undocumented business rule exists.
