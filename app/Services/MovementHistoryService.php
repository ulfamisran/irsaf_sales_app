<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DamagedGood;
use App\Models\IncomingGood;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductUnitChangeLog;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReceivingLog;
use App\Models\RentalItem;
use App\Models\SaleDetail;
use App\Models\SaleTradeIn;
use App\Models\ServiceMaterial;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class MovementHistoryService
{
    /** @var array<int, string|null> */
    private array $branches = [];

    /** @var array<int, string|null> */
    private array $warehouses = [];

    /**
     * @return array{found: bool, title: string, subtitle: string, events: list<array<string, mixed>>}
     */
    public function forSerial(string $serial, string $sort = 'transaction'): array
    {
        $serial = trim($serial);
        $unit = ProductUnit::query()->with(['product', 'user', 'purchase.user'])->where('serial_number', $serial)->first();
        $events = [];

        $this->addPurchasesForSerial($events, $serial, $unit);
        $this->addTradeInsForSerial($events, $serial);
        $this->addSalesForSerial($events, $serial);
        $this->addMutationsForSerial($events, $serial);
        $this->addRentalsForSerial($events, $serial);
        $this->addDamageForSerial($events, $serial, $unit);
        $this->addReceivingForUnit($events, $unit);
        $this->addChangeLogs($events, $unit);
        if ($unit && ! $this->hasEntryDocument($events)) {
            $this->addUnitRecorded($events, $unit);
        }

        $product = $unit?->product;
        $title = $serial;
        $subtitle = $product
            ? trim(($product->sku ?? '').' · '.trim(($product->brand ?? '').' '.($product->series ?? '')))
            : __('Nomor serial tidak tersimpan sebagai unit, riwayat diambil dari transaksi yang menyebut serial ini.');

        return [
            'found' => $unit !== null || $events !== [],
            'title' => $title,
            'subtitle' => $subtitle,
            'events' => $this->sorted($events, $sort, true),
        ];
    }

    /**
     * @return array{found: bool, title: string, subtitle: string, events: list<array<string, mixed>>}
     */
    public function forSku(string $sku, string $sort = 'transaction'): array
    {
        $sku = trim($sku);
        $product = Product::query()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
        if (! $product) {
            return [
                'found' => false,
                'title' => $sku,
                'subtitle' => __('SKU tidak ditemukan.'),
                'events' => [],
            ];
        }

        $events = [];
        $this->addPurchasesForProduct($events, (int) $product->id);
        $this->addSalesForProduct($events, (int) $product->id);
        $this->addMutationsForProduct($events, (int) $product->id);
        $this->addIncomingForProduct($events, (int) $product->id);
        $this->addRentalsForProduct($events, (int) $product->id);
        $this->addServiceMaterialsForProduct($events, (int) $product->id);
        $this->addTradeInsForProduct($events, (int) $product->id);
        $this->addDamageForProduct($events, (int) $product->id);

        return [
            'found' => true,
            'title' => (string) $product->sku,
            'subtitle' => trim(($product->brand ?? '').' '.($product->series ?? '')),
            'events' => $this->sorted($events, $sort),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addPurchasesForSerial(array &$events, string $serial, ?ProductUnit $unit): void
    {
        $seen = [];
        if ($unit?->purchase) {
            $this->pushPurchase($events, $unit->purchase, $serial, 1);
            $seen[$unit->purchase->id] = true;
        }

        $like = $this->like($serial);
        $details = PurchaseDetail::query()
            ->with('purchase.user')
            ->where('serial_numbers', 'like', $like)
            ->get();
        foreach ($details as $detail) {
            if (! $detail->purchase || isset($seen[$detail->purchase->id]) || ! $this->containsSerial($detail->serial_numbers, $serial)) {
                continue;
            }
            $this->pushPurchase($events, $detail->purchase, $serial, (int) $detail->quantity);
            $seen[$detail->purchase->id] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addPurchasesForProduct(array &$events, int $productId): void
    {
        $details = PurchaseDetail::query()
            ->with('purchase.user')
            ->where('product_id', $productId)
            ->get();
        foreach ($details as $detail) {
            if (! $detail->purchase) {
                continue;
            }
            $this->pushPurchase($events, $detail->purchase, null, (int) $detail->quantity);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushPurchase(array &$events, Purchase $purchase, ?string $serial, int $qty): void
    {
        $detail = trim((string) ($purchase->jenis_pembelian ?: __('Pembelian')));
        if ($qty > 0) {
            $detail .= ' · '.__('Qty :qty', ['qty' => $qty]);
        }
        if ($serial) {
            $detail .= ' · '.$serial;
        }
        $place = $this->locationName($purchase->warehouse_id ? 'warehouse' : 'branch', (int) ($purchase->warehouse_id ?: $purchase->branch_id));
        if ($place) {
            $detail .= ' · '.$place;
        }

        $this->push($events, $purchase->purchase_date, 10, [
            'type' => (string) ($purchase->jenis_pembelian ?: __('Pembelian')),
            'document' => (string) ($purchase->invoice_number ?: 'PB-'.$purchase->id),
            'actor' => $purchase->user?->name,
            'detail' => $detail,
            'status' => $purchase->isCancelled() ? __('Dibatalkan') : __('Berlaku'),
            'direction' => $purchase->isCancelled() ? 'none' : 'in',
            'location_key' => $this->placeKey($purchase->warehouse_id, $purchase->branch_id),
            'recorded_at' => $purchase->created_at,
            'url' => $this->url('purchases.show', $purchase->id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addSalesForSerial(array &$events, string $serial): void
    {
        $details = SaleDetail::query()
            ->with('sale.user')
            ->where('serial_numbers', 'like', $this->like($serial))
            ->get();
        foreach ($details as $detail) {
            if (! $detail->sale || ! $this->containsSerial($detail->serial_numbers, $serial)) {
                continue;
            }
            $this->pushSale($events, $detail->sale, $serial, (int) $detail->quantity);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addSalesForProduct(array &$events, int $productId): void
    {
        $details = SaleDetail::query()->with('sale.user')->where('product_id', $productId)->get();
        foreach ($details as $detail) {
            if ($detail->sale) {
                $this->pushSale($events, $detail->sale, null, (int) $detail->quantity);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushSale(array &$events, $sale, ?string $serial, int $qty): void
    {
        $detail = __('Qty :qty', ['qty' => max($qty, 1)]);
        if ($serial) {
            $detail .= ' · '.$serial;
        }
        $place = $this->locationName($sale->warehouse_id ? 'warehouse' : 'branch', (int) ($sale->warehouse_id ?: $sale->branch_id));
        if ($place) {
            $detail .= ' · '.$place;
        }
        $cancelled = in_array((string) $sale->status, ['cancel', 'cancelled'], true);
        $this->push($events, $sale->sale_date, 40, [
            'type' => __('Penjualan'),
            'document' => (string) ($sale->invoice_number ?: 'JL-'.$sale->id),
            'actor' => $sale->user?->name,
            'detail' => $detail,
            'status' => $this->documentStatus((string) $sale->status),
            'direction' => $cancelled ? 'none' : 'out',
            'location_key' => $this->placeKey($sale->warehouse_id, $sale->branch_id),
            'recorded_at' => $sale->created_at,
            'url' => $this->url('sales.show', $sale->id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addMutationsForSerial(array &$events, string $serial): void
    {
        $rows = StockMutation::query()
            ->with('user')
            ->where(function ($query) use ($serial) {
                $like = $this->like($serial);
                $query->where('serial_numbers', 'like', $like)
                    ->orWhere('distribution_unit_snapshot', 'like', $like);
            })
            ->get();
        foreach ($rows as $row) {
            $inList = $this->containsSerial((string) $row->serial_numbers, $serial);
            $inSnapshot = $this->containsSerial(json_encode($row->distribution_unit_snapshot), $serial);
            if (! $inList && ! $inSnapshot) {
                continue;
            }
            $this->pushMutation($events, $row, $serial);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addMutationsForProduct(array &$events, int $productId): void
    {
        $rows = StockMutation::query()->with('user')->where('product_id', $productId)->get();
        foreach ($rows as $row) {
            $this->pushMutation($events, $row, null);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushMutation(array &$events, StockMutation $row, ?string $serial): void
    {
        $from = $this->locationName($row->from_location_type, (int) $row->from_location_id);
        $to = $this->locationName($row->to_location_type, (int) $row->to_location_id);
        $detail = trim(($from ?: '-').' → '.($to ?: '-'));
        if ($serial) {
            $detail .= ' · '.$serial;
        } else {
            $detail .= ' · '.__('Qty :qty', ['qty' => (int) $row->quantity]);
        }
        $this->push($events, $row->mutation_date, 30, [
            'type' => __('Distribusi'),
            'document' => (string) ($row->invoice_number ?: 'DS-'.$row->id),
            'actor' => $row->user?->name,
            'detail' => $detail,
            'status' => $row->isCancelled() ? __('Dibatalkan') : __('Berlaku'),
            'direction' => $row->isCancelled() ? 'none' : 'transfer',
            'location_key' => $this->placeKeyFromType($row->from_location_type, (int) $row->from_location_id),
            'to_location_key' => $this->placeKeyFromType($row->to_location_type, (int) $row->to_location_id),
            'recorded_at' => $row->created_at,
            'url' => $row->distribution_id ? $this->url('stock-mutations.invoice', $row->distribution_id) : null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addRentalsForSerial(array &$events, string $serial): void
    {
        $items = RentalItem::query()->with('rental.user')->where('serial_number', $serial)->get();
        foreach ($items as $item) {
            if ($item->rental) {
                $this->pushRental($events, $item->rental, $serial);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addRentalsForProduct(array &$events, int $productId): void
    {
        $items = RentalItem::query()->with('rental.user')->where('product_id', $productId)->get();
        $seen = [];
        foreach ($items as $item) {
            if (! $item->rental || isset($seen[$item->rental_id])) {
                continue;
            }
            $seen[$item->rental_id] = true;
            $this->pushRental($events, $item->rental, null);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushRental(array &$events, $rental, ?string $serial): void
    {
        $detail = $serial ?: __('Penyewaan unit');
        $cancelled = in_array((string) $rental->status, ['cancel', 'cancelled'], true);
        $this->push($events, $rental->pickup_date, 50, [
            'type' => __('Penyewaan'),
            'document' => (string) ($rental->invoice_number ?: 'SW-'.$rental->id),
            'actor' => $rental->user?->name,
            'detail' => $detail,
            'status' => $this->documentStatus((string) $rental->status),
            'direction' => $cancelled ? 'none' : 'out',
            'location_key' => $this->placeKey($rental->warehouse_id, $rental->branch_id),
            'recorded_at' => $rental->created_at,
            'url' => $this->url('rentals.show', $rental->id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addTradeInsForSerial(array &$events, string $serial): void
    {
        $rows = SaleTradeIn::query()->with('sale.user')->where('serial_number', $serial)->get();
        foreach ($rows as $row) {
            if ($row->sale) {
                $this->pushTradeIn($events, $row);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addTradeInsForProduct(array &$events, int $productId): void
    {
        $rows = SaleTradeIn::query()->with('sale.user')->where('product_id', $productId)->get();
        foreach ($rows as $row) {
            if ($row->sale) {
                $this->pushTradeIn($events, $row);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushTradeIn(array &$events, SaleTradeIn $row): void
    {
        $cancelled = in_array((string) ($row->sale?->status ?? ''), ['cancel', 'cancelled'], true);
        $this->push($events, $row->sale?->sale_date, 15, [
            'type' => __('Tukar Tambah'),
            'document' => (string) ($row->sale?->invoice_number ?: 'JL-'.$row->sale_id),
            'actor' => $row->sale?->user?->name,
            'detail' => trim($row->serial_number.' · '.$row->sku),
            'status' => $this->documentStatus((string) ($row->sale?->status ?? '')),
            'direction' => $cancelled ? 'none' : 'in',
            'location_key' => $this->placeKey($row->sale?->warehouse_id, $row->sale?->branch_id),
            'recorded_at' => $row->created_at,
            'url' => $this->url('sales.show', $row->sale_id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addIncomingForProduct(array &$events, int $productId): void
    {
        $rows = IncomingGood::query()->with('user')->where('product_id', $productId)->get();
        foreach ($rows as $row) {
            $place = $this->locationName($row->warehouse_id ? 'warehouse' : 'branch', (int) ($row->warehouse_id ?: $row->branch_id));
            $this->push($events, $row->received_date, 12, [
                'type' => __('Barang Masuk'),
                'document' => 'BM-'.$row->id,
                'actor' => $row->user?->name,
                'detail' => __('Qty :qty', ['qty' => (int) $row->quantity]).($place ? ' · '.$place : ''),
                'status' => __('Berlaku'),
                'direction' => 'in',
                'location_key' => $this->placeKey($row->warehouse_id, $row->branch_id),
                'recorded_at' => $row->created_at,
                'url' => $this->url('incoming-goods.show', $row->id),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addServiceMaterialsForProduct(array &$events, int $productId): void
    {
        $rows = ServiceMaterial::query()->with('service.user')->where('product_id', $productId)->get();
        foreach ($rows as $row) {
            if (! $row->service) {
                continue;
            }
            $this->push($events, $row->service->entry_date, 55, [
                'type' => __('Service'),
                'document' => (string) ($row->service->invoice_number ?: 'SV-'.$row->service_id),
                'actor' => $row->service->user?->name,
                'detail' => trim((string) $row->name).' · '.__('Qty :qty', ['qty' => (int) $row->quantity]),
                'status' => $this->documentStatus((string) $row->service->status),
                'recorded_at' => $row->created_at,
                'url' => $this->url('services.show', $row->service_id),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addDamageForSerial(array &$events, string $serial, ?ProductUnit $unit): void
    {
        $query = DamagedGood::query()->with('user');
        if ($unit) {
            $query->where(function ($inner) use ($unit, $serial) {
                $inner->where('product_unit_id', $unit->id)->orWhere('serial_number', $serial);
            });
        } else {
            $query->where('serial_number', $serial);
        }
        foreach ($query->get() as $row) {
            $this->pushDamage($events, $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addDamageForProduct(array &$events, int $productId): void
    {
        $rows = DamagedGood::query()
            ->with('user')
            ->whereHas('productUnit', fn ($query) => $query->where('product_id', $productId))
            ->get();
        foreach ($rows as $row) {
            $this->pushDamage($events, $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function pushDamage(array &$events, DamagedGood $row): void
    {
        $this->push($events, $row->recorded_date, 60, [
            'type' => __('Barang Rusak'),
            'document' => 'RS-'.$row->id,
            'actor' => $row->user?->name,
            'detail' => trim($row->serial_number.' · '.(string) $row->damage_description),
            'status' => $row->isReactivated() ? __('Diaktifkan kembali') : __('Rusak'),
            'recorded_at' => $row->created_at,
            'url' => $this->url('damaged-goods.show', $row->id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addReceivingForUnit(array &$events, ?ProductUnit $unit): void
    {
        if (! $unit) {
            return;
        }
        $logs = PurchaseReceivingLog::query()->with(['checker', 'purchase'])->where('product_unit_id', $unit->id)->get();
        foreach ($logs as $log) {
            $this->push($events, $log->checked_at, 20, [
                'type' => __('Cek Pembelian'),
                'document' => (string) ($log->purchase?->invoice_number ?: 'PB-'.$log->purchase_id),
                'actor' => $log->checker?->name,
                'detail' => $log->resultLabel(),
                'status' => $log->resultLabel(),
                'recorded_at' => $log->created_at,
                'url' => $this->url('purchase-receiving.show', $log->purchase_id),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addChangeLogs(array &$events, ?ProductUnit $unit): void
    {
        if (! $unit) {
            return;
        }
        $logs = ProductUnitChangeLog::query()->with('user')->where('product_unit_id', $unit->id)->get();
        foreach ($logs as $log) {
            $fields = implode(', ', array_keys((array) $log->changes));
            $this->push($events, $log->changed_at, 70, [
                'type' => __('Perubahan Unit'),
                'document' => 'UB-'.$log->id,
                'actor' => $log->user?->name,
                'detail' => trim($fields.($log->reason ? ' · '.$log->reason : '')),
                'status' => __('Tercatat'),
                'recorded_at' => $log->created_at,
                'url' => null,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function addUnitRecorded(array &$events, ProductUnit $unit): void
    {
        $this->push($events, $unit->received_date ?: $unit->created_at, 5, [
            'type' => __('Pencatatan Unit'),
            'document' => 'UN-'.$unit->id,
            'actor' => $unit->user?->name,
            'detail' => $unit->serial_number,
            'status' => __('Tercatat'),
            'direction' => 'in',
            'location_key' => $this->placeKeyFromType($unit->location_type, (int) $unit->location_id),
            'recorded_at' => $unit->created_at,
            'url' => $this->url('stock-units.show', $unit->id),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, mixed>  $event
     */
    private function push(array &$events, mixed $date, int $rank, array $event): void
    {
        $carbon = $this->carbon($date);
        $recorded = $this->carbon($event['recorded_at'] ?? null);
        $events[] = $event + [
            'date' => $carbon,
            'recorded_at' => $recorded,
            'sort' => ($carbon?->format('Y-m-d H:i:s') ?? '9999-12-31 00:00:00').sprintf('%02d', $rank),
            'recorded_sort' => $recorded?->format('Y-m-d H:i:s') ?? '9999-12-31 00:00:00',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function sorted(array $events, string $sort = 'transaction', bool $balanceLocation = false): array
    {
        usort($events, function (array $a, array $b) use ($sort) {
            $transaction = strcmp((string) $a['sort'], (string) $b['sort']);
            $recorded = strcmp((string) $a['recorded_sort'], (string) $b['recorded_sort']);
            if ($sort === 'created_at') {
                return $recorded !== 0 ? $recorded : $transaction;
            }

            return $transaction !== 0 ? $transaction : $recorded;
        });

        $events = array_values($events);
        if ($balanceLocation && $sort !== 'created_at') {
            return $this->orderByLocation($events);
        }

        return $events;
    }

    /**
     * Pada tanggal yang sama, stok keluar dari lokasi terakhir didahulukan
     * sebelum stok masuk di lokasi lain, supaya masuk dan keluar seimbang.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function orderByLocation(array $events): array
    {
        $groups = [];
        foreach ($events as $event) {
            $day = $event['date'] instanceof Carbon ? $event['date']->format('Y-m-d') : '9999-12-31';
            $groups[$day][] = $event;
        }
        ksort($groups);

        $ordered = [];
        $location = null;
        foreach ($groups as $pending) {
            $guard = 0;
            while ($pending !== [] && $guard < 1000) {
                $guard++;
                $index = $this->pickNextIndex($pending, $location);
                $next = $pending[$index];
                array_splice($pending, $index, 1);
                $ordered[] = $next;
                $location = $this->nextLocation($location, $next);
            }
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $pending
     */
    private function pickNextIndex(array $pending, ?string $location): int
    {
        if ($location !== null) {
            foreach ($pending as $index => $event) {
                if ($this->leavesLocation($event, $location)) {
                    return $index;
                }
            }
        }

        if ($location === null) {
            foreach ($pending as $index => $event) {
                if ($this->isActiveMove($event) && ($event['direction'] ?? '') === 'in') {
                    return $index;
                }
            }
        }

        foreach ($pending as $index => $event) {
            if ($this->isActiveMove($event)) {
                return $index;
            }
        }

        return 0;
    }

    private function leavesLocation(array $event, string $location): bool
    {
        if (! $this->isActiveMove($event) || ($event['location_key'] ?? null) !== $location) {
            return false;
        }

        return in_array($event['direction'] ?? '', ['out', 'transfer'], true);
    }

    private function nextLocation(?string $location, array $event): ?string
    {
        if (! $this->isActiveMove($event)) {
            return $location;
        }

        return match ($event['direction'] ?? 'none') {
            'in' => $event['location_key'] ?? $location,
            'out' => ($event['location_key'] ?? null) === $location ? null : $location,
            'transfer' => ($event['location_key'] ?? null) === $location
                ? ($event['to_location_key'] ?? null)
                : $location,
            default => $location,
        };
    }

    private function isActiveMove(array $event): bool
    {
        if (($event['status'] ?? '') === __('Dibatalkan')) {
            return false;
        }

        return in_array($event['direction'] ?? 'none', ['in', 'out', 'transfer'], true);
    }

    private function placeKey(mixed $warehouseId, mixed $branchId): ?string
    {
        if ((int) $warehouseId > 0) {
            return 'warehouse:'.(int) $warehouseId;
        }
        if ((int) $branchId > 0) {
            return 'branch:'.(int) $branchId;
        }

        return null;
    }

    private function placeKeyFromType(?string $type, int $id): ?string
    {
        if ($type === null || $id < 1) {
            return null;
        }

        return (in_array($type, ['warehouse', 'gudang'], true) ? 'warehouse' : 'branch').':'.$id;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function hasEntryDocument(array $events): bool
    {
        foreach ($events as $event) {
            $type = mb_strtolower((string) ($event['type'] ?? ''));
            if (str_contains($type, 'pembelian') || str_contains($type, 'distribusi unit') || $type === mb_strtolower(__('Tukar Tambah')) || $type === mb_strtolower(__('Barang Masuk'))) {
                return true;
            }
        }

        return false;
    }

    private function containsSerial(?string $raw, string $serial): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }
        $needle = mb_strtoupper(trim($serial));
        $decoded = json_decode($raw, true);
        $parts = is_array($decoded) ? $this->flatten($decoded) : preg_split('/[\r\n,;|]+/', $raw);

        foreach ((array) $parts as $part) {
            if (is_string($part) && mb_strtoupper(trim($part)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function flatten(array $value): array
    {
        $out = [];
        array_walk_recursive($value, function ($item) use (&$out) {
            if (is_string($item)) {
                $out[] = $item;
            }
        });

        return $out;
    }

    private function like(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    private function carbon(mixed $date): ?Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date);
    }

    private function locationName(?string $type, int $id): ?string
    {
        if ($id < 1 || $type === null) {
            return null;
        }
        if (in_array($type, ['warehouse', 'gudang'], true)) {
            $this->warehouses[$id] ??= Warehouse::query()->whereKey($id)->value('name');

            return $this->warehouses[$id] ? __('Gudang').' '.$this->warehouses[$id] : null;
        }
        $this->branches[$id] ??= Branch::query()->whereKey($id)->value('name');

        return $this->branches[$id] ? __('Cabang').' '.$this->branches[$id] : null;
    }

    private function documentStatus(string $status): string
    {
        return match ($status) {
            'cancel', 'cancelled' => __('Dibatalkan'),
            'open' => __('Open'),
            'released', 'completed', 'active' => __('Berlaku'),
            default => $status !== '' ? $status : '-',
        };
    }

    private function url(string $name, mixed $parameter): ?string
    {
        if ($parameter === null || $parameter === '' || ! Route::has($name)) {
            return null;
        }

        return route($name, $parameter);
    }
}
