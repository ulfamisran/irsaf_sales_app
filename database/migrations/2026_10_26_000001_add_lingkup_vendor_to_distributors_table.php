<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->string('lingkup_vendor', 20)->default('eksternal')->after('phone')->index();
        });

        // Data existing otomatis eksternal
        DB::table('distributors')->update(['lingkup_vendor' => 'eksternal']);
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn('lingkup_vendor');
        });
    }
};
