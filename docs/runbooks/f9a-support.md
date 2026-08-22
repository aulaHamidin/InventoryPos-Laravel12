# Draft Runbook Support dan Triage

Status: **DRAFT F9A — VALIDASI OPERASIONAL WAJIB F9B**

## Intake minimum

Minta waktu kejadian, halaman/aksi, role, tenant reference internal, correlation ID, expected/actual result, dan screenshot tersanitasi. Jangan meminta password, OTP, API token, cookie, signature, QRIS produksi, atau dump database.

## Triage

1. Klasifikasikan auth/access, POS/payment, stock/ledger, analytics/queue, report/export, billing/webhook, performance, atau UI/browser.
2. Tentukan severity memakai runbook incident. Tenant leakage, credential disclosure, corruption, dan data loss langsung P0.
3. Reproduksi dengan fixture sintetis pada release SHA yang sama; jangan mengubah data pelanggan untuk diagnosis.
4. Gunakan audit log tersanitasi, correlation ID, failed job, health, dan metric. Pastikan semua bukti bebas secret.
5. Berikan workaround hanya bila tidak merusak data/authorization. Tindakan mutation harus melalui workflow resmi.
6. Catat owner, mitigasi, target fix, regression test, retest date, dan komunikasi penutupan.

Private download hanya diberikan ulang setelah ownership/authorization diverifikasi; URL sementara tidak boleh ditempel ke tiket permanen.
