<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_details', 'hpp')) {
                $table->decimal('hpp', 15, 2)->default(0)->after('price')
                    ->comment('Harga Pokok Penjualan per unit saat transaksi (snapshot dari product.purchase_price)');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'total_paid')) {
                $table->decimal('total_paid', 15, 2)->default(0)->after('total')
                    ->comment('Total pembayaran yang sudah diterima (untuk lunas/belum lunas)');
            }
        });

        // Backfill total_paid dari sale_payments untuk data existing
        if (Schema::hasColumn('sales', 'total_paid') && Schema::hasTable('sale_payments')) {
            $totals = DB::table('sale_payments')
                ->select('sale_id', DB::raw('SUM(amount) as total'))
                ->groupBy('sale_id')
                ->pluck('total', 'sale_id');

            foreach ($totals as $saleId => $total) {
                DB::table('sales')->where('id', $saleId)->where('status', 'released')->update(['total_paid' => $total]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'hpp')) {
                $table->dropColumn('hpp');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'total_paid')) {
                $table->dropColumn('total_paid');
            }
        });
    }
};
