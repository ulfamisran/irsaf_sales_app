<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_update_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 50)->index();
            $table->unsignedBigInteger('reference_id')->index();
            $table->string('reference_number', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at_action');
            $table->text('reason');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id', 'updated_at_action'], 'tx_update_logs_ref_idx');
        });

        foreach (['sale_payments', 'service_payments', 'rental_payments', 'purchase_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'cash_flow_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('cash_flow_id')->nullable()->after('id')->constrained('cash_flows')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['sale_payments', 'service_payments', 'rental_payments', 'purchase_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'cash_flow_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('cash_flow_id');
                });
            }
        }
        Schema::dropIfExists('transaction_update_logs');
    }
};
