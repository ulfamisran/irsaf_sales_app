<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom manual (sku, brand, series, specs, category_id)
        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('sale_id');
            $table->string('brand')->nullable()->after('serial_number');
            $table->string('series')->nullable()->after('brand');
            $table->text('specs')->nullable()->after('series');
            $table->foreignId('category_id')->nullable()->after('specs')->constrained()->onDelete('restrict');
        });

        // Backfill dari template_product untuk data existing
        $tradeIns = DB::table('sale_trade_ins')->get();
        foreach ($tradeIns as $row) {
            $product = $row->template_product_id
                ? DB::table('products')->find($row->template_product_id)
                : null;
            $brand = $product ? ($product->brand ?? '') : '';
            $series = $product ? ($product->series ?? null) : null;
            $specs = $product ? ($product->specs ?? null) : null;
            $categoryId = $product ? ($product->category_id ?? null) : null;
            if ($categoryId === null) {
                $categoryId = DB::table('categories')->value('id');
            }
            $sku = 'TT-' . preg_replace('/[^a-zA-Z0-9]/', '', $brand) . '-' . ($row->serial_number ?? '');
            $sku = preg_replace('/[^a-zA-Z0-9\-_]/', '-', substr($sku, 0, 50));
            if ($sku === 'TT--' || $sku === '') {
                $sku = 'TT-' . ($row->serial_number ?? 'unknown');
            }
            DB::table('sale_trade_ins')->where('id', $row->id)->update([
                'sku' => $sku,
                'brand' => $brand,
                'series' => $series,
                'specs' => $specs,
                'category_id' => $categoryId,
            ]);
        }

        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->foreignId('template_product_id')
                ->nullable()
                ->after('sale_id')
                ->constrained('products')
                ->onDelete('restrict');
        });

        $tradeIns = DB::table('sale_trade_ins')->get();
        $fallbackProduct = DB::table('products')->first();
        foreach ($tradeIns as $row) {
            $product = $fallbackProduct;
            if ($row->category_id) {
                $p = DB::table('products')->where('category_id', $row->category_id)->first();
                if ($p) {
                    $product = $p;
                }
            }
            if ($product) {
                DB::table('sale_trade_ins')->where('id', $row->id)->update(['template_product_id' => $product->id]);
            }
        }

        Schema::table('sale_trade_ins', function (Blueprint $table) {
            $table->dropColumn(['sku', 'brand', 'series', 'specs', 'category_id']);
        });
    }
};
