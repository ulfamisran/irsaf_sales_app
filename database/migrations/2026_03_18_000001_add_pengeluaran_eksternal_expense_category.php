<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expense_categories')->updateOrInsert(
            ['code' => 'PENGELUARAN_EKSTERNAL'],
            [
                'name' => 'Pengeluaran Eksternal',
                'code' => 'PENGELUARAN_EKSTERNAL',
                'description' => 'Pengeluaran dana eksternal',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('expense_categories')->where('code', 'PENGELUARAN_EKSTERNAL')->delete();
    }
};

