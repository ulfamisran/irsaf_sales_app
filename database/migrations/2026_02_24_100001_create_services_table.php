<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->index();
            $table->foreignId('branch_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('laptop_type', 100)->comment('Jenis laptop (brand/model)');
            $table->text('laptop_detail')->nullable()->comment('Detail laptop (spesifikasi, serial dll)');
            $table->text('damage_description')->nullable()->comment('Deskripsi kerusakan');
            $table->decimal('service_cost', 15, 2)->default(0)->comment('Biaya service/HPP');
            $table->decimal('service_price', 15, 2)->default(0)->comment('Harga service');
            $table->decimal('total_paid', 15, 2)->default(0)->comment('Total pembayaran');
            $table->date('entry_date')->index()->comment('Tanggal masuk');
            $table->date('exit_date')->nullable()->index()->comment('Tanggal keluar/selesai');
            $table->string('pickup_status', 20)->default('belum_diambil')->comment('belum_diambil, sudah_diambil');
            $table->string('payment_status', 20)->default('belum_lunas')->comment('belum_lunas, lunas');
            $table->string('status', 20)->default('open')->index()->comment('open, completed, cancel');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
