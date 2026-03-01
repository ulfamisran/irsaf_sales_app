<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_materials', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->index(['payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_materials', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });
    }
};
