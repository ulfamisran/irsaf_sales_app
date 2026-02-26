<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'phone')) {
                $table->string('phone', 30)->nullable()->after('address');
                $table->index('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'phone')) {
                $table->dropIndex(['phone']);
                $table->dropColumn('phone');
            }
        });
    }
};

