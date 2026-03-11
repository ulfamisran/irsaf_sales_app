<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->onDelete('restrict');
            $table->string('serial_number')->index();
            $table->date('recorded_date')->index();
            $table->text('damage_description');
            $table->decimal('harga_hpp', 15, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reactivated_at')->nullable();
            $table->foreignId('reactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recorded_date', 'reactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_goods');
    }
};
