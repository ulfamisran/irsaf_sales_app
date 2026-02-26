<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tunaiPm = DB::table('payment_methods')
            ->whereRaw("LOWER(TRIM(jenis_pembayaran)) LIKE '%tunai%'")
            ->orderBy('id')
            ->first();

        if ($tunaiPm) {
            DB::table('cash_flows')
                ->where('type', 'OUT')
                ->whereNull('payment_method_id')
                ->update(['payment_method_id' => $tunaiPm->id]);
        }
    }

    public function down(): void
    {
        // Tidak di-revert - data sudah di-update
    }
};
