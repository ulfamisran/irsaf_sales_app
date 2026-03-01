<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('name', 150);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_materials');
    }
};
