<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (! Schema::hasColumn('rentals', 'actual_return_date')) {
                $table->date('actual_return_date')->nullable()->after('return_date');
            }
            if (! Schema::hasColumn('rentals', 'return_recorded_at')) {
                $table->timestamp('return_recorded_at')->nullable()->after('actual_return_date');
            }
            if (! Schema::hasColumn('rentals', 'return_user_id')) {
                $table->foreignId('return_user_id')->nullable()->after('return_recorded_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('rentals', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('return_user_id');
            }
        });

        Schema::table('rental_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rental_items', 'is_returned')) {
                $table->boolean('is_returned')->default(false)->after('total');
            }
            if (! Schema::hasColumn('rental_items', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('is_returned');
            }
            if (! Schema::hasColumn('rental_items', 'return_condition')) {
                $table->string('return_condition', 20)->nullable()->after('returned_at');
            }
            if (! Schema::hasColumn('rental_items', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('return_condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'return_user_id')) {
                $table->dropConstrainedForeignId('return_user_id');
            }
            foreach (['actual_return_date', 'return_recorded_at', 'return_notes'] as $col) {
                if (Schema::hasColumn('rentals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('rental_items', function (Blueprint $table) {
            foreach (['is_returned', 'returned_at', 'return_condition', 'return_notes'] as $col) {
                if (Schema::hasColumn('rental_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
