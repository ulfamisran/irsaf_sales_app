<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_unit_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->string('serial_snapshot', 191)->nullable()->comment('Serial unit saat perubahan');
            $table->text('reason');
            $table->json('changes')->nullable()->comment('Field lama/baru yang diubah');
            $table->timestamps();

            $table->index(['product_unit_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_change_logs');
    }
};
