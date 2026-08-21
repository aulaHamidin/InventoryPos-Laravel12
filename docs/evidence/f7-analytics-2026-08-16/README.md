# Evidence Fase 7 — Analytics & Smart Threshold

Tanggal verifikasi: 2026-08-16 (`Asia/Jakarta`)

## Visual matrix

| File | Viewport yang diminta | State yang dibuktikan |
|---|---:|---|
| `dashboard-desktop-1440x900.jpg` | 1440×900 | critical stock → shopping recommendation → class insight → operational summary; populated, unclassified, dead, no-movement, learning progress |
| `dashboard-mobile-390x844.jpg` | 390×844 | urutan prioritas, navigation mobile, critical dan recommendation tanpa page overflow |
| `dashboard-mobile-states-390x844.jpg` | 390×844 | unclassified, dead, no-movement, active/eligible pada bagian bawah dashboard |
| `dashboard-loading-desktop.jpg` | 1440×900 | lazy loading skeleton sebelum aggregate widget selesai dimuat |
| `dashboard-error-desktop.jpg` | 1440×900 | persistent error fallback `Analytics tidak tersedia` |
| `items-preview-desktop.jpg` | 1440×900 | class badge, mode, timestamp, dan hasil tombol read-only **Hitung Preview** |
| `items-mobile-390x844.jpg` | 390×844 | tabel item memakai horizontal scroll terlokalisasi; page tidak overflow |
| `analytics-settings-desktop.jpg` | 1440×900 | pengaturan Owner `dead_stock_days`, helper `0`, dan tombol simpan |

Browser check juga memverifikasi:

- `documentElement.scrollWidth === clientWidth` pada desktop dan mobile;
- preview menampilkan net demand `30`, average `1.000000`, lead source `item`, threshold `5`, class `Fast Moving`;
- state error persisten dirender oleh fallback widget saat query gagal; defect `$asOf` yang ditemukan saat walkthrough telah diperbaiki dan diberi regression assertion;
- tidak ada data cost, margin, valuation, atau profit pada widget analytics.

## Runtime evidence

- Suite cepat: **106 passed**, **586 assertions**; tiga Redis integration test di-skip secara sengaja pada konfigurasi `array/sync`.
- Redis runtime terpisah: **3 passed**, **25 assertions** dengan worker dan mutex multi-process nyata.
- Node: **5 passed**.
- Migration harness: fresh, upgrade dari tree migration commit `e72ef07`, serta rollback lossy pada dua database sementara lulus.
- Backfill lokal: 5 item aktif, 4 eligible, 1 ineligible, 0 eligible-unclassified, 0 eligible timestamp null, 0 ineligible timestamp present, queue depth 0, failed analytics jobs 0.
- Worker `exports,analytics,default` dan service scheduler berada pada state running; `schedule:list` memuat sweep F7.

Tidak ada secret, token, credential, atau isi `.env` dalam evidence ini.
