<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->decimal('harga_hpp', 15, 2)->default(0)->after('product_id');
            $table->decimal('harga_jual', 15, 2)->default(0)->after('harga_hpp');
        });

        // Backfill: set harga_hpp dan harga_jual dari product (join by product_id)
        DB::statement("
            UPDATE product_units pu
            INNER JOIN products p ON pu.product_id = p.id
            SET pu.harga_hpp = p.purchase_price, pu.harga_jual = p.selling_price
        ");
    }

    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn(['harga_hpp', 'harga_jual']);
        });
    }
};
