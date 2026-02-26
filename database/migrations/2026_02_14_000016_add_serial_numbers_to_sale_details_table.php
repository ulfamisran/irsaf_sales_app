<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_details', 'serial_numbers')) {
                $table->longText('serial_numbers')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'serial_numbers')) {
                $table->dropColumn('serial_numbers');
            }
        });
    }
};

