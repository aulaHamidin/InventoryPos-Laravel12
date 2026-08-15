<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_payments', function (Blueprint $table) {
            $table->foreignId('confirmed_by')->nullable()->after('gateway_reference')->constrained('users')->restrictOnDelete();
            $table->string('manual_reference')->nullable()->after('confirmed_by');
            $table->text('confirmation_note')->nullable()->after('manual_reference');
            $table->index(
                ['tenant_id', 'pos_transaction_id', 'method', 'status'],
                'idx_pos_payments_transaction_method_status'
            );
        });

        DB::statement("ALTER TABLE pos_payments MODIFY method ENUM('cash','qris','transfer') NOT NULL");
        DB::statement("ALTER TABLE item_stock_movements MODIFY movement_type ENUM('stock_in','stock_out','sale','sale_void','customer_return','supplier_return','damaged','adjustment','opname_adjustment') NOT NULL");
        DB::statement("UPDATE pos_payments AS payment INNER JOIN pos_transactions AS transaction_record ON transaction_record.id = payment.pos_transaction_id SET payment.confirmed_by = transaction_record.cashier_id, payment.paid_at = COALESCE(payment.paid_at, payment.created_at), payment.manual_reference = COALESCE(payment.manual_reference, payment.gateway_reference), payment.confirmation_note = COALESCE(payment.confirmation_note, 'Dimigrasikan sebagai histori QRIS manual pada Fase 6') WHERE payment.method = 'qris' AND payment.confirmed_by IS NULL");
        DB::statement("ALTER TABLE pos_payments ADD CONSTRAINT chk_pos_payments_manual_confirmation CHECK ((method = 'cash' AND confirmed_by IS NULL AND manual_reference IS NULL AND confirmation_note IS NULL) OR (method IN ('qris','transfer') AND confirmed_by IS NOT NULL AND paid_at IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pos_payments DROP CHECK chk_pos_payments_manual_confirmation');
        DB::statement("ALTER TABLE item_stock_movements MODIFY movement_type ENUM('stock_in','stock_out','sale','customer_return','supplier_return','damaged','adjustment','opname_adjustment') NOT NULL");
        DB::statement("ALTER TABLE pos_payments MODIFY method ENUM('cash','qris') NOT NULL");

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropIndex('idx_pos_payments_transaction_method_status');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['manual_reference', 'confirmation_note']);
        });
    }
};
