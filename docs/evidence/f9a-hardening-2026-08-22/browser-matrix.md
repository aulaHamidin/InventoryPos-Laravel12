# Browser dan Device Matrix

Status lokal: **LULUS**. Remote `browser-runtime` tetap wajib sebelum penutupan.

| Browser | Desktop 1440×900 | Mobile 390×844 | Tablet 768×1024 |
|---|---|---|---|
| Chromium | Lulus | Lulus + local 4G profile | Lulus |
| Firefox | Lulus | Lulus | Lulus |
| Safari/iOS | Manual optional | Manual optional | Manual optional |

Automated scenarios: Owner/Staff login, menu/widget role, item search/exact barcode, keyboard-wedge scanner, diskon, cash/QRIS/transfer, own history, direct URL/forged denial, financial exclusion pada DOM/state, no overflow, reachable actions, receipt/print stylesheet.

Screenshot/video hanya memakai fixture sintetis dan tidak boleh memuat password, token, kontak nyata, atau QRIS produksi.

Profil local 4G Chromium memakai latency 40 ms, download 4 Mbit/detik, dan upload 1,5 Mbit/detik pada navigasi authenticated dengan aset shell yang sudah dimuat oleh login. Cold-cache public landing bukan bagian gate dashboard operasional ini.

Hasil final Playwright: **29 lulus, 43 skipped, 0 gagal** dari 72 kombinasi. Login Owner/Staff dijalankan oleh global setup Chromium dan test eksplisit Firefox; workflow lengkap dijalankan pada desktop Chromium/Firefox, responsive/denial pada enam browser-viewport project, dan 4G hanya pada mobile Chromium sesuai kontrak.

Public login shell juga diperiksa melalui in-app browser. Mobile memiliki `clientWidth=390`, `scrollWidth=390` (tanpa overflow).

Screenshot tersanitasi:

- `login-desktop-1440x900.png` dan `login-mobile-390x844.png`;
- `staff-dashboard-desktop-1440x900.png` dan `staff-dashboard-mobile-390x844.png`;
- `staff-pos-payment-desktop-1440x900.png` dan `staff-pos-payment-mobile-390x844.png`.
- `staff-unauthorized-desktop-1440x900.png` dan `staff-unauthorized-mobile-390x844.png`.
