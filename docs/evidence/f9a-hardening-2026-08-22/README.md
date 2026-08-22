# Evidence F9A Hardening — 2026-08-22

Status: **LOCAL GATE LULUS — REMOTE CI DAN WORKFLOW MANUAL BELUM DIEKSEKUSI**

Baseline kontrak: `ac6b7bf7ab630ba061b69a37a816804152b7695b`

Evidence ini hanya memuat data sintetis dan ringkasan tersanitasi. Manifest fixture di `storage/framework/testing/` berizin `0600`, diabaikan Git, dan dilarang disalin ke evidence atau artifact.

## Environment fingerprint lokal

- OS runner: WSL Ubuntu 24.04 pada Docker Desktop.
- PHP 8.3.33; Composer 2.10.2; MySQL 8.4.11; Redis 8.10.0.
- Node lokal 20.20.2/npm 10.8.2 untuk authoring; CI dikunci Node 24.
- Docker Engine 29.5.3.
- k6: `grafana/k6:2.1.0`.
- Playwright: lockfile project; Chromium dan Firefox.
- Database: `inventori_q_hardening`, khusus local/testing.

## Evidence index

| Area | File | Status |
|---|---|---|
| Security review | `security-review.md` | Local review/test lulus |
| Dependency/secret scan | `dependency-security.md` | Local final scan lulus |
| Load/reconciliation | `load-smoke.md` | Smoke lokal lulus |
| Full load baseline | `load-baseline.md` | 10 tenant/20 VU lokal lulus |
| Concurrency/idempotency | `concurrency.md` | Regression dan Redis runtime lokal lulus |
| Query profile | `query-profile.md` | 200/2.000 constant-count lulus |
| Queue profile | `queue-profile.md` | 500 analytics/5 export lokal lulus |
| Browser/device | `browser-matrix.md` | Chromium/Firefox lokal lulus |
| Runbook review | `runbook-review.md` | Draft lengkap; runtime ditunda F9B |
| Findings/severity | `findings.md` | P0/P1 lokal nol; final setelah remote gate |

Full baseline lokal 10 tenant × 2.000 item × 20 VU telah lulus. Full remote CI dan workflow manual `F9A Hardening Baseline` tetap menjadi blocker penutupan F9A. Tidak ada klaim deployment, backup schedule, restore drill, RPO/RTO aktual, worker/scheduler production, atau alert delivery nyata di evidence ini.
