<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill product_units.harga_hpp (dan harga_jual bila kosong) untuk unit yang
 * berasal dari tukar tambah namun HPP-nya masih 0/NULL akibat bug lama
 * (unit tukar tambah baru dibuat tanpa menyimpan harga_hpp).
 *
 * Sumber nilai (berurutan):
 *   1) sale_trade_ins.trade_in_value  -> nilai tukar tambah asli (paling akurat)
 *   2) products.purchase_price        -> fallback, hanya untuk produk bertipe 'bekas'
 *
 * Hanya menyentuh unit dengan harga_hpp NULL/0 dan sumber nilai > 0,
 * sehingga aman dijalankan ulang (idempotent) dan tidak merusak data yang benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $units = DB::table('product_units')
            ->where(function ($q) {
                $q->whereNull('harga_hpp')->orWhere('harga_hpp', 0);
            })
            ->get(['id', 'serial_number', 'product_id', 'harga_hpp', 'harga_jual']);

        $fixed = 0;

        foreach ($units as $unit) {
            $source = null;

            // 1) Sumber utama: nilai tukar tambah terbaru untuk serial ini.
            $tradeIn = DB::table('sale_trade_ins')
                ->where('serial_number', $unit->serial_number)
                ->where('trade_in_value', '>', 0)
                ->orderByDesc('id')
                ->first();

            if ($tradeIn) {
                $source = (float) $tradeIn->trade_in_value;
            } else {
                // 2) Fallback: purchase_price produk, hanya untuk barang bekas (tukar tambah).
                $product = DB::table('products')->where('id', $unit->product_id)->first();
                if (
                    $product
                    && (float) ($product->purchase_price ?? 0) > 0
                    && ($product->laptop_type ?? null) === 'bekas'
                ) {
                    $source = (float) $product->purchase_price;
                }
            }

            if ($source === null || $source <= 0) {
                continue;
            }

            $payload = ['harga_hpp' => $source];
            if ((float) ($unit->harga_jual ?? 0) <= 0) {
                $payload['harga_jual'] = $source;
            }

            DB::table('product_units')->where('id', $unit->id)->update($payload);
            $fixed++;
        }

        Log::info('backfill_harga_hpp_for_trade_in_units: selesai', [
            'candidates' => $units->count(),
            'fixed' => $fixed,
        ]);
    }

    public function down(): void
    {
        // Tidak ada reverse yang aman: nilai 0/NULL sebelumnya tidak dicatat.
    }
};
