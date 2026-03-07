<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->index();
            $table->foreignId('distributor_id')->constrained()->onDelete('restrict');
            $table->string('location_type', 20); // warehouse | branch
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('restrict');
            $table->date('purchase_date')->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('termin', 100)->nullable(); // NET 30, Tunai, dll
            $table->date('due_date')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->timestamps();

            $table->index(['location_type', 'warehouse_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
