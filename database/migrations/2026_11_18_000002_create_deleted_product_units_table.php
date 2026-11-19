<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deleted_product_units')) {
            return;
        }

        Schema::create('deleted_product_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_unit_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('serial_number')->index();
            $table->string('sku', 120)->nullable();
            $table->string('product_label', 255)->nullable();
            $table->string('location_type', 20)->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('status', 20)->nullable();
            $table->text('reason');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->index();
            $table->timestamps();

            $table->index(['product_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_product_units');
    }
};
