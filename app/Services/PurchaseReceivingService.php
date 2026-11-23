<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReceivingLog;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseReceivingService
{
    public function __construct(
        protected StockMutationService $stockMutationService
    ) {}

    /**
     * @return array{
     *   invoice_total: float,
     *   recognized_debt: float,
     *   return_value: float,
     *   deferred_value: float,
     *   unchecked_value: float,
     *   check_status: string,
     *   check_status_label: string,
     *   paid: float,
     *   payment_status: string,
     *   payment_status_label: string,
     *   remaining_recognized: float,
     *   needs_check: bool
     * }
     */
    public function summarize(Purchase $purchase): array
    {
        return $this->reportSheet($purchase)['summary'];
    }

    /**
     * Detail pembelian dan hasil cek untuk cetak laporan.
     *
     * @return array{lines: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function reportSheet(Purchase $purchase): array
    {
        $purchase->loadMissing(['details.product.category', 'details.units', 'payments']);

        $invoiceTotal = round((float) $purchase->total, 2);
        $recognized = 0.0;
        $returnValue = 0.0;
        $deferred = 0.0;
        $unchecked = 0.0;
        $hasUnchecked = false;
        $hasChecked = false;
        $unitsByDetail = $this->serialUnitsByDetail($purchase);
        $lines = [];

        foreach ($purchase->details as $detail) {
            $line = $this->classifyDetail($detail, $unitsByDetail[(int) $detail->id] ?? collect());
            $lines[] = $line;
            $recognized += $line['recognized_value'];
            $returnValue += $line['return_value'];
            $deferred += $line['pending_value'];
            $unchecked += $line['unchecked_value'];
            $hasUnchecked = $hasUnchecked || $line['has_unchecked'];
            $hasChecked = $hasChecked || $line['has_checked'];
        }

        $recognized = round($recognized, 2);
        $returnValue = round($returnValue, 2);
        $deferred = round($deferred, 2);
        $unchecked = round($unchecked, 2);

        if (! $hasUnchecked && ! $hasChecked) {
            // Purchase without details yet or legacy fully matched via qty_baik = quantity
            $checkStatus = 'checked';
            $checkLabel = __('Selesai dicek');
        } elseif ($hasUnchecked && ! $hasChecked) {
            $checkStatus = 'unchecked';
            $checkLabel = __('Belum dicek');
        } elseif ($hasUnchecked && $hasChecked) {
            $checkStatus = 'partial';
            $checkLabel = __('Sebagian dicek');
        } else {
            $checkStatus = 'checked';
            $checkLabel = __('Selesai dicek');
        }

        $paid = round((float) $purchase->total_paid, 2);
        if ($paid <= 0 && $purchase->relationLoaded('payments')) {
            $paid = round((float) $purchase->payments->sum('amount'), 2);
        }

        $remainingRecognized = max(0, round($recognized - $paid, 2));
        if ($recognized <= 0.02) {
            $paymentStatus = 'no_recognized';
            $paymentLabel = __('Belum ada utang terakui');
        } elseif ($paid >= $recognized - 0.02) {
            $paymentStatus = 'paid';
            $paymentLabel = __('Lunas');
        } elseif ($paid > 0.02) {
            $paymentStatus = 'partial';
            $paymentLabel = __('Sebagian');
        } else {
            $paymentStatus = 'unpaid';
            $paymentLabel = __('Belum Lunas');
        }

        return [
            'lines' => $lines,
            'summary' => [
                'invoice_total' => $invoiceTotal,
                'recognized_debt' => $recognized,
                'return_value' => $returnValue,
                'deferred_value' => $deferred,
                'unchecked_value' => $unchecked,
                'check_status' => $checkStatus,
                'check_status_label' => $checkLabel,
                'paid' => $paid,
                'payment_status' => $paymentStatus,
                'payment_status_label' => $paymentLabel,
                'remaining_recognized' => $remainingRecognized,
                'needs_check' => $hasUnchecked,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductUnit>  $units
     * @return array<string, mixed>
     */
    private function classifyDetail(PurchaseDetail $detail, $units): array
    {
        $unitPrice = round((float) $detail->unit_price, 2);
        if ($unitPrice <= 0 && (int) $detail->quantity > 0) {
            $unitPrice = round((float) $detail->subtotal / (int) $detail->quantity, 2);
        }

        $product = $detail->product;
        $requiresSerial = $product?->category?->requires_serial ?? true;
        $serialsOnDetail = $this->parseDetailSerials($detail->serial_numbers);
        $recognizedQty = 0;
        $pendingQty = 0;
        $returnQty = 0;
        $uncheckedQty = 0;
        $recognized = 0.0;
        $returnValue = 0.0;
        $deferred = 0.0;
        $unchecked = 0.0;
        $hasUnchecked = false;
        $hasChecked = false;
        $unitRows = [];

        if ($requiresSerial || $serialsOnDetail !== [] || $units->isNotEmpty()) {
            foreach ($units as $unit) {
                $hpp = $this->unitDebtAmount($unit, $unitPrice);
                if ($this->unitCountsAsRecognizedDebt($unit)) {
                    $hasChecked = true;
                    $recognized += $unitPrice;
                    $recognizedQty++;
                    $unitRows[] = $this->reportUnitRow((string) $unit->serial_number, 'diterima', __('Diterima'), $unitPrice);
                } elseif ($unit->check_status === ProductUnit::CHECK_UNCHECKED) {
                    $unchecked += $hpp;
                    $uncheckedQty++;
                    $hasUnchecked = true;
                    $unitRows[] = $this->reportUnitRow((string) $unit->serial_number, 'unchecked', __('Belum dicek'), $hpp);
                } elseif ($this->normalizedUnitStatus($unit) === ProductUnit::STATUS_RETURE) {
                    $hasChecked = true;
                    $returnValue += $hpp;
                    $returnQty++;
                    $unitRows[] = $this->reportUnitRow((string) $unit->serial_number, 'retur', __('Diretur'), $hpp);
                } elseif ($this->normalizedUnitStatus($unit) === ProductUnit::STATUS_PENDING) {
                    $hasChecked = true;
                    $deferred += $hpp;
                    $pendingQty++;
                    $unitRows[] = $this->reportUnitRow((string) $unit->serial_number, 'pending', __('Dipending'), $hpp);
                }
            }

            // Tidak ada unit teratribusi: hitung sisa dari qty detail (termasuk serial kosong).
            if ($units->isEmpty()) {
                $recognizedQty = (int) $detail->qty_baik;
                $returnQty = (int) $detail->qty_reture;
                $pendingQty = (int) $detail->qty_pending;
                $uncheckedQty = max(0, (int) $detail->quantity - $recognizedQty - $returnQty - $pendingQty);
                $recognized += $recognizedQty * $unitPrice;
                $returnValue += $returnQty * $unitPrice;
                $deferred += $pendingQty * $unitPrice;
                if ($uncheckedQty > 0) {
                    $unchecked += $uncheckedQty * $unitPrice;
                    $hasUnchecked = true;
                }
                if ($recognizedQty + $returnQty + $pendingQty > 0) {
                    $hasChecked = true;
                }
                $cursor = 0;
                $this->appendQtyRows($unitRows, $serialsOnDetail, $cursor, 'diterima', __('Diterima'), $recognizedQty, $unitPrice);
                $this->appendQtyRows($unitRows, $serialsOnDetail, $cursor, 'retur', __('Diretur'), $returnQty, $unitPrice);
                $this->appendQtyRows($unitRows, $serialsOnDetail, $cursor, 'pending', __('Dipending'), $pendingQty, $unitPrice);
                $this->appendQtyRows($unitRows, $serialsOnDetail, $cursor, 'unchecked', __('Belum dicek'), $uncheckedQty, $unitPrice);
            }
        } else {
            $recognizedQty = (int) $detail->qty_baik;
            $returnQty = (int) $detail->qty_reture;
            $pendingQty = (int) $detail->qty_pending;
            $uncheckedQty = max(0, (int) $detail->quantity - $recognizedQty - $returnQty - $pendingQty);
            $recognized += $recognizedQty * $unitPrice;
            $returnValue += $returnQty * $unitPrice;
            $deferred += $pendingQty * $unitPrice;
            $unchecked += $uncheckedQty * $unitPrice;
            if ($uncheckedQty > 0) {
                $hasUnchecked = true;
            }
            if ($recognizedQty + $returnQty + $pendingQty > 0) {
                $hasChecked = true;
            }
            $cursor = 0;
            $this->appendQtyRows($unitRows, [], $cursor, 'diterima', __('Diterima'), $recognizedQty, $unitPrice);
            $this->appendQtyRows($unitRows, [], $cursor, 'retur', __('Diretur'), $returnQty, $unitPrice);
            $this->appendQtyRows($unitRows, [], $cursor, 'pending', __('Dipending'), $pendingQty, $unitPrice);
            $this->appendQtyRows($unitRows, [], $cursor, 'unchecked', __('Belum dicek'), $uncheckedQty, $unitPrice);
        }

        $name = trim(implode(' ', array_filter([
            $product?->sku,
            $product?->brand,
            $product?->series,
        ], fn ($part) => trim((string) $part) !== '')));

        return [
            'product' => $name !== '' ? $name : '-',
            'serials' => $serialsOnDetail === [] ? '' : implode(', ', $serialsOnDetail),
            'quantity' => (int) $detail->quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round((float) $detail->subtotal, 2),
            'recognized_qty' => $recognizedQty,
            'recognized_value' => round($recognized, 2),
            'pending_qty' => $pendingQty,
            'pending_value' => round($deferred, 2),
            'return_qty' => $returnQty,
            'return_value' => round($returnValue, 2),
            'unchecked_qty' => $uncheckedQty,
            'unchecked_value' => round($unchecked, 2),
            'has_unchecked' => $hasUnchecked,
            'has_checked' => $hasChecked,
            'units' => $unitRows,
        ];
    }

    /**
     * @return array{serial: string, qty: int, status: string, label: string, amount: float}
     */
    private function reportUnitRow(string $serial, string $status, string $label, float $amount, int $qty = 1): array
    {
        return [
            'serial' => trim($serial),
            'qty' => $qty,
            'status' => $status,
            'label' => $label,
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @param  list<array{serial: string, qty: int, status: string, label: string, amount: float}>  $rows
     * @param  list<string>  $serials
     */
    private function appendQtyRows(array &$rows, array $serials, int &$cursor, string $status, string $label, int $qty, float $unitPrice): void
    {
        if ($qty <= 0) {
            return;
        }
        if ($serials !== []) {
            for ($i = 0; $i < $qty; $i++) {
                $rows[] = $this->reportUnitRow($serials[$cursor] ?? '', $status, $label, $unitPrice);
                $cursor++;
            }

            return;
        }

        $rows[] = $this->reportUnitRow('', $status, $label, $unitPrice * $qty, $qty);
    }

    public function purchaseNeedsCheck(Purchase $purchase): bool
    {
        return $this->summarize($purchase)['needs_check'];
    }

    public function pendingCount(User $user): int
    {
        $key = 'purchase_receiving.pending_count.'.$user->id;
        if (request()->attributes->has($key)) {
            return (int) request()->attributes->get($key);
        }

        $query = Purchase::query()
            ->where('status', '!=', Purchase::STATUS_CANCELLED)
            ->where(function ($inner) {
                $inner->where('jenis_pembelian', Purchase::JENIS_PEMBELIAN_UNIT)
                    ->orWhereNull('jenis_pembelian')
                    ->orWhere('jenis_pembelian', '');
            });
        $this->constrainNeedingCheck($query);

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KEPALA_CABANG]) && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                $query->where('warehouse_id', $user->warehouse_id);
            } else {
                request()->attributes->set($key, 0);

                return 0;
            }
        }

        $count = $query->with(['details.product.category', 'details.units', 'payments'])
            ->get()
            ->filter(fn (Purchase $p) => $this->purchaseNeedsCheck($p))
            ->count();
        request()->attributes->set($key, $count);

        return $count;
    }

    /**
     * @param  array<int, array{unit_id: int, result: string}>  $unitResults  result: baik|reture|pending
     * @param  array<int, array{purchase_detail_id: int, qty_baik?: int, qty_reture?: int, qty_pending?: int}>  $qtyResults
     */
    public function applyCheck(Purchase $purchase, array $unitResults, array $qtyResults, int $userId): void
    {
        if ($purchase->isCancelled()) {
            throw new InvalidArgumentException(__('Pembelian yang dibatalkan tidak dapat dicek.'));
        }

        DB::transaction(function () use ($purchase, $unitResults, $qtyResults, $userId) {
            $now = Carbon::now();

            foreach ($unitResults as $row) {
                $unitId = (int) ($row['unit_id'] ?? 0);
                $result = (string) ($row['result'] ?? '');
                if ($unitId <= 0 || $result === '') {
                    continue;
                }

                $unit = ProductUnit::whereKey($unitId)
                    ->where('purchase_id', $purchase->id)
                    ->lockForUpdate()
                    ->first();
                if (! $unit) {
                    throw new InvalidArgumentException(__('Unit tidak ditemukan pada pembelian ini.'));
                }
                if ($unit->check_status !== ProductUnit::CHECK_UNCHECKED) {
                    throw new InvalidArgumentException(
                        __('Serial :sn sudah dicek sebelumnya.', ['sn' => $unit->serial_number])
                    );
                }

                $newStatus = match ($result) {
                    'baik' => ProductUnit::STATUS_IN_STOCK,
                    'reture' => ProductUnit::STATUS_RETURE,
                    'pending' => ProductUnit::STATUS_PENDING,
                    default => throw new InvalidArgumentException(__('Hasil cek tidak valid.')),
                };

                $unit->update([
                    'status' => $newStatus,
                    'check_status' => ProductUnit::CHECK_CHECKED,
                    'checked_at' => $now,
                    'checked_by' => $userId,
                ]);

                PurchaseReceivingLog::create([
                    'purchase_id' => $purchase->id,
                    'purchase_detail_id' => $unit->purchase_detail_id,
                    'product_unit_id' => $unit->id,
                    'result' => $result,
                    'previous_result' => null,
                    'qty' => 1,
                    'checked_by' => $userId,
                    'checked_at' => $now,
                ]);

                if ($newStatus === ProductUnit::STATUS_IN_STOCK) {
                    $this->stockMutationService->recalculateStockQuantityIfExists(
                        (int) $unit->product_id,
                        (string) $unit->location_type,
                        (int) $unit->location_id
                    );
                }
            }

            foreach ($qtyResults as $row) {
                $detailId = (int) ($row['purchase_detail_id'] ?? 0);
                $addBaik = max(0, (int) ($row['qty_baik'] ?? 0));
                $addReture = max(0, (int) ($row['qty_reture'] ?? 0));
                $addPending = max(0, (int) ($row['qty_pending'] ?? 0));
                $addTotal = $addBaik + $addReture + $addPending;
                if ($detailId <= 0 || $addTotal <= 0) {
                    continue;
                }

                $detail = PurchaseDetail::whereKey($detailId)
                    ->where('purchase_id', $purchase->id)
                    ->lockForUpdate()
                    ->first();
                if (! $detail) {
                    throw new InvalidArgumentException(__('Detail pembelian tidak ditemukan.'));
                }

                $left = max(0, (int) $detail->quantity - (int) $detail->qty_baik - (int) $detail->qty_reture - (int) $detail->qty_pending);
                if ($addTotal > $left) {
                    throw new InvalidArgumentException(
                        __('Qty cek melebihi sisa belum dicek (tersisa :left).', ['left' => $left])
                    );
                }

                $detail->update([
                    'qty_baik' => (int) $detail->qty_baik + $addBaik,
                    'qty_reture' => (int) $detail->qty_reture + $addReture,
                    'qty_pending' => (int) $detail->qty_pending + $addPending,
                ]);

                $locType = $purchase->warehouse_id ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH;
                $locId = $purchase->warehouse_id ?: (int) $purchase->branch_id;
                $product = Product::findOrFail((int) $detail->product_id);

                foreach ([
                    PurchaseReceivingLog::RESULT_BAIK => $addBaik,
                    PurchaseReceivingLog::RESULT_RETURE => $addReture,
                    PurchaseReceivingLog::RESULT_PENDING => $addPending,
                ] as $result => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }
                    PurchaseReceivingLog::create([
                        'purchase_id' => $purchase->id,
                        'purchase_detail_id' => $detail->id,
                        'product_unit_id' => null,
                        'result' => $result,
                        'previous_result' => null,
                        'qty' => $qty,
                        'checked_by' => $userId,
                        'checked_at' => $now,
                    ]);
                }

                if ($addBaik > 0) {
                    $this->stockMutationService->addStock(
                        $product,
                        $locType,
                        $locId,
                        $addBaik,
                        $userId,
                        null,
                        $now->toDateString(),
                        (float) $detail->unit_price,
                        (float) ($product->selling_price ?? 0),
                        false,
                        null,
                        null,
                        false
                    );
                }
            }
        });
    }

    /**
     * Update hasil cek yang sudah tersimpan (riwayat).
     */
    public function updateCheck(PurchaseReceivingLog $log, string $newResult, int $userId, ?string $notes = null): void
    {
        if (! in_array($newResult, [
            PurchaseReceivingLog::RESULT_BAIK,
            PurchaseReceivingLog::RESULT_RETURE,
            PurchaseReceivingLog::RESULT_PENDING,
        ], true)) {
            throw new InvalidArgumentException(__('Hasil cek tidak valid.'));
        }

        $oldResult = (string) $log->result;
        if ($oldResult === $newResult) {
            throw new InvalidArgumentException(__('Hasil cek tidak berubah.'));
        }

        $purchase = Purchase::findOrFail((int) $log->purchase_id);
        if ($purchase->isCancelled()) {
            throw new InvalidArgumentException(__('Pembelian yang dibatalkan tidak dapat diubah hasil ceknya.'));
        }

        DB::transaction(function () use ($log, $newResult, $oldResult, $userId, $notes, $purchase) {
            $now = Carbon::now();
            $qty = max(1, (int) $log->qty);

            if ($log->product_unit_id) {
                $unit = ProductUnit::whereKey($log->product_unit_id)->lockForUpdate()->first();
                if (! $unit) {
                    throw new InvalidArgumentException(__('Unit tidak ditemukan.'));
                }
                $newStatus = match ($newResult) {
                    PurchaseReceivingLog::RESULT_BAIK => ProductUnit::STATUS_IN_STOCK,
                    PurchaseReceivingLog::RESULT_RETURE => ProductUnit::STATUS_RETURE,
                    PurchaseReceivingLog::RESULT_PENDING => ProductUnit::STATUS_PENDING,
                };

                // Serial yang sudah sold/keep/disewa/dll tetap pada status stok terakhir.
                // Hasil cek tetap tersimpan.
                $keepCurrentStockStatus = ! $this->checkResultMayReplaceStockStatus((string) $unit->status);
                $unit->update([
                    'status' => $keepCurrentStockStatus ? $unit->status : $newStatus,
                    'check_status' => ProductUnit::CHECK_CHECKED,
                    'checked_at' => $now,
                    'checked_by' => $userId,
                ]);

                if (! $keepCurrentStockStatus) {
                    $this->stockMutationService->recalculateStockQuantityIfExists(
                        (int) $unit->product_id,
                        (string) $unit->location_type,
                        (int) $unit->location_id
                    );
                }
            } else {
                $detail = PurchaseDetail::whereKey($log->purchase_detail_id)->lockForUpdate()->first();
                if (! $detail) {
                    throw new InvalidArgumentException(__('Detail pembelian tidak ditemukan.'));
                }

                $baik = (int) $detail->qty_baik;
                $reture = (int) $detail->qty_reture;
                $pending = (int) $detail->qty_pending;

                // Kurangi dari hasil lama
                if ($oldResult === PurchaseReceivingLog::RESULT_BAIK) {
                    $baik = max(0, $baik - $qty);
                } elseif ($oldResult === PurchaseReceivingLog::RESULT_RETURE) {
                    $reture = max(0, $reture - $qty);
                } elseif ($oldResult === PurchaseReceivingLog::RESULT_PENDING) {
                    $pending = max(0, $pending - $qty);
                }

                // Tambah ke hasil baru
                if ($newResult === PurchaseReceivingLog::RESULT_BAIK) {
                    $baik += $qty;
                } elseif ($newResult === PurchaseReceivingLog::RESULT_RETURE) {
                    $reture += $qty;
                } elseif ($newResult === PurchaseReceivingLog::RESULT_PENDING) {
                    $pending += $qty;
                }

                if ($baik + $reture + $pending > (int) $detail->quantity) {
                    throw new InvalidArgumentException(__('Qty hasil cek melebihi qty pembelian.'));
                }

                $detail->update([
                    'qty_baik' => $baik,
                    'qty_reture' => $reture,
                    'qty_pending' => $pending,
                ]);

                $product = Product::findOrFail((int) $detail->product_id);
                $locType = $purchase->warehouse_id ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH;
                $locId = $purchase->warehouse_id ?: (int) $purchase->branch_id;

                // Stok: keluar dari baik / masuk ke baik
                if ($oldResult === PurchaseReceivingLog::RESULT_BAIK && $newResult !== PurchaseReceivingLog::RESULT_BAIK) {
                    $this->stockMutationService->reduceStock($product, $locType, $locId, $qty);
                } elseif ($oldResult !== PurchaseReceivingLog::RESULT_BAIK && $newResult === PurchaseReceivingLog::RESULT_BAIK) {
                    $this->stockMutationService->addStock(
                        $product,
                        $locType,
                        $locId,
                        $qty,
                        $userId,
                        null,
                        $now->toDateString(),
                        (float) $detail->unit_price,
                        (float) ($product->selling_price ?? 0),
                        false,
                        null,
                        null,
                        false
                    );
                }
            }

            $log->update([
                'previous_result' => $oldResult,
                'result' => $newResult,
                'checked_by' => $userId,
                'checked_at' => $now,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Resolve deferred (pending) unit/qty into in-stock (baik) or reture.
     *
     * @param  array{unit_id?: int, purchase_detail_id?: int, qty?: int, action: string}  $item
     *        action: diterima|diretur
     */
    public function resolveDeferred(Purchase $purchase, array $item, int $userId): void
    {
        $action = (string) ($item['action'] ?? '');
        $newResult = match ($action) {
            'diterima' => PurchaseReceivingLog::RESULT_BAIK,
            'diretur' => PurchaseReceivingLog::RESULT_RETURE,
            default => throw new InvalidArgumentException(__('Aksi penangguhan tidak valid.')),
        };

        $unitId = (int) ($item['unit_id'] ?? 0);
        $detailId = (int) ($item['purchase_detail_id'] ?? 0);

        if ($unitId > 0) {
            $log = PurchaseReceivingLog::where('purchase_id', $purchase->id)
                ->where('product_unit_id', $unitId)
                ->where('result', PurchaseReceivingLog::RESULT_PENDING)
                ->orderByDesc('id')
                ->first();

            if ($log) {
                $this->updateCheck($log, $newResult, $userId, __('Resolusi penangguhan'));

                return;
            }

            // Fallback: unit pending tanpa log
            $unit = ProductUnit::whereKey($unitId)
                ->where('purchase_id', $purchase->id)
                ->where('status', ProductUnit::STATUS_PENDING)
                ->first();
            if (! $unit) {
                throw new InvalidArgumentException(__('Unit penangguhan tidak ditemukan.'));
            }

            DB::transaction(function () use ($purchase, $unit, $newResult, $userId) {
                $now = Carbon::now();
                $newStatus = $newResult === PurchaseReceivingLog::RESULT_BAIK
                    ? ProductUnit::STATUS_IN_STOCK
                    : ProductUnit::STATUS_RETURE;

                $unit->update([
                    'status' => $newStatus,
                    'check_status' => ProductUnit::CHECK_CHECKED,
                    'checked_at' => $now,
                    'checked_by' => $userId,
                ]);

                PurchaseReceivingLog::create([
                    'purchase_id' => $purchase->id,
                    'purchase_detail_id' => $unit->purchase_detail_id,
                    'product_unit_id' => $unit->id,
                    'result' => $newResult,
                    'previous_result' => PurchaseReceivingLog::RESULT_PENDING,
                    'qty' => 1,
                    'checked_by' => $userId,
                    'checked_at' => $now,
                    'notes' => __('Resolusi penangguhan'),
                ]);

                if ($newStatus === ProductUnit::STATUS_IN_STOCK) {
                    $this->stockMutationService->recalculateStockQuantityIfExists(
                        (int) $unit->product_id,
                        (string) $unit->location_type,
                        (int) $unit->location_id
                    );
                }
            });

            return;
        }

        if ($detailId <= 0) {
            throw new InvalidArgumentException(__('Item penangguhan tidak valid.'));
        }

        $qty = max(1, (int) ($item['qty'] ?? 0));
        $log = PurchaseReceivingLog::where('purchase_id', $purchase->id)
            ->where('purchase_detail_id', $detailId)
            ->whereNull('product_unit_id')
            ->where('result', PurchaseReceivingLog::RESULT_PENDING)
            ->orderByDesc('id')
            ->first();

        if ($log) {
            $resolveQty = min($qty, (int) $log->qty);
            if ($resolveQty <= 0) {
                throw new InvalidArgumentException(__('Qty penangguhan tidak valid.'));
            }

            if ($resolveQty === (int) $log->qty) {
                $this->updateCheck($log, $newResult, $userId, __('Resolusi penangguhan'));

                return;
            }

            // Partial: split log then update the resolved portion
            DB::transaction(function () use ($log, $resolveQty, $newResult, $userId) {
                $remaining = (int) $log->qty - $resolveQty;
                $log->update(['qty' => $remaining]);

                $split = PurchaseReceivingLog::create([
                    'purchase_id' => $log->purchase_id,
                    'purchase_detail_id' => $log->purchase_detail_id,
                    'product_unit_id' => null,
                    'result' => PurchaseReceivingLog::RESULT_PENDING,
                    'previous_result' => null,
                    'qty' => $resolveQty,
                    'checked_by' => $log->checked_by,
                    'checked_at' => $log->checked_at,
                    'notes' => $log->notes,
                ]);

                $this->updateCheck($split, $newResult, $userId, __('Resolusi penangguhan'));
            });

            return;
        }

        // Fallback without log: adjust detail qty directly
        DB::transaction(function () use ($purchase, $detailId, $qty, $newResult, $userId) {
            $detail = PurchaseDetail::whereKey($detailId)
                ->where('purchase_id', $purchase->id)
                ->lockForUpdate()
                ->first();
            if (! $detail || (int) $detail->qty_pending < $qty) {
                throw new InvalidArgumentException(__('Qty penangguhan tidak mencukupi.'));
            }

            $baik = (int) $detail->qty_baik;
            $reture = (int) $detail->qty_reture;
            $pending = (int) $detail->qty_pending - $qty;
            if ($newResult === PurchaseReceivingLog::RESULT_BAIK) {
                $baik += $qty;
            } else {
                $reture += $qty;
            }

            $detail->update([
                'qty_baik' => $baik,
                'qty_reture' => $reture,
                'qty_pending' => $pending,
            ]);

            $now = Carbon::now();
            PurchaseReceivingLog::create([
                'purchase_id' => $purchase->id,
                'purchase_detail_id' => $detail->id,
                'product_unit_id' => null,
                'result' => $newResult,
                'previous_result' => PurchaseReceivingLog::RESULT_PENDING,
                'qty' => $qty,
                'checked_by' => $userId,
                'checked_at' => $now,
                'notes' => __('Resolusi penangguhan'),
            ]);

            if ($newResult === PurchaseReceivingLog::RESULT_BAIK) {
                $product = Product::findOrFail((int) $detail->product_id);
                $locType = $purchase->warehouse_id ? Stock::LOCATION_WAREHOUSE : Stock::LOCATION_BRANCH;
                $locId = $purchase->warehouse_id ?: (int) $purchase->branch_id;
                $this->stockMutationService->addStock(
                    $product,
                    $locType,
                    $locId,
                    $qty,
                    $userId,
                    null,
                    $now->toDateString(),
                    (float) $detail->unit_price,
                    (float) ($product->selling_price ?? 0),
                    false,
                    null,
                    null,
                    false
                );
            }
        });
    }

    /**
     * Recognized (baik) units/qty for display on debt screens.
     *
     * @return list<array{type: string, label: string, serial?: string, qty: int, value: float}>
     */
    public function listRecognizedItems(Purchase $purchase): array
    {
        $purchase->loadMissing(['details.product.category', 'details.units', 'details.product']);
        $items = [];
        $unitsByDetail = $this->serialUnitsByDetail($purchase);

        foreach ($purchase->details as $detail) {
            $requiresSerial = $detail->product?->category?->requires_serial ?? true;
            $productLabel = trim(($detail->product->sku ?? '').' '.($detail->product->brand ?? '').' '.($detail->product->series ?? '')) ?: __('Produk');
            $unitPrice = round((float) $detail->unit_price, 2);
            if ($unitPrice <= 0 && (int) $detail->quantity > 0) {
                $unitPrice = round((float) $detail->subtotal / (int) $detail->quantity, 2);
            }
            $serialsOnDetail = $this->parseDetailSerials($detail->serial_numbers);
            $units = $unitsByDetail[(int) $detail->id] ?? collect();

            if ($requiresSerial || $serialsOnDetail !== [] || $units->isNotEmpty()) {
                foreach ($units as $unit) {
                    if (! $this->unitCountsAsRecognizedDebt($unit)) {
                        continue;
                    }
                    $items[] = [
                        'type' => 'serial',
                        'label' => $productLabel,
                        'serial' => (string) $unit->serial_number,
                        'qty' => 1,
                        'value' => $unitPrice,
                    ];
                }
            } elseif ((int) $detail->qty_baik > 0) {
                $items[] = [
                    'type' => 'qty',
                    'label' => $productLabel,
                    'qty' => (int) $detail->qty_baik,
                    'value' => (int) $detail->qty_baik * $unitPrice,
                ];
            }
        }

        return $items;
    }

    /**
     * Deferred (pending) units/qty for penangguhan tab.
     *
     * @return list<array{type: string, unit_id?: int, purchase_detail_id?: int, label: string, serial?: string, qty: int, value: float}>
     */
    public function listDeferredItems(Purchase $purchase): array
    {
        $purchase->loadMissing(['details.product.category', 'details.units', 'details.product']);
        $items = [];

        foreach ($purchase->details as $detail) {
            $requiresSerial = $detail->product?->category?->requires_serial ?? true;
            $productLabel = trim(($detail->product->sku ?? '').' '.($detail->product->brand ?? '').' '.($detail->product->series ?? '')) ?: __('Produk');
            $unitPrice = round((float) $detail->unit_price, 2);

            if ($requiresSerial) {
                foreach ($detail->units as $unit) {
                    if ($unit->status !== ProductUnit::STATUS_PENDING) {
                        continue;
                    }
                    $hpp = round((float) ($unit->harga_hpp ?: $unitPrice), 2);
                    $items[] = [
                        'type' => 'serial',
                        'unit_id' => (int) $unit->id,
                        'label' => $productLabel,
                        'serial' => (string) $unit->serial_number,
                        'qty' => 1,
                        'value' => $hpp,
                    ];
                }
            } elseif ((int) $detail->qty_pending > 0) {
                $items[] = [
                    'type' => 'qty',
                    'purchase_detail_id' => (int) $detail->id,
                    'label' => $productLabel,
                    'qty' => (int) $detail->qty_pending,
                    'value' => (int) $detail->qty_pending * $unitPrice,
                ];
            }
        }

        return $items;
    }

    /**
     * Utang terakui = unit yang sudah diterima baik.
     * Status stok berikutnya (masih in_stock atau sudah sold) tidak mengurangi utang.
     *
     * @return list<string>
     */
    private function recognizedDebtStatuses(): array
    {
        return [
            ProductUnit::STATUS_IN_STOCK,
            ProductUnit::STATUS_SOLD,
            ProductUnit::STATUS_KEEP,
            ProductUnit::STATUS_IN_RENT,
            ProductUnit::STATUS_RESERVED,
        ];
    }

    private function normalizedUnitStatus(ProductUnit $unit): string
    {
        return strtolower(trim((string) $unit->status));
    }

    private function unitCountsAsRecognizedDebt(ProductUnit $unit): bool
    {
        // in_stock dan sold tetap utang. Flag cek tidak boleh menggugurkan unit yang sudah masuk stok lalu terjual.
        return in_array($this->normalizedUnitStatus($unit), $this->recognizedDebtStatuses(), true);
    }

    /**
     * Harga utang per unit. HPP 0 atau kosong memakai harga beli di invoice
     * supaya penjualan tidak membuat nilai utang menjadi nol.
     */
    private function unitDebtAmount(ProductUnit $unit, float $unitPrice): float
    {
        $hpp = $unit->harga_hpp;
        if ($hpp === null || (float) $hpp <= 0) {
            return round($unitPrice, 2);
        }

        return round((float) $hpp, 2);
    }

    /**
     * @return list<string>
     */
    private function parseDetailSerials(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $serials = preg_split('/[\r\n,]+/', $text) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($sn) => trim((string) $sn),
            $serials
        ), static fn ($sn) => $sn !== '')));
    }

    public function constrainNeedingCheck(Builder $query): void
    {
        $recognized = $this->recognizedDebtStatuses();

        $placeholders = implode(', ', array_fill(0, count($recognized), '?'));
        $query->where(function (Builder $inner) use ($recognized, $placeholders) {
            $inner->whereHas('details.units', function (Builder $units) use ($recognized, $placeholders) {
                $units->where('check_status', ProductUnit::CHECK_UNCHECKED)
                    ->whereRaw('LOWER(TRIM(status)) NOT IN ('.$placeholders.')', $recognized);
            })->orWhereHas('details', function (Builder $details) {
                $details->whereRaw('COALESCE(quantity, 0) > COALESCE(qty_baik, 0) + COALESCE(qty_reture, 0) + COALESCE(qty_pending, 0)')
                    ->whereDoesntHave('units');
            });
        });
    }

    /**
     * Unit serial milik pembelian, termasuk yang purchase_detail_id-nya kosong
     * selama serialnya masih tercantum di baris pembelian.
     *
     * @return array<int, \Illuminate\Support\Collection<int, ProductUnit>>
     */
    private function serialUnitsByDetail(Purchase $purchase): array
    {
        $purchase->loadMissing(['details']);
        $detailIds = $purchase->details->pluck('id')->map(fn ($id) => (int) $id)->all();

        $units = ProductUnit::query()
            ->where(function ($q) use ($purchase, $detailIds) {
                $q->where('purchase_id', $purchase->id);
                if ($detailIds !== []) {
                    $q->orWhereIn('purchase_detail_id', $detailIds);
                }
            })
            ->get();

        $serials = [];
        foreach ($purchase->details as $detail) {
            foreach ($this->parseDetailSerials($detail->serial_numbers) as $sn) {
                $serials[] = $sn;
            }
        }
        $serials = array_values(array_unique($serials));
        if ($serials !== []) {
            $knownIds = $units->pluck('id')->all();
            $serialKeys = array_values(array_filter(array_map(
                fn (string $sn) => $this->serialKey($sn),
                $serials
            )));
            $legacy = ProductUnit::query()
                ->when($knownIds !== [], fn ($q) => $q->whereNotIn('id', $knownIds))
                ->where(function ($q) use ($serials, $serialKeys) {
                    $q->whereIn('serial_number', $serials);
                    if ($serialKeys !== []) {
                        $q->orWhereIn(
                            DB::raw("UPPER(REPLACE(REPLACE(REPLACE(TRIM(serial_number), '-', ''), ' ', ''), '.', ''))"),
                            $serialKeys
                        );
                    }
                })
                ->get();
            $units = $units->concat($legacy);
        }

        $byDetail = [];
        $used = [];
        foreach ($purchase->details as $detail) {
            $detailId = (int) $detail->id;
            $serialLookup = [];
            foreach ($this->parseDetailSerials($detail->serial_numbers) as $sn) {
                $key = $this->serialKey($sn);
                if ($key !== '') {
                    $serialLookup[$key] = true;
                }
            }

            $matched = $units->filter(function (ProductUnit $unit) use ($detailId, $detailIds, $serialLookup, &$used) {
                if (isset($used[$unit->id])) {
                    return false;
                }
                if ((int) $unit->purchase_detail_id === $detailId) {
                    return true;
                }
                $key = $this->serialKey((string) $unit->serial_number);
                if ($key === '' || ! isset($serialLookup[$key])) {
                    return false;
                }
                if ($unit->purchase_detail_id && in_array((int) $unit->purchase_detail_id, $detailIds, true)) {
                    return false;
                }

                return true;
            })->values();

            foreach ($matched as $unit) {
                $used[(int) $unit->id] = true;
            }
            $byDetail[$detailId] = $matched;
        }

        foreach ($units as $unit) {
            if (isset($used[(int) $unit->id])) {
                continue;
            }
            if ((int) $unit->purchase_id !== (int) $purchase->id) {
                continue;
            }
            $targetId = null;
            foreach ($purchase->details as $detail) {
                if ((int) $detail->product_id === (int) $unit->product_id) {
                    $targetId = (int) $detail->id;
                    break;
                }
            }
            if ($targetId === null && $purchase->details->count() === 1) {
                $targetId = (int) $purchase->details->first()->id;
            }
            if ($targetId === null || ! isset($byDetail[$targetId])) {
                continue;
            }
            $byDetail[$targetId]->push($unit);
            $used[(int) $unit->id] = true;
        }

        return $byDetail;
    }

    /**
     * Status yang memang dihasilkan oleh cek barang. Status lain (sold, keep, sewa, dan sejenisnya) tidak ditimpa.
     */
    private function checkResultMayReplaceStockStatus(string $status): bool
    {
        return in_array($status, [
            ProductUnit::STATUS_IN_STOCK,
            ProductUnit::STATUS_NOT_IN_STOCK,
            ProductUnit::STATUS_RETURE,
            ProductUnit::STATUS_PENDING,
        ], true);
    }

    private function serialKey(string $serial): string
    {
        $normalized = strtoupper(trim($serial));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';

        return $normalized;
    }
}
