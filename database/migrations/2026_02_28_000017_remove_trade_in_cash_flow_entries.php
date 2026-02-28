<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menghapus entri cash_flows dengan reference_type = 'trade_in'.
     * Nilai tukar tambah sekarang dihitung dari sale_trade_ins (Dana Masuk Barang),
     * bukan lagi sebagai dana keluar, agar histori arus kas dan monitoring kas konsisten.
     */
    public function up(): void
    {
        $deleted = DB::table('cash_flows')
            ->where('reference_type', 'trade_in')
            ->delete();

        if ($deleted > 0) {
            // Log atau bisa diabaikan; migrasi tetap sukses
        }
    }

    /**
     * Rollback tidak mengembalikan data yang dihapus (data sudah tidak dipakai).
     */
    public function down(): void
    {
        // Tidak mengembalikan entri trade_in yang dihapus
    }
};
