# Document Delta Fase 9 — Hardening Pre-Deploy & Pilot Split

Status: **DISETUJUI**

Decision ID: **CD-9.1**

Tanggal: 2026-08-22 (`Asia/Jakarta`)

Baseline: merge Fase 8 `98e1d8775265ab7e00e8cd29c2f7fd8148aabf98`

## 1. Document Delta Declaration

**Affected documents**

- `development-roadmap.md`;
- `implementation_plan.md`;
- `master-plan-fase-5-12.md`;
- `docs/f8-acceptance.md` (handoff ke F9A);
- `.agents/AGENTS.md`.

**Reason**

Gate backup/restore, RPO/RTO, health runtime, dan pilot pada Fase 9 memerlukan environment deployment. Production deployment sengaja ditunda sampai seluruh kode F0–F12 selesai, sedangkan domain Fase 10–11 dapat dibangun dan diverifikasi sebelum public release.

**Current contract**

- Urutan repository mewajibkan Fase 9 selesai penuh sebelum Fase 10.
- Fase 9 mencampur hardening lokal/CI dengan deployment pilot.
- Fase 12 hanya memiliki satu status selesai walaupun sebagian gate hanya dapat dibuktikan pada runtime deployment.

**Proposed contract**

Urutan normatif v1 menjadi:

```text
F9A Hardening Lokal/CI
→ F10 Billing & Admin Pusat
→ F11 Self-Service & Automated Billing
→ F12 Observability Code-Complete
→ deployment pilot non-public
→ F9B + F12 Runtime Acceptance
→ Public v1
```

**Migration impact**

Tidak ada migration, schema, enum, index, backfill, atau perubahan data pada increment kontrak ini.

**Backward compatibility**

Tidak ada perubahan API, response, business state, permission, atau UI runtime. Nomor Fase 10–12 tidak berubah. Fase 9 dipisah sebagai gate pelaksanaan tanpa mengganti kontrak fitur.

**Test impact**

Test F9A dijalankan melalui local Docker dan CI. Gate yang memerlukan environment production-like dipindahkan ke F9B. Regression F0–F8 tetap wajib pada F10–F12, dan regression terintegrasi F0–F12 wajib diulang pada F9B.

## 2. Status dan Urutan Normatif

- Baseline F9A adalah merge F8 `98e1d8775265ab7e00e8cd29c2f7fd8148aabf98`.
- F9A yang lulus diberi status exact **HARDENING PRE-DEPLOY SELESAI**. Status ini bukan penyelesaian Fase 9.
- Fase 10 hanya boleh dimulai setelah F9A lulus, tidak ada P0 terbuka, seluruh P1 memiliki mitigasi/keputusan eksplisit, dan CI utama hijau.
- Fase 11 tetap bergantung pada Fase 10.
- Setelah Fase 11, Fase 12 diimplementasikan dan dapat diberi status exact **OBSERVABILITY CODE-COMPLETE**. Status ini bukan penyelesaian Fase 12.
- Deployment pilot non-public baru dilakukan setelah seluruh kode F0–F12 code-complete.
- Fase 9 dan Fase 12 hanya ditandai selesai setelah status exact **F9B/RUNTIME ACCEPTANCE SELESAI** tercapai.
- Public v1 dilarang sebelum F9B dan seluruh runtime acceptance F12 lulus.

## 3. F9A — Hardening Pre-Deploy

F9A mencakup pekerjaan yang dapat dibuktikan tanpa environment deployment:

- load/concurrency test versioned untuk login/session, item search/scan, POS checkout/payment/idempotency, stock race, queue, dan Redis session revocation;
- security review tenant isolation, ownership, authorization, mass assignment, CSRF/session, private download, secret management, log redaction, dan dependency audit;
- profiling query/queue dengan environment dan batas test tercatat;
- browser/device matrix lokal untuk desktop Chromium/Firefox, mobile Chromium, viewport tablet, scanner keyboard-wedge, dan printer fallback; Safari/iOS hanya bila perangkat tersedia;
- draft runbook deployment, rollback, incident, backup, restore, dan support.

F9A tidak mencakup dan tidak boleh mengklaim:

- backup terjadwal pada environment pilot;
- restore drill production-like;
- RPO/RTO aktual;
- health worker/scheduler atau alert pada deployment nyata;
- pilot operasional.

F9A lulus jika evidence lokal/CI lengkap, CI hijau, tidak ada P0, dan seluruh P1 mempunyai mitigasi serta keputusan eksplisit.

## 4. Gate F10, F11, dan F12 Code-Complete

- F10 memakai SHA penutupan F9A sebagai baseline implementasinya dan tetap memerlukan CD-10.1 serta implementation plan tersendiri.
- F11 tetap memerlukan F10 selesai, CD-11.1, Owner 2FA policy, serta credential OTP dan Midtrans sandbox.
- Sandbox F11 boleh diuji dari local environment melalui HTTPS tunnel. URL tunnel dan seluruh credential bersifat environment-only dan dilarang masuk repository, log, screenshot, atau evidence.
- F11 hanya boleh ditutup setelah alur provider sandbox nyata untuk pending, paid, failed/expired, duplicate, out-of-order, invalid signature, dan reconciliation lulus; fake-only tidak cukup.
- F12 code-complete mencakup Horizon, health checks, correlation ID, alert routing, audit review, backup verification, retention, redaction, dan simulasi lokal/CI.
- Runtime worker/scheduler, backup, restore, alert delivery, dan health deployment tetap terbuka sampai F9B.

## 5. F9B — Deployment Pilot dan Runtime Acceptance

Setelah F12 code-complete, deploy artifact yang sama ke environment pilot production-like dan non-public. F9B wajib:

- menjalankan migration dan backfill tertunda F7/F8 serta migration F10–F12;
- memverifikasi aplikasi, database, Redis, queue, worker, scheduler, webhook, private storage, dan health checks;
- menjalankan backup terjadwal dan restore ke environment terisolasi dengan checksum/record sampling dan smoke test;
- mencatat RPO/RTO aktual;
- mengulang load, concurrency, security, billing, onboarding, browser/device, dan workflow operasional F0–F12;
- membuktikan alert delivery, failed-job recovery, webhook reconciliation, dan backup failure/success behavior;
- memakai data sintetis secara default; data toko nyata hanya dengan persetujuan eksplisit dan scope yang disetujui;
- menutup seluruh P0 serta mencatat mitigasi/keputusan untuk P1 yang diterima.

Public launch menggunakan artifact yang telah lulus pilot. Jika environment public terpisah, artifact immutable yang sama dipromosikan tanpa perubahan source.

## 6. Acceptance dan Evidence

- F9A mempunyai acceptance record dan evidence sendiri tanpa mencentang Fase 9 selesai.
- F10, F11, dan F12 code-complete mencatat SHA, CI run, test, sandbox, visual, serta known limitation masing-masing.
- F9B mencatat SHA artifact, environment/runtime version, migration/backfill, queue/worker/scheduler health, backup/restore, RPO/RTO, alert, regression, pilot, severity register, dan keputusan pass/fail.
- Evidence dilarang memuat `.env`, secret, token, OTP nyata, webhook signature, payment credential, data toko nyata tanpa izin, atau URL tunnel yang masih aktif.

## 7. Rollback Kontrak

Delta ini documentation-only dan dapat dibatalkan tanpa rollback database. Pembatalan setelah F10 dimulai harus tetap mempertahankan seluruh evidence F9A dan tidak boleh menandai Fase 9 selesai tanpa menyelesaikan gate deployment/pilot lama.
