<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('location_type', 20)->default('warehouse')->after('branch_id');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->change();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->dropColumn('location_type');
        });
    }
};
