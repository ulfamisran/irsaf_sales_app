<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->unsignedInteger('quantity');
            $table->date('received_date')->index();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_goods');
    }
};
