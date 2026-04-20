<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->decimal('hpp_per_unit', 15, 2)->default(0)->after('biaya_distribusi_per_unit');
        });

        // Backfill hpp_per_unit from distribution_unit_snapshot JSON
        $mutations = DB::table('stock_mutations')
            ->whereNotNull('distribution_unit_snapshot')
            ->where('distribution_unit_snapshot', '!=', '[]')
            ->get(['id', 'distribution_unit_snapshot']);

        foreach ($mutations as $m) {
            $snapshot = json_decode($m->distribution_unit_snapshot, true);
            if (is_array($snapshot) && count($snapshot) > 0) {
                $totalHpp = 0;
                foreach ($snapshot as $row) {
                    $totalHpp += (float) ($row['harga_hpp'] ?? 0);
                }
                $avgHpp = round($totalHpp / count($snapshot), 2);
                DB::table('stock_mutations')->where('id', $m->id)->update(['hpp_per_unit' => $avgHpp]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropColumn('hpp_per_unit');
        });
    }
};
