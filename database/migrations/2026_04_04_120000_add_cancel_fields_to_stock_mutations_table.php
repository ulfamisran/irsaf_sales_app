<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index()->after('user_id');
            $table->date('cancel_date')->nullable()->after('status');
            $table->foreignId('cancel_user_id')->nullable()->after('cancel_date')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable()->after('cancel_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancel_user_id');
            $table->dropColumn(['status', 'cancel_date', 'cancel_reason']);
        });
    }
};
