<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_goods', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('warehouse_id')->constrained('branches')->nullOnDelete();
        });

        // Allow warehouse_id to be nullable for branch-level incoming goods.
        DB::statement('ALTER TABLE incoming_goods MODIFY warehouse_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('incoming_goods', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        DB::statement('ALTER TABLE incoming_goods MODIFY warehouse_id BIGINT UNSIGNED NOT NULL');
    }
};
