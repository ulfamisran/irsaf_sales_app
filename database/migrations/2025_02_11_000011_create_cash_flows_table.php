<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->index(); // IN, OUT
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference_type', 50)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->date('transaction_date')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
