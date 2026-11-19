<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_exchanges')) {
            Schema::create('unit_exchanges', function (Blueprint $table) {
                $table->id();
                $table->string('exchange_number', 30)->unique();
                $table->date('exchange_date');
                $table->foreignId('sale_id')->constrained('sales');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->timestamps();

                $table->index(['sale_id', 'exchange_date']);
            });
        }

        if (! Schema::hasTable('unit_exchange_items')) {
            Schema::create('unit_exchange_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_exchange_id')->constrained('unit_exchanges')->cascadeOnDelete();
                $table->foreignId('sale_detail_id')->nullable()->constrained('sale_details')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('old_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
                $table->foreignId('new_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
                $table->string('old_serial', 100);
                $table->string('new_serial', 100);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('price_difference', 15, 2)->default(0);
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_exchange_items');
        Schema::dropIfExists('unit_exchanges');
    }
};
