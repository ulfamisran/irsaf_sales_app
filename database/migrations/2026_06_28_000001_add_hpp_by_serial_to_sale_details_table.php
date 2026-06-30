<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_details', 'hpp_by_serial')) {
                $table->json('hpp_by_serial')->nullable()->after('hpp')
                    ->comment('Snapshot HPP per serial saat transaksi/koreksi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'hpp_by_serial')) {
                $table->dropColumn('hpp_by_serial');
            }
        });
    }
};
