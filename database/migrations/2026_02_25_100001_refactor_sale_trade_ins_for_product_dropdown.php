<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sale_trade_ins', 'template_product_id')) {
            Schema::table('sale_trade_ins', function (Blueprint $table) {
                $table->foreignId('template_product_id')
                    ->nullable()
                    ->after('sale_id')
                    ->constrained('products')
                    ->onDelete('restrict')
                    ->comment('Produk master sebagai format (brand, series, specs, kategori)');
            });
        }

        // Backfill existing rows: gunakan produk dengan category_id yang sama
        $tradeIns = DB::table('sale_trade_ins')->whereNull('template_product_id')->get();
        $fallbackProduct = DB::table('products')->first();
        foreach ($tradeIns as $row) {
            $product = null;
            if (! empty($row->category_id)) {
                $product = DB::table('products')->where('category_id', $row->category_id)->first();
            }
            $product = $product ?? $fallbackProduct;
            if ($product) {
                DB::table('sale_trade_ins')->where('id', $row->id)->update(['template_product_id' => $product->id]);
            }
        }

        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->foreignId('template_product_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('sale_trade_ins', 'category_id')) {
            Schema::table('sale_trade_ins', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }
        foreach (['brand', 'series', 'specs'] as $col) {
            if (Schema::hasColumn('sale_trade_ins', $col)) {
                Schema::table('sale_trade_ins', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->string('brand')->after('serial_number')->default('');
            $table->string('series')->nullable()->after('brand');
            $table->text('specs')->nullable()->after('series');
            $table->foreignId('category_id')->nullable()->after('specs')->constrained()->onDelete('restrict');
        });

        DB::table('sale_trade_ins')->get()->each(function ($row) {
            $product = DB::table('products')->find($row->template_product_id);
            if ($product) {
                DB::table('sale_trade_ins')->where('id', $row->id)->update([
                    'brand' => $product->brand ?? '',
                    'series' => $product->series ?? null,
                    'specs' => $product->specs ?? null,
                    'category_id' => $product->category_id ?? null,
                ]);
            }
        });

        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_product_id');
        });
    }
};
