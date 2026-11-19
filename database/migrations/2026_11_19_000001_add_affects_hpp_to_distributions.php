<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->boolean('affects_hpp')->nullable()->after('biaya_distribusi_per_unit');
        });

        Schema::table('distribution_details', function (Blueprint $table) {
            $table->boolean('affects_hpp')->nullable()->after('biaya_distribusi_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropColumn('affects_hpp');
        });

        Schema::table('distribution_details', function (Blueprint $table) {
            $table->dropColumn('affects_hpp');
        });
    }
};
