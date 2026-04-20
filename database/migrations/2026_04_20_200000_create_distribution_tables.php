<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('from_location_type');
            $table->unsignedBigInteger('from_location_id');
            $table->string('to_location_type');
            $table->unsignedBigInteger('to_location_id');
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->date('distribution_date');
            $table->string('status')->default('active');
            $table->date('cancel_date')->nullable();
            $table->foreignId('cancel_user_id')->nullable()->constrained('users');
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index('distribution_date');
            $table->index('status');
        });

        Schema::create('distribution_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('biaya_distribusi_per_unit', 15, 2)->default(0);
            $table->decimal('hpp_per_unit', 15, 2)->default(0);
            $table->longText('serial_numbers')->nullable();
            $table->timestamps();
            $table->index(['distribution_id', 'product_id']);
        });

        Schema::create('distribution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['distribution_id', 'payment_method_id']);
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->unsignedBigInteger('distribution_id')->nullable()->after('id');
            $table->foreign('distribution_id')->references('id')->on('distributions')->nullOnDelete();
        });

        $this->backfillFromStockMutations();
    }

    private function backfillFromStockMutations(): void
    {
        $invoiceNumbers = DB::table('stock_mutations')
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->select('invoice_number')
            ->distinct()
            ->pluck('invoice_number');

        foreach ($invoiceNumbers as $invoiceNumber) {
            $mutations = DB::table('stock_mutations')
                ->where('invoice_number', $invoiceNumber)
                ->orderBy('id')
                ->get();

            if ($mutations->isEmpty()) {
                continue;
            }

            $first = $mutations->first();
            $isCancelled = $mutations->contains(fn ($m) => ($m->status ?? 'active') === 'cancelled');

            $totalBiaya = $mutations->sum(fn ($m) => (float) ($m->biaya_distribusi_per_unit ?? 0) * (int) $m->quantity);

            $mutationIds = $mutations->pluck('id')->all();
            $totalPaid = (float) DB::table('cash_flows')
                ->where('reference_type', 'distribution')
                ->where('type', 'IN')
                ->whereIn('reference_id', $mutationIds)
                ->sum('amount');

            $now = now();
            $distributionId = DB::table('distributions')->insertGetId([
                'invoice_number' => $invoiceNumber,
                'from_location_type' => $first->from_location_type,
                'from_location_id' => $first->from_location_id,
                'to_location_type' => $first->to_location_type,
                'to_location_id' => $first->to_location_id,
                'total' => round($totalBiaya, 2),
                'total_paid' => round($totalPaid, 2),
                'notes' => $first->notes,
                'user_id' => $first->user_id ?? 1,
                'distribution_date' => $first->mutation_date,
                'status' => $isCancelled ? 'cancelled' : 'active',
                'cancel_date' => $isCancelled ? $first->cancel_date : null,
                'cancel_user_id' => $isCancelled ? $first->cancel_user_id : null,
                'cancel_reason' => $isCancelled ? $first->cancel_reason : null,
                'created_at' => $first->created_at ?? $now,
                'updated_at' => $now,
            ]);

            foreach ($mutations as $m) {
                DB::table('distribution_details')->insert([
                    'distribution_id' => $distributionId,
                    'product_id' => $m->product_id,
                    'quantity' => $m->quantity,
                    'biaya_distribusi_per_unit' => $m->biaya_distribusi_per_unit ?? 0,
                    'hpp_per_unit' => $m->hpp_per_unit ?? 0,
                    'serial_numbers' => $m->serial_numbers,
                    'created_at' => $m->created_at ?? $now,
                    'updated_at' => $now,
                ]);

                DB::table('stock_mutations')
                    ->where('id', $m->id)
                    ->update(['distribution_id' => $distributionId]);
            }

            $cashFlows = DB::table('cash_flows')
                ->where('reference_type', 'distribution')
                ->where('type', 'IN')
                ->whereIn('reference_id', $mutationIds)
                ->get();

            foreach ($cashFlows as $cf) {
                DB::table('distribution_payments')->insert([
                    'distribution_id' => $distributionId,
                    'payment_method_id' => $cf->payment_method_id,
                    'amount' => $cf->amount,
                    'notes' => null,
                    'created_at' => $cf->created_at ?? $now,
                    'updated_at' => $now,
                ]);

                DB::table('cash_flows')
                    ->where('id', $cf->id)
                    ->update(['reference_id' => $distributionId]);
            }

            DB::table('cash_flows')
                ->where('reference_type', 'distribution')
                ->where('type', '!=', 'IN')
                ->whereIn('reference_id', $mutationIds)
                ->update(['reference_id' => $distributionId]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropForeign(['distribution_id']);
            $table->dropColumn('distribution_id');
        });
        Schema::dropIfExists('distribution_payments');
        Schema::dropIfExists('distribution_details');
        Schema::dropIfExists('distributions');
    }
};
