<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_materials', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('service_id')
                ->constrained('products')
                ->nullOnDelete();
            $table->index(['service_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_materials', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
