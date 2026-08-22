# Draft Runbook Incident dan Eskalasi

Status: **DRAFT F9A — VALIDASI OPERASIONAL WAJIB F9B**

## Severity

- P0: tenant/authorization bypass, credential disclosure, unauthenticated sensitive access, data loss, atau corruption stok/ledger/payment. Hentikan traffic, panggil incident owner, dan mulai containment segera.
- P1: rate-limit lemah, workflow operasional persisten terblokir, latency gate gagal, atau security misconfiguration tanpa eksploit aktif. Wajib owner, mitigasi, target, dan retest date.
- P2: defense-in-depth atau usability yang tidak memblokir operasi.
- P3: dokumentasi/cosmetic/backlog rendah.

## Alur respons

1. Buat incident ID, timestamp Asia/Jakarta, reporter, environment, release SHA, scope tenant, dan gejala; jangan salin secret atau data pelanggan.
2. Triage dampak dan severity. Untuk P0, blok traffic terkait, revoke credential/token bila relevan, hentikan mutation worker, dan pertahankan evidence.
3. Bentuk timeline, hipotesis, dan langkah reproduksi dengan data sintetis. Gunakan correlation ID F12 ketika tersedia.
4. Pilih mitigasi paling kecil: feature isolation, queue pause, credential rotation, rollback aplikasi, atau restore terisolasi.
5. Validasi tenant isolation, auth, data reconciliation, queue, health, dan alert sebelum recovery.
6. Tutup hanya setelah root cause, corrective action, owner, due date, regression test, dan post-incident review dicatat.

Kontak/escalation routing aktual belum boleh diisi dengan data pribadi di repository; F12 menyediakan routing melalui environment secret dan F9B membuktikan delivery nyata.
