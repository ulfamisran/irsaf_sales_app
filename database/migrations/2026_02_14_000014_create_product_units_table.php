<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->string('serial_number')->unique()->index();

            // Current location of the unit (warehouse/branch)
            $table->string('location_type', 20)->index(); // warehouse, branch
            $table->unsignedBigInteger('location_id')->index();

            $table->string('status', 20)->default('in_stock')->index(); // in_stock, sold, damaged, etc
            $table->date('received_date')->nullable()->index();
            $table->timestamp('sold_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'location_type', 'location_id']);
            $table->index(['location_type', 'location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};

