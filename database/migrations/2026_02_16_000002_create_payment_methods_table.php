<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_pembayaran', 50)->index(); // Tunai, Transfer, QRIS, dll
            $table->string('nama_bank', 100)->nullable()->index();
            $table->string('atas_nama_bank', 150)->nullable();
            $table->string('no_rekening', 50)->nullable()->index();
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['jenis_pembayaran', 'nama_bank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};

