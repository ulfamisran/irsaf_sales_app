<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('expense_categories')->where('code', 'PEMBELIAN')->exists()) {
            DB::table('expense_categories')->insert([
                'name' => 'Pembelian',
                'code' => 'PEMBELIAN',
                'description' => 'Pembelian barang dari distributor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('expense_categories')->where('code', 'PEMBELIAN')->delete();
    }
};
