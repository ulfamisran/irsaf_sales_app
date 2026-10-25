<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReceivingLog;
use App\Models\Stock;
use Carbon\Carbon;
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
        $purchase->loadMissing(['details.product.category', 'details.units', 'payments']);

        $invoiceTotal = round((float) $purchase->total, 2);
        $recognized = 0.0;
        $returnValue = 0.0;
        $deferred = 0.0;
        $unchecked = 0.0;
        $hasUnchecked = false;
        $hasChecked = false;

        foreach ($purchase->details as $detail) {
            $unitPrice = round((float) $detail->unit_price, 2);
            $product = $detail->product;
            $requiresSerial = $product?->category?->requires_serial ?? true;

            if ($requiresSerial) {
                $units = $detail->relationLoaded('units')
                    ? $detail->units
                    : ProductUnit::where('purchase_detail_id', $detail->id)->get();

                foreach ($units as $unit) {
                    $hpp = round((float) ($unit->harga_hpp ?: $unitPrice), 2);
                    if ($unit->check_status === ProductUnit::CHECK_UNCHECKED) {
                        $unchecked += $hpp;
                        $hasUnchecked = true;
                    } else {
                        $hasChecked = true;
                        if ($unit->status === ProductUnit::STATUS_IN_STOCK
                            || $unit->status === ProductUnit::STATUS_KEEP
                            || $unit->status === ProductUnit::STATUS_SOLD
                            || $unit->status === ProductUnit::STATUS_IN_RENT
                            || $unit->status === ProductUnit::STATUS_RESERVED) {
                            $recognized += $hpp;
                        } elseif ($unit->status === ProductUnit::STATUS_RETURE) {
                            $returnValue += $hpp;
                        } elseif ($unit->status === ProductUnit::STATUS_PENDING) {
                            $deferred += $hpp;
                        }
                    }
                }

                // Fallback serial text without linked units (legacy / edge)
                if ($units->isEmpty() && ! empty($detail->serial_numbers)) {
                    $serials = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
                    $serials = array_values(array_filter(array_map('trim', $serials)));
                    // Legacy treated as fully baik via migration on units; if no units, use qty columns.
                    $recognized += ((int) $detail->qty_baik) * $unitPrice;
                    $returnValue += ((int) $detail->qty_reture) * $unitPrice;
                    $deferred += ((int) $detail->qty_pending) * $unitPrice;
                    $left = max(0, (int) $detail->quantity - (int) $detail->qty_baik - (int) $detail->qty_reture - (int) $detail->qty_pending);
                    if ($left > 0) {
                        $unchecked += $left * $unitPrice;
                        $hasUnchecked = true;
                    }
                    if ((int) $detail->qty_baik + (int) $detail->qty_reture + (int) $detail->qty_pending > 0) {
                        $hasChecked = true;
                    }
                }
            } else {
                $baik = (int) $detail->qty_baik;
                $reture = (int) $detail->qty_reture;
                $pending = (int) $detail->qty_pending;
                $left = max(0, (int) $detail->quantity - $baik - $reture - $pending);
                $recognized += $baik * $unitPrice;
                $returnValue += $reture * $unitPrice;
                $deferred += $pending * $unitPrice;
                $unchecked += $left * $unitPrice;
                if ($left > 0) {
                    $hasUnchecked = true;
                }
                if ($baik + $reture + $pending > 0) {
                    $hasChecked = true;
                }
            }
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
        ];
    }

    public function purchaseNeedsCheck(Purchase $purchase): bool
    {
        return $this->summarize($purchase)['needs_check'];
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
                if (in_array($unit->status, [
                    ProductUnit::STATUS_SOLD,
                    ProductUnit::STATUS_KEEP,
                    ProductUnit::STATUS_IN_RENT,
                    ProductUnit::STATUS_RESERVED,
                ], true)) {
                    throw new InvalidArgumentException(
                        __('Serial :sn tidak dapat diubah karena status stok saat ini: :status.', [
                            'sn' => $unit->serial_number,
                            'status' => $unit->status,
                        ])
                    );
                }

                $newStatus = match ($newResult) {
                    PurchaseReceivingLog::RESULT_BAIK => ProductUnit::STATUS_IN_STOCK,
                    PurchaseReceivingLog::RESULT_RETURE => ProductUnit::STATUS_RETURE,
                    PurchaseReceivingLog::RESULT_PENDING => ProductUnit::STATUS_PENDING,
                };

                $unit->update([
                    'status' => $newStatus,
                    'check_status' => ProductUnit::CHECK_CHECKED,
                    'checked_at' => $now,
                    'checked_by' => $userId,
                ]);

                $this->stockMutationService->recalculateStockQuantityIfExists(
                    (int) $unit->product_id,
                    (string) $unit->location_type,
                    (int) $unit->location_id
                );
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

        foreach ($purchase->details as $detail) {
            $requiresSerial = $detail->product?->category?->requires_serial ?? true;
            $productLabel = trim(($detail->product->sku ?? '').' '.($detail->product->brand ?? '').' '.($detail->product->series ?? '')) ?: __('Produk');
            $unitPrice = round((float) $detail->unit_price, 2);

            if ($requiresSerial) {
                foreach ($detail->units as $unit) {
                    if ($unit->check_status !== ProductUnit::CHECK_CHECKED) {
                        continue;
                    }
                    if (! in_array($unit->status, [
                        ProductUnit::STATUS_IN_STOCK,
                        ProductUnit::STATUS_KEEP,
                        ProductUnit::STATUS_SOLD,
                        ProductUnit::STATUS_IN_RENT,
                        ProductUnit::STATUS_RESERVED,
                    ], true)) {
                        continue;
                    }
                    $hpp = round((float) ($unit->harga_hpp ?: $unitPrice), 2);
                    $items[] = [
                        'type' => 'serial',
                        'label' => $productLabel,
                        'serial' => (string) $unit->serial_number,
                        'qty' => 1,
                        'value' => $hpp,
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
}
