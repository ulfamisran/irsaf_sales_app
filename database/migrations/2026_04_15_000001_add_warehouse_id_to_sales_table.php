<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'warehouse_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'warehouse_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }
};
