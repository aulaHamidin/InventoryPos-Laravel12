# Dependency dan Secret Scan

Status lokal: **LULUS**. Remote security job tetap wajib sebelum penutupan.

Perintah authoritative:

```text
composer audit --locked --no-interaction
npm audit --audit-level=high
gitleaks git /repo --redact --verbose
gitleaks dir /repo --redact --verbose
```

Hasil lokal 2026-08-22:

- Composer advisory: 0 advisory.
- npm audit: 0 critical/high/moderate/low vulnerability.
- Gitleaks history: 20 commit, sekitar 4,16 MB, 0 leak.
- Gitleaks current tracked/untracked source tree (ignored runtime secret dikecualikan): sekitar 4,01 MB, 0 leak pada final source snapshot lokal.

Gitleaks memakai image `v8.29.0` yang dipin digest. Artifact dilarang memuat manifest hardening, `.env`, token, cookie, OTP, signature, atau QRIS produksi.
