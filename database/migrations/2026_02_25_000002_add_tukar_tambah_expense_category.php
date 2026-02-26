<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expense_categories')->updateOrInsert(
            ['code' => 'TT'],
            [
                'name' => 'Tukar Tambah (TT)',
                'code' => 'TT',
                'description' => 'Pengeluaran untuk pembelian unit laptop bekas via tukar tambah',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('expense_categories')->where('code', 'TT')->delete();
    }
};
