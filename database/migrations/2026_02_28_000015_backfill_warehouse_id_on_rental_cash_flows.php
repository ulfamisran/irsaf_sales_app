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
        // Backfill cash_flows for rental transactions to use warehouse_id
        DB::statement("
            UPDATE cash_flows cf
            JOIN rentals r ON r.id = cf.reference_id
            SET cf.warehouse_id = r.warehouse_id,
                cf.branch_id = NULL
            WHERE cf.reference_type = 'rental'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // no-op (avoid restoring old branch_id)
    }
};
