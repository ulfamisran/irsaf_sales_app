<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('requires_serial')->default(true)->after('description');
        });

        // Kategori aksesoris (barang non-serial, dilacak berdasarkan jumlah/qty saja).
        DB::table('categories')->where('name', 'Aksesoris')->update(['requires_serial' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('requires_serial');
        });
    }
};
