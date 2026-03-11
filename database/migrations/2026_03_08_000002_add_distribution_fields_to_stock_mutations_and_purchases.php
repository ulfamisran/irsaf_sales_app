<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->decimal('biaya_distribusi_per_unit', 15, 2)->default(0)->after('quantity');
            $table->foreignId('distribution_payment_method_id')->nullable()->after('biaya_distribusi_per_unit')
                ->constrained('payment_methods')->nullOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('stock_mutation_id')->nullable()->after('user_id')
                ->constrained('stock_mutations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('distribution_payment_method_id');
            $table->dropColumn('biaya_distribusi_per_unit');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_mutation_id');
        });
    }
};
