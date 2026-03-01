<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expense_categories')->updateOrInsert(
            ['code' => 'SP-SVC'],
            [
                'name' => 'Pembelian Sparepart User (SERVICE)',
                'code' => 'SP-SVC',
                'description' => 'Pengeluaran pembelian sparepart untuk service pelanggan',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('expense_categories')->where('code', 'SP-SVC')->delete();
    }
};
