<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['distributions', 'unit_exchanges'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'client_ip')) {
                    if (Schema::hasColumn($table, 'user_id')) {
                        $blueprint->string('client_ip', 45)->nullable()->after('user_id');
                    } else {
                        $blueprint->string('client_ip', 45)->nullable();
                    }
                }
                if (! Schema::hasColumn($table, 'client_user_agent')) {
                    $blueprint->string('client_user_agent', 500)->nullable()->after('client_ip');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['distributions', 'unit_exchanges'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'client_user_agent')) {
                    $blueprint->dropColumn('client_user_agent');
                }
                if (Schema::hasColumn($table, 'client_ip')) {
                    $blueprint->dropColumn('client_ip');
                }
            });
        }
    }
};
