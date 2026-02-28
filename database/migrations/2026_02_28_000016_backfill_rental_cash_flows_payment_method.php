<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE cash_flows cf
            JOIN rentals r ON r.id = cf.reference_id
            JOIN rental_payments rp
              ON rp.rental_id = r.id
             AND rp.amount = cf.amount
             AND DATE(rp.created_at) = DATE(cf.created_at)
            SET cf.payment_method_id = rp.payment_method_id,
                cf.warehouse_id = r.warehouse_id,
                cf.branch_id = NULL
            WHERE cf.reference_type = 'rental'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // no-op
    }
};
