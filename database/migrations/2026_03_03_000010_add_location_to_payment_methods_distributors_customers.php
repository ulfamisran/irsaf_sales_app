<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_methods', 'distributors', 'customers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('placement_type', 20)->nullable()->after('id');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_methods', 'distributors', 'customers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn(['placement_type', 'branch_id', 'warehouse_id']);
            });
        }
    }
};
