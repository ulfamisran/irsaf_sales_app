<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('branch_id');
                $table->index('customer_id', 'sales_customer_id_idx');
            }

            if (! Schema::hasColumn('sales', 'status')) {
                // Existing data should be treated as released/final.
                $table->string('status', 20)->default('released')->after('sale_date')->index();
            }

            if (! Schema::hasColumn('sales', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('sales', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('sales', 'description')) {
                $table->text('description')->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('sales', 'released_at')) {
                $table->dateTime('released_at')->nullable()->after('status')->index();
            }
        });

        // Add FK with explicit name (avoid weird auto-generated names)
        if (Schema::hasColumn('sales', 'customer_id')) {
            $dbName = DB::getDatabaseName();
            $fkExists = DB::table('information_schema.table_constraints')
                ->where('constraint_schema', $dbName)
                ->where('table_name', 'sales')
                ->where('constraint_type', 'FOREIGN KEY')
                ->where('constraint_name', 'sales_customer_id_fk')
                ->exists();

            if (! $fkExists) {
                Schema::table('sales', function (Blueprint $table) {
                    $table->foreign('customer_id', 'sales_customer_id_fk')
                        ->references('id')
                        ->on('customers')
                        ->nullOnDelete();
                });
            }
        }

        // Backfill status for existing rows (safety for older DBs)
        if (Schema::hasColumn('sales', 'status')) {
            DB::table('sales')->whereNull('status')->update(['status' => 'released']);
        }
        if (Schema::hasColumn('sales', 'released_at')) {
            DB::table('sales')->whereNull('released_at')->update(['released_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'customer_id')) {
                // Drop explicit FK name if exists; fall back to column-based drop.
                try {
                    $table->dropForeign('sales_customer_id_fk');
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropIndex('sales_customer_id_idx');
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('sales', 'released_at')) {
                $table->dropColumn('released_at');
            }
            if (Schema::hasColumn('sales', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('sales', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('sales', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('sales', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

