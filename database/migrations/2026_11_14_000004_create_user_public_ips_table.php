<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_public_ips')) {
            Schema::create('user_public_ips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('ip_address', 45);
                $table->string('note', 100)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'ip_address']);
            });
        }

        $now = now();
        $rows = DB::table('users')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('user_public_ips')
                    ->whereColumn('user_public_ips.user_id', 'users.id');
            })
            ->pluck('id')
            ->map(fn ($id) => [
                'user_id' => $id,
                'ip_address' => '*',
                'note' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_public_ips')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_public_ips');
    }
};
