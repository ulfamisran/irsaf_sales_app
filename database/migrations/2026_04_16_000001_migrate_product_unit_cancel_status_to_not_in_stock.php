<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy: pembatalan pembelian men-set status unit ke "cancel".
     * Diseragamkan ke "not_in_stock" agar sama dengan perilaku baru dan reuse pembelian.
     *
     * Catatan: unit berstatus "cancel" dari alur lain (mis. pembatalan penjualan) ikut berubah;
     * perilaku reuse / stok tetap setara "tidak di stok".
     */
    public function up(): void
    {
        DB::table('product_units')
            ->where('status', 'cancel')
            ->update(['status' => 'not_in_stock']);
    }

    public function down(): void
    {
        // Tidak aman mengembalikan: baris not_in_stock baru tidak bisa dibedakan dari hasil migrasi.
    }
};
