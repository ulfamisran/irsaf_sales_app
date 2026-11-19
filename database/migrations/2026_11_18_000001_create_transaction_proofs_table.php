<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_proofs')) {
            return;
        }

        Schema::create('transaction_proofs', function (Blueprint $table) {
            $table->id();
            $table->string('proofable_type', 40);
            $table->unsignedBigInteger('proofable_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proofable_type', 'proofable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_proofs');
    }
};
