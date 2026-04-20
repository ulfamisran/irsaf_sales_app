<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('location_type', 20)->default('branch')->after('branch_id');
            $table->foreignId('warehouse_id')->nullable()->after('location_type')
                ->constrained()->nullOnDelete();

            $table->unsignedBigInteger('branch_id')->nullable()->change();

            $table->index(['warehouse_id', 'entry_date']);
        });

        // Set location_type for existing records
        DB::table('services')->whereNotNull('branch_id')->update(['location_type' => 'branch']);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['warehouse_id', 'entry_date']);
            $table->dropColumn(['location_type', 'warehouse_id']);
        });
    }
};
