# Evidence Fase 5 — Cycle Counting

## Automated

- Fresh migration dan seed database testing: lulus.
- Upgrade migration dari baseline F0–4: lulus.
- Full test suite: 65 test / 314 assertion lulus.
- Targeted F5: 17 test / 91 assertion lulus.
- Composer, platform requirements, Pint, Vite/Filament build, view cache, dan route list lulus.

## Visual screenshot index

Walkthrough dijalankan sebagai Owner Demo pada aplikasi lokal:

- `01-opname-list-desktop.png` — daftar sesi, progress, status, dan action.
- `02-create-session-desktop.png` — modal create partial/full.
- `03-count-desktop.png` — scan/search fallback, stok fisik, note, dan Save & Next.
- `04-review-desktop.png` — review snapshot 5, fisik 3, dan discrepancy -2.
- `05-finalize-confirmation-desktop.png` — confirmation konsekuensial finalisasi.
- `06-completed-desktop.png` — summary read-only dengan 1 adjusted line dan 2 unit keluar.
- `07-count-mobile.png` — counting workflow pada viewport 390 × 844.
- `08-review-mobile.png` — progress lengkap dan zero-delta review pada mobile.
- `09-completed-mobile.png` — completed summary responsif dan read-only.
- `10-scope-conflict-desktop.png` — draft rack sama ditolak dengan pesan scope conflict.

Incomplete, duplicate-finalize, loading prevention, dan validation state juga dicakup oleh automated Livewire/API tests. Camera tidak diaktifkan dalam walkthrough karena memerlukan izin perangkat; keyboard-wedge/search fallback tersedia dan terlihat pada halaman count.

Evidence wajib bebas credential, token, dan data sensitif.
