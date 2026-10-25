<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->string('check_status', 20)->default('checked')->after('status')->index();
            $table->foreignId('purchase_id')->nullable()->after('product_id')->constrained('purchases')->nullOnDelete();
            $table->foreignId('purchase_detail_id')->nullable()->after('purchase_id')->constrained('purchase_details')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('check_status');
            $table->foreignId('checked_by')->nullable()->after('checked_at')->constrained('users')->nullOnDelete();
        });

        // Data lama: dianggap sudah dicek.
        DB::table('product_units')->update(['check_status' => 'checked']);

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->unsignedInteger('qty_baik')->default(0)->after('quantity');
            $table->unsignedInteger('qty_reture')->default(0)->after('qty_baik');
            $table->unsignedInteger('qty_pending')->default(0)->after('qty_reture');
        });

        // Data lama non-serial: qty_baik = quantity (sudah diterima penuh).
        DB::table('purchase_details')->update([
            'qty_baik' => DB::raw('quantity'),
            'qty_reture' => 0,
            'qty_pending' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn(['qty_baik', 'qty_reture', 'qty_pending']);
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn(['check_status', 'checked_at']);
            $table->dropConstrainedForeignId('purchase_detail_id');
            $table->dropConstrainedForeignId('purchase_id');
        });
    }
};
