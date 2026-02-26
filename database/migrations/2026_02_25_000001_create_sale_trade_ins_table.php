<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_trade_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->string('serial_number');
            $table->string('brand');
            $table->string('series')->nullable();
            $table->text('specs')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->decimal('trade_in_value', 15, 2)->comment('HPP - nilai laptop saat tukar tambah');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null')->comment('Produk baru yang dibuat dari laptop tukar');
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_trade_ins');
    }
};
