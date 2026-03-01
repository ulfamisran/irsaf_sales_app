<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('cancel_date')->nullable()->after('released_at');
            $table->foreignId('cancel_user_id')->nullable()->after('cancel_date')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable()->after('cancel_user_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->date('cancel_date')->nullable()->after('description');
            $table->foreignId('cancel_user_id')->nullable()->after('cancel_date')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable()->after('cancel_user_id');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->date('cancel_date')->nullable()->after('description');
            $table->foreignId('cancel_user_id')->nullable()->after('cancel_date')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable()->after('cancel_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cancel_user_id']);
            $table->dropColumn(['cancel_date', 'cancel_user_id', 'cancel_reason']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['cancel_user_id']);
            $table->dropColumn(['cancel_date', 'cancel_user_id', 'cancel_reason']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['cancel_user_id']);
            $table->dropColumn(['cancel_date', 'cancel_user_id', 'cancel_reason']);
        });
    }
};
