# Draft Runbook Backup

Status: **BELUM DIEKSEKUSI — WAJIB F9B**

## Sasaran

Backup mencakup database, private object metadata/file yang diperlukan, konfigurasi release non-secret, dan referensi secret version. Backup harus terenkripsi, access-controlled, memiliki retention policy, checksum, dan lifecycle terpisah dari environment pilot.

## Prosedur rancangan

1. Catat environment, release SHA, database version/size, operator, waktu mulai, dan backup identifier.
2. Ambil snapshot/database dump konsisten memakai credential least-privilege. Jangan mencetak DSN/password.
3. Sinkronkan private object backup dan manifest checksum tanpa URL bertanda tangan atau token.
4. Verifikasi ukuran, checksum, encryption, readability, retention, dan akses restore account.
5. Salin ke lokasi terisolasi sesuai retention; uji bahwa aplikasi tidak dapat melayani file backup secara langsung.
6. Rekam durasi dan freshness. RPO aktual baru dicatat setelah schedule pilot berjalan di F9B.

F9A hanya mereview prosedur ini. Klaim backup terjadwal, keberhasilan restore, atau RPO/RTO aktual dilarang sebelum drill F9B.
