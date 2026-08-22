# Document Delta Fase 9A — Rate Limit & Transport Hardening

Status: **DISETUJUI**

Decision ID: **CD-9.2**

Tanggal: 2026-08-22 (`Asia/Jakarta`)

Baseline keputusan dan implementasi F9A: merge pengesahan CD-9.1 `ac6b7bf7ab630ba061b69a37a816804152b7695b`

## 1. Document Delta Declaration

**Affected documents**

- `api-specification.md`;
- `software-architecture-document.md`;
- `development-roadmap.md`;
- `master-plan-fase-5-12.md`;
- `implementation-plan-fase-9a.md`;
- `.agents/AGENTS.md`.

**Reason**

API baseline belum mempunyai kontrak `429`, pembagian rate limiter, atau aturan backing store distributed. F9A juga memerlukan keputusan eksplisit untuk CORS, security headers, private response, dan redaction agar security acceptance dapat dijalankan secara deterministic tanpa menunggu deployment pilot.

**Current contract**

- API memakai envelope success/error canonical, tetapi `RATE_LIMITED` belum terdaftar.
- Login API belum mempunyai batas request yang dikunci kontrak.
- Tidak ada pembagian limiter read, mutation, dan export.
- CORS, transport headers, cache policy response sensitif, dan redaction belum decision-complete.

**Proposed contract**

- API login dibatasi 5 request per menit per kombinasi hash email ternormalisasi dan IP.
- Authenticated read dibatasi 300 request per menit per tenant dan User.
- Mutation dibatasi 120 request per menit per tenant dan User.
- Pembuatan export dibatasi 10 request per menit per tenant dan User.
- Logout tidak dibatasi agar actor selalu dapat mencabut token/session.
- Request yang dibatasi menghasilkan status `429`, envelope `RATE_LIMITED`, dan header limiter canonical tanpa mutation atau side effect.
- Runtime multi-process memakai Redis atau distributed cache yang kompatibel dengan atomic rate limiting. Array/file hanya boleh dipakai pada unit test single-process.
- CORS browser deny-by-default dan hanya membuka origin eksplisit tanpa wildcard credential.
- Seluruh response memakai security headers minimum; HSTS hanya pada production HTTPS.
- Auth response dan private download tidak boleh di-cache; private file tidak boleh dilayani sebagai public asset.
- Metadata audit/log harus melalui recursive sensitive-value redaction.

**Migration impact**

Tidak ada migration, schema, enum, index, backfill, atau perubahan data.

**Backward compatibility**

Tidak ada endpoint atau request field baru. Request yang melewati batas kini memperoleh `429`; ini merupakan additive defensive behavior pada `/api/v1` dan tidak mengubah successful response. Client wajib menghormati `Retry-After`.

**Test impact**

- feature test untuk setiap bucket, key isolation, header, envelope, dan zero side effect;
- Redis multi-process test untuk atomic limiter;
- CORS/security-header/private-file/cache-control test;
- recursive audit/log redaction test;
- dependency dan secret scan pada CI.

## 2. Rate Limit Contract

| Bucket | Batas | Key canonical | Surface |
|---|---:|---|---|
| Login | 5/menit | `sha256(lower(email)) + IP` | `POST /api/v1/auth/login` |
| Read | 300/menit | `tenant_id:user_id` | authenticated read dan status/download yang diizinkan |
| Mutation | 120/menit | `tenant_id:user_id` | authenticated command selain logout dan create export |
| Export | 10/menit | `tenant_id:user_id` | command pembuatan export |
| Logout | Tidak dibatasi | — | `POST /api/v1/auth/logout` |

Rate limit diterapkan setelah authentication untuk bucket tenant-scoped dan sebelum controller/Action. Foreign ID, request invalid, dan idempotency retry tetap memakai bucket actor yang sama. Limiter tidak boleh mengambil tenant ID dari request.

Canonical response:

```json
{
  "status": "error",
  "message": "Terlalu banyak permintaan. Coba lagi nanti.",
  "error_code": "RATE_LIMITED",
  "errors": []
}
```

Response wajib memuat integer `Retry-After`, `X-RateLimit-Limit`, dan `X-RateLimit-Remaining`. Login tidak boleh membedakan akun ada/tidak ada. Request `429` menghasilkan zero mutation, zero audit, zero event, dan zero queued job.

## 3. Transport dan Private Response Contract

Seluruh response web/API/private download memuat:

- `X-Content-Type-Options: nosniff`;
- `X-Frame-Options: DENY`;
- `Referrer-Policy: strict-origin-when-cross-origin`;
- `Permissions-Policy: camera=(), geolocation=(), microphone=()`;
- Content Security Policy minimum `frame-ancestors 'none'; base-uri 'self'; form-action 'self'`;
- `Cross-Origin-Opener-Policy: same-origin`;
- `Cross-Origin-Resource-Policy: same-origin`.

`Strict-Transport-Security: max-age=31536000; includeSubDomains` hanya dikirim ketika environment production dan request benar-benar secure. Nilai `preload` tidak diklaim pada F9A.

Cross-origin browser request ditolak secara default. Origin hanya dibuka dari allowlist environment eksplisit; wildcard dan credentialed wildcard dilarang. Native Bearer-token client tetap dapat memakai API tanpa kontrak CORS baru.

Auth response dan private download memakai `Cache-Control: no-store, private`. PHP version disclosure dinonaktifkan pada hardening runtime dan wajib dinonaktifkan pada deployment production.

## 4. Sensitive Metadata Redaction

Satu recursive redactor dipakai oleh audit metadata dan structured log context. Key dicocokkan case-insensitive untuk:

- password dan confirmation;
- token, authorization, cookie, serta CSRF;
- API key, secret, OTP, dan signature.

Nilai diganti `[REDACTED]`; nama key dan struktur metadata dipertahankan agar audit tetap dapat digunakan. Redaction bersifat fail-safe dan tidak boleh menggagalkan business transaction. Raw credential, token, OTP, signature, atau cookie dilarang masuk log, audit, exception context, CI output, dan evidence.

## 5. Acceptance dan Rollback

- Seluruh limiter lulus pada unit/feature dan Redis multi-process runtime.
- `429` canonical serta zero-side-effect terbukti.
- Security headers, CORS, cache policy, private file, dan redaction lulus.
- Tidak ada P0 terbuka; P1 hanya boleh diterima dengan owner, mitigasi, target, dan retest date.
- CD-9.2 tidak menutup F9A atau Fase 9 dengan sendirinya.

Delta ini tidak mempunyai rollback database. Rollback kode mengembalikan limiter/header/redaction lama, tetapi hanya boleh dilakukan bila risiko security diterima eksplisit dan dicatat sebagai P1/P0 sesuai dampaknya.
