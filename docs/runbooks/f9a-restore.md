# Draft Runbook Restore Terisolasi

Status: **BELUM DIEKSEKUSI — WAJIB F9B**

## Guardrail

Restore selalu menuju environment/database terisolasi dengan hostname dan credential berbeda. Jangan overwrite database aktif. Pastikan target resolved secara eksplisit dan tidak menerima traffic publik.

## Prosedur rancangan

1. Pilih backup identifier/checksum, catat titik waktu, operator, reviewer, dan target terisolasi.
2. Buat database/storage target kosong, least-privilege, dan unique prefix; validasi nama target sebelum restore.
3. Restore database dan private files, lalu verifikasi checksum dan jumlah record/object.
4. Deploy release SHA yang kompatibel tanpa menjalankan migration tak terencana.
5. Jalankan integrity check tenant ownership, users/session revocation state, stok-ledger, transaction-payment-movement, analytics status, export privacy, dan audit continuity.
6. Jalankan login, POS, queue, scheduler dry verification, browser smoke, serta sampling multi-tenant sintetis.
7. Catat waktu dari start sampai data/application siap. RPO/RTO aktual hanya sah dari drill F9B ini.
8. Hapus environment restore secara terkontrol setelah evidence disanitasi dan retention evidence disetujui.

Kegagalan checksum, compatibility, ownership, atau reconciliation menjadikan restore gagal dan P0/P1 sesuai dampaknya.
