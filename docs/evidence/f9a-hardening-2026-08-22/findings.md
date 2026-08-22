# Findings dan Severity Register

Status: **REGISTER FINAL F9A — LOCAL DAN REMOTE GATE LULUS**

| ID | Severity | Temuan | Keputusan/mitigasi | Owner | Target/retest |
|---|---|---|---|---|---|
| F9A-LOCAL-001 | P2 | Jalur Docker Desktop ke WSL melalui `host.docker.internal` mengalami dial timeout pada baseline 20 VU, sehingga run pertama tidak valid. | Run dibatalkan; database hardening di-reset; app dan k6 ditempatkan pada network Docker yang sama. CI Linux native tetap memakai host network. | Engineering | Retest baseline lokal 2026-08-22: lulus |
| F9A-LOCAL-002 | P2 | Iterasi awal queue profiler memakai Redis queue namespace yang sama dengan worker development; dua export fixture diambil worker tersebut dan gagal sebelum akses data karena tenant fixture tidak ada pada database development. | Dua record gagal sintetis dihapus berdasarkan UUID persis; tidak ada export/data bisnis berubah. `REDIS_PREFIX` unik diwajibkan oleh command dan seluruh runtime CI. | Engineering | Retest 500 analytics + 5 export 2026-08-22: lulus 21,21 detik, failed job 0 |
| F9A-LOCAL-003 | P2 | Koreksi clock WSL/Docker Desktop menghasilkan minimum negatif sporadis pada custom timing k6 dan output durasi migration, tanpa error HTTP atau kegagalan rekonsiliasi. | Gate memakai p95, error counter, server result, dan rekonsiliasi; hasil minimum tidak dipakai sebagai acceptance metric. Workflow manual Linux native mengonfirmasi baseline. | Engineering | Local dan remote p95/reconciliation 2026-08-22 lulus |

P0 terbuka: 0. P1 terbuka: 0 berdasarkan seluruh gate lokal dan remote final.
