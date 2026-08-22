# Draft Runbook Rollback v1 / Pilot

Status: **DRAFT F9A — EKSEKUSI DEPLOYMENT WAJIB F9B**

## Trigger rollback

Rollback dipicu oleh P0, migration gagal/parsial, tenant leakage, auth bypass, secret disclosure, stok/ledger/payment corruption, data loss, error 5xx berulang, queue tidak dapat dipulihkan dalam observation window, atau health kritis gagal. P1 membutuhkan keputusan release owner dan incident owner yang dicatat.

## Prosedur

1. Hentikan traffic pilot dan aktifkan maintenance mode; jangan hapus log/evidence.
2. Hentikan scheduler dan worker penghasil mutation, lalu catat queue depth dan failed jobs.
3. Tentukan apakah aman melakukan application rollback saja. Jangan menjalankan migration `down` bila rollback bersifat lossy atau telah ada data baru.
4. Deploy artifact sebelumnya yang kompatibel. Bila schema tidak kompatibel, restore backup ke environment terisolasi dahulu dan validasi sebelum mengganti database pilot.
5. Verifikasi login/revocation, tenant isolation, stok-ledger, payment, export private, worker/scheduler, dan health.
6. Buka kembali pilot hanya setelah incident owner dan release owner menyetujui hasil verifikasi.

## Peringatan data

Rollback F7 kehilangan klasifikasi/mode historis tanpa backup; rollback F8 kehilangan status aktif dan revocation version. Keduanya hanya boleh dilakukan dalam maintenance mode dengan backup. Tidak ada migration F9A.

Catat trigger, waktu deteksi, operator, SHA asal/tujuan, backup identifier, perintah yang dijalankan, hasil rekonsiliasi, dampak, dan tindakan lanjutan tanpa memasukkan secret.
