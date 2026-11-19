<?php

use App\Services\UserActivityHistoryImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return;
        }

        if (! Schema::hasColumn('user_activity_logs', 'source')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->string('source', 40)->nullable()->after('occurred_at');
                $table->unsignedBigInteger('source_id')->nullable()->after('source');
                $table->unique(['source', 'source_id']);
            });
        }

        app(UserActivityHistoryImporter::class)->import();
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_activity_logs') || ! Schema::hasColumn('user_activity_logs', 'source')) {
            return;
        }

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
    }
};
