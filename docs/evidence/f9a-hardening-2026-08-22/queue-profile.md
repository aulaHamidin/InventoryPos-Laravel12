# Queue Profile

Status lokal: **LULUS**. Workflow manual remote tetap wajib sebelum penutupan.

Command `hardening:profile-queue` hanya menerima database local/testing bernama hardening, membutuhkan fixture 10 tenant, Redis queue kosong, lalu mengirim tepat 500 job analytics dan 5 export sintetis. Tiga worker memproses `exports,analytics,default`.

Hasil tiga worker Redis pada database dan prefix terisolasi:

| Gate | Hasil |
|---|---:|
| Analytics job | 500 |
| Export sintetis | 5 |
| Durasi drain | 21,21 detik |
| Queue analytics/export akhir | 0/0 |
| Export selesai/file hilang | 5/0 |
| Cross-tenant output | 0 |
| Failed job | 0 |

Artifact tersanitasi: `f9a-queue-profile.json`. Command kini fail-closed bila `REDIS_PREFIX` tidak memuat namespace F9A/hardening, sehingga worker development/pilot tidak dapat mengambil job fixture secara tidak sengaja.
