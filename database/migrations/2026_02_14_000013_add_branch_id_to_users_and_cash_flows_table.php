<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->nullOnDelete();
            }
        });

        Schema::table('cash_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_flows', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->nullOnDelete()
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            if (Schema::hasColumn('cash_flows', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};

