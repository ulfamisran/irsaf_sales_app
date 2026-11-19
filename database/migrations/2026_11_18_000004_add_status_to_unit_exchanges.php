<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_exchanges')) {
            return;
        }

        if (! Schema::hasColumn('unit_exchanges', 'status')) {
            Schema::table('unit_exchanges', function (Blueprint $table) {
                $table->string('status', 20)->default('open')->after('exchange_date');
            });

            DB::table('unit_exchanges')->update(['status' => 'released']);

            $diffs = DB::table('unit_exchange_items')
                ->join('unit_exchanges', 'unit_exchanges.id', '=', 'unit_exchange_items.unit_exchange_id')
                ->groupBy('unit_exchanges.sale_id')
                ->select('unit_exchanges.sale_id', DB::raw('ROUND(SUM(unit_exchange_items.price_difference), 2) as diff'))
                ->get();

            foreach ($diffs as $row) {
                $diff = round((float) $row->diff, 2);
                if (abs($diff) < 0.01) {
                    continue;
                }
                DB::update(
                    'UPDATE sales SET total = ROUND(total + ?, 2), total_paid = ROUND(total_paid + ?, 2) WHERE id = ?',
                    [$diff, $diff, $row->sale_id]
                );
            }
        }

        if (Schema::hasTable('expense_categories')) {
            DB::table('expense_categories')
                ->where('code', 'TUKAR-UNIT')
                ->update(['affects_profit_loss' => false]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('unit_exchanges') && Schema::hasColumn('unit_exchanges', 'status')) {
            Schema::table('unit_exchanges', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
