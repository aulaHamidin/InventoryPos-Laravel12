# Document Delta Declaration - Repair Baseline Fase 0-4

Status: approved implementation baseline
Tanggal: 2026-08-11

## Delta 1 - POS Payment pada Fase 2

Affected documents: development-roadmap.md, implementation_plan.md.

Reason: alur cash pada SAD sudah mewajibkan lifecycle uang yang terpisah.

Current contract: roadmap menaruh pembuatan `pos_payments` di Fase 6, tetapi Pay Cash Fase 2 membuat payment.

Proposed contract:

- Tabel dan lifecycle dasar `pos_payments` dibuat pada Fase 2.
- Fase 2 hanya mengaktifkan method cash.
- Fase 6 menambah QRIS, gateway lifecycle, webhook, dan refund tanpa membuat ulang tabel.

Migration impact: migration `pos_payments` menjadi batch Fase 2.
Backward compatibility: database development di-reset setelah backup.
Test impact: amount cash payment harus sama dengan total transaksi dan tidak boleh dibayar dua kali.

## Delta 2 - Foreign Key dan Histori

Affected documents: database-design-document.md, implementation_plan.md.

Reason: cascade dari master dapat menghapus movement/transaksi individual dan melanggar retention contract.

Current contract: DDD belum membedakan direct tenant ownership dan relasi domain.

Proposed contract:

- FK langsung `tenant_id -> tenants.id` menggunakan cascade untuk whole-tenant purge.
- FK historis antarmodel menggunakan restrict.
- FK opsional non-historis dapat menggunakan set null.
- Item dan user menggunakan soft delete.
- Tidak ada hard-delete UI/API untuk movement, transaction, payment, audit, atau item berhistori.

Migration impact: seluruh FK Fase 0-4 ditulis ulang.
Backward compatibility: database development di-reset setelah backup.
Test impact: item deletion safety, immutability, dan tenant purge cascade wajib diuji.

## Delta 3 - Shopping List Canonical

Affected documents: blueprint, DDD, SAD, API specification, implementation plan.

Reason: implementasi lama memakai supplier header serta status `submitted/received` yang tidak ada di DDD/SAD.

Current contract: DDD/SAD memakai `draft -> purchased -> completed` dan `archived`; API belum memetakan submit/receive.

Proposed contract:

- Status: `draft`, `purchased`, `completed`, `archived`.
- Endpoint submit memetakan `draft -> purchased`.
- Endpoint receive memetakan `purchased -> completed`.
- Supplier disimpan per shopping-list item.
- `qty_disarankan = max(1, stok_minimal - stok_saat_ini)` pada Fase 4.
- Generator tidak menyimpan list kosong.
- Receive satu kali; semua item yang dibeli wajib memiliki `qty_received > 0`.

Migration impact: header supplier dihapus; detail memakai supplier, qty_disarankan, qty_dibeli, qty_received, is_checked.
Backward compatibility: database development di-reset setelah backup.
Test impact: preferred/null supplier, empty result, submit validation, lifecycle, lock order, dan duplicate receive.

## Delta 4 - Operational Status Tenant

Affected documents: API specification dan implementation plan.

Reason: `suspended` adalah subscription status, bukan tenant operational status.

Current contract: Blueprint/DDD mengunci `active|banned`, sementara plan memakai istilah suspend.

Proposed contract:

- `tenants.operational_status` hanya `active|banned`.
- Administrative action menggunakan `ban/unban`.
- Subscription tetap dapat berstatus `suspended`.

Migration impact: enum/domain cast suspended dihapus dari tenant.
Backward compatibility: belum ada tenant production berstatus suspended.
Test impact: tenant banned ditolak dan billing tidak mengubah operational status.

## Approval Record

Keputusan implementasi yang dikunci:

- Laravel 12 dan PHP 8.3.
- Backup lalu reset database development.
- POS payment dasar berada di Fase 2.
- Fase 5+ dibekukan sampai repair gate Fase 0-4 lulus.


## Delta 5 - Authentication Identity Boundary

Affected documents: software architecture document and implementation plan.

Tenant `User` remains globally tenant-scoped and fail-closed for application queries. Session and Sanctum identity resolution use a dedicated authentication provider/token relation that bypasses only `TenantScope`; immediately after authentication, `SetTenantContext` restores normal tenant-scoped execution. This exception is limited to credential/token lookup and is not available to controllers or business Actions.


## Delta 6 - POS Zero-Net dan Negative Stock

Affected documents: database-design-document.md, blueprint-saas-stok.md, dan API specification.

Reason: DDD mewajibkan `pos_payments.amount > 0`, sementara diskon baris boleh sama dengan nilai bruto baris; Blueprint juga menyatakan semua stock decrease menghormati `allow_negative_stock`.

Resolved contract:

- Diskon setiap baris tetap boleh sampai sebesar gross line amount, tetapi total net seluruh transaksi harus lebih dari 0 sebelum checkout disimpan.
- Pay Cash menyimpan `amount = total_amount` dan karena itu tidak pernah membuat payment bernilai nol.
- Revalidasi stok saat Pay Cash menolak kekurangan stok hanya jika `allow_negative_stock = false`; bila true, sale boleh menghasilkan stok negatif.
- Failed revalidation tetap meng-commit status `failed` dan audit event, tanpa payment atau movement.

Migration impact: tidak ada perubahan tipe; CHECK `pos_payments.amount > 0` dipertahankan.
Test impact: zero-net checkout, negative-stock payment, dan failed-state audit wajib diuji.
