<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            $table->foreignId('expense_category_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('expense_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
        });
    }
};

