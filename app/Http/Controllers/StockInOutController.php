<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DamagedGood;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockInOutController extends Controller
{
    public function index(Request $request): View
    {
        return view('stock-inout.index', $this->buildReport($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->buildReport($request);
        $html = view('stock-inout.export', $data)->render();

        return ExcelExporter::downloadFromHtml($html, 'mutasi-stok-'.now()->format('Ymd-His').'.xlsx', 'generic');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildReport($request);
        $pdf = Pdf::loadView('stock-inout.export-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('mutasi-stok-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(Request $request): array
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat() && ! $user->hasAnyRole([Role::ADMIN_GUDANG, Role::ADMIN_CABANG, Role::KASIR])) {
            abort(403, __('Unauthorized.'));
        }

        $today = Carbon::today()->toDateString();
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'location_type' => ['nullable', 'in:'.Stock::LOCATION_BRANCH.','.Stock::LOCATION_WAREHOUSE],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'show_all' => ['nullable', 'in:1,on,true'],
        ]);

        $dateFrom = $validated['date_from'] ?? $today;
        $dateTo = $validated['date_to'] ?? $today;
        $showAll = $request->boolean('show_all');

        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $filterLocked = false;
        $locationLabel = null;
        $effectiveLocationType = $validated['location_type'] ?? null;
        $effectiveLocationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

        if (! $canFilterLocation) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR]) && $user->branch_id) {
                $filterLocked = true;
                $effectiveLocationType = Stock::LOCATION_BRANCH;
                $effectiveLocationId = (int) $user->branch_id;
                $locationLabel = __('Cabang').': '.(Branch::find($user->branch_id)?->name ?? '');
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG]) && $user->warehouse_id) {
                $filterLocked = true;
                $effectiveLocationType = Stock::LOCATION_WAREHOUSE;
                $effectiveLocationId = (int) $user->warehouse_id;
                $locationLabel = __('Gudang').': '.(Warehouse::find($user->warehouse_id)?->name ?? '');
            } else {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        $opening = $this->openingStock($effectiveLocationType, $effectiveLocationId, $dateFrom);
        $masuk = $this->stockMasuk($effectiveLocationType, $effectiveLocationId, $dateFrom, $dateTo);
        $diterima = $this->stockDiterima($effectiveLocationType, $effectiveLocationId, $dateFrom, $dateTo);
        $terjual = $this->stockTerjual($effectiveLocationType, $effectiveLocationId, $dateFrom, $dateTo);
        $rusak = $this->stockRusak($effectiveLocationType, $effectiveLocationId, $dateFrom, $dateTo);
        $akhir = $this->inStockTotals($effectiveLocationType, $effectiveLocationId);
        $serialsAkhir = $this->inStockSerials($effectiveLocationType, $effectiveLocationId);

        $productIds = collect()
            ->merge($opening->keys())
            ->merge($masuk['qty']->keys())
            ->merge($diterima['qty']->keys())
            ->merge($terjual['qty']->keys())
            ->merge($rusak['qty']->keys())
            ->merge($akhir->keys())
            ->unique()
            ->filter()
            ->values();

        $products = $productIds->isNotEmpty()
            ? Product::with('category')->whereIn('id', $productIds)->orderBy('sku')->get()
            : collect();

        $categories = Category::withTrashed()->orderBy('name')->get()->keyBy('id');
        $grouped = [];
        $totals = [
            'stok_awal' => 0,
            'stok_masuk' => 0,
            'stok_diterima' => 0,
            'stok_terjual' => 0,
            'stok_rusak' => 0,
            'stok_akhir' => 0,
        ];

        foreach ($products as $product) {
            $pid = (int) $product->id;
            $qtyAwal = (int) ($opening[$pid] ?? 0);
            $qtyMasuk = (int) ($masuk['qty'][$pid] ?? 0);
            $qtyDiterima = (int) ($diterima['qty'][$pid] ?? 0);
            $qtyTerjual = (int) ($terjual['qty'][$pid] ?? 0);
            $qtyRusak = (int) ($rusak['qty'][$pid] ?? 0);
            $qtyAkhir = (int) ($akhir[$pid] ?? 0);
            $qtyKeluar = $qtyTerjual + $qtyRusak;

            if ($qtyAwal === 0 && $qtyMasuk === 0 && $qtyDiterima === 0 && $qtyTerjual === 0 && $qtyRusak === 0 && $qtyAkhir === 0) {
                continue;
            }

            // Kartu ringkasan selalu menghitung seluruh produk relevan
            $totals['stok_awal'] += $qtyAwal;
            $totals['stok_masuk'] += $qtyMasuk;
            $totals['stok_diterima'] += $qtyDiterima;
            $totals['stok_terjual'] += $qtyTerjual;
            $totals['stok_rusak'] += $qtyRusak;
            $totals['stok_akhir'] += $qtyAkhir;

            // Default: hanya produk yang ada stok masuk atau stok keluar di periode
            if (! $showAll && $qtyMasuk === 0 && $qtyKeluar === 0) {
                continue;
            }

            $categoryId = (int) ($product->category_id ?? 0);
            $categoryName = $categoryId > 0
                ? (string) ($categories[$categoryId]->name ?? __('Tanpa Kategori'))
                : (string) __('Tanpa Kategori');
            $key = $categoryId > 0 ? (string) $categoryId : 'none';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'category_name' => $categoryName,
                    'stok_awal' => 0,
                    'stok_masuk' => 0,
                    'stok_diterima' => 0,
                    'stok_terjual' => 0,
                    'stok_rusak' => 0,
                    'stok_akhir' => 0,
                    'products' => [],
                ];
            }

            $requiresSerial = $product->requiresSerialTracking();
            $grouped[$key]['stok_awal'] += $qtyAwal;
            $grouped[$key]['stok_masuk'] += $qtyMasuk;
            $grouped[$key]['stok_diterima'] += $qtyDiterima;
            $grouped[$key]['stok_terjual'] += $qtyTerjual;
            $grouped[$key]['stok_rusak'] += $qtyRusak;
            $grouped[$key]['stok_akhir'] += $qtyAkhir;
            $grouped[$key]['products'][] = [
                'product' => $product,
                'requires_serial' => $requiresSerial,
                'stok_awal' => $qtyAwal,
                'stok_masuk' => $qtyMasuk,
                'stok_diterima' => $qtyDiterima,
                'stok_terjual' => $qtyTerjual,
                'stok_rusak' => $qtyRusak,
                'stok_akhir' => $qtyAkhir,
                'serials_masuk' => $requiresSerial ? ($masuk['serials'][$pid] ?? []) : [],
                'serials_diterima' => $requiresSerial ? ($diterima['serials'][$pid] ?? []) : [],
                'serials_terjual' => $requiresSerial ? ($terjual['serials'][$pid] ?? []) : [],
                'serials_rusak' => $requiresSerial ? ($rusak['serials'][$pid] ?? []) : [],
                'serials_akhir' => $requiresSerial ? ($serialsAkhir[$pid] ?? []) : [],
            ];
        }

        $preferred = ['laptop', 'aksesoris', 'aksesori', 'printer', 'proyektor', 'tablet', 'layar', 'service', 'komponen'];
        $categoryRows = collect($grouped)->sortBy(function ($row) use ($preferred) {
            $name = mb_strtolower((string) $row['category_name']);
            foreach ($preferred as $i => $needle) {
                if (str_contains($name, $needle)) {
                    return sprintf('%02d-%s', $i, $name);
                }
            }

            return '99-'.$name;
        })->values();

        $locationDisplay = $locationLabel;
        if (! $locationDisplay && $effectiveLocationType && $effectiveLocationId) {
            $locationDisplay = $effectiveLocationType === Stock::LOCATION_WAREHOUSE
                ? __('Gudang').': '.(Warehouse::find($effectiveLocationId)?->name ?? '#'.$effectiveLocationId)
                : __('Cabang').': '.(Branch::find($effectiveLocationId)?->name ?? '#'.$effectiveLocationId);
        } elseif (! $locationDisplay) {
            $locationDisplay = __('Semua Lokasi');
        }

        return [
            'categoryRows' => $categoryRows,
            'totals' => $totals,
            'showAll' => $showAll,
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'canFilterLocation' => $canFilterLocation,
            'filterLocked' => $filterLocked,
            'locationLabel' => $locationLabel,
            'effectiveLocationType' => $effectiveLocationType,
            'effectiveLocationId' => $effectiveLocationId,
            'locationDisplay' => $locationDisplay,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
    }

    /**
     * Stok awal = unit in_stock sebelum date_from
     * (masih in_stock sekarang, atau keluar di periode karena terjual/rusak/distribusi keluar).
     *
     * @return Collection<int|string, int>
     */
    private function openingStock(?string $locationType, ?int $locationId, string $dateFrom): Collection
    {
        $qty = collect();

        // Masih in_stock, diterima sebelum periode
        $stillIn = ProductUnit::query()
            ->selectRaw('product_id, COUNT(*) as qty')
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->whereDate('received_date', '<', $dateFrom)
            ->where(function ($q) use ($dateFrom) {
                $q->whereNull('checked_at')
                    ->orWhereDate('checked_at', '<', $dateFrom);
            });
        $this->applyLocation($stillIn, $locationType, $locationId);
        foreach ($stillIn->groupBy('product_id')->pluck('qty', 'product_id') as $pid => $q) {
            $qty[(int) $pid] = (int) ($qty[(int) $pid] ?? 0) + (int) $q;
        }

        // Terjual di/ setelah awal periode, tapi received sebelum periode → termasuk stok awal
        $soldInPeriod = ProductUnit::query()
            ->selectRaw('product_id, COUNT(*) as qty')
            ->where('status', ProductUnit::STATUS_SOLD)
            ->whereDate('received_date', '<', $dateFrom)
            ->whereNotNull('sold_at')
            ->whereDate('sold_at', '>=', $dateFrom);
        $this->applyLocation($soldInPeriod, $locationType, $locationId);
        foreach ($soldInPeriod->groupBy('product_id')->pluck('qty', 'product_id') as $pid => $q) {
            $qty[(int) $pid] = (int) ($qty[(int) $pid] ?? 0) + (int) $q;
        }

        // Rusak di periode, received sebelum periode
        $damagedQuery = DamagedGood::query()
            ->whereDate('recorded_date', '>=', $dateFrom)
            ->whereHas('productUnit', function ($q) use ($locationType, $locationId, $dateFrom) {
                $q->whereDate('received_date', '<', $dateFrom);
                if ($locationType && $locationId) {
                    $q->where('location_type', $locationType)->where('location_id', $locationId);
                }
            });

        foreach ($damagedQuery->with('productUnit:id,product_id')->get() as $dg) {
            $pid = (int) ($dg->productUnit?->product_id ?? 0);
            if ($pid > 0) {
                $qty[$pid] = (int) ($qty[$pid] ?? 0) + 1;
            }
        }

        return $qty;
    }

    /**
     * Stok masuk: unit diterima (received_date) di periode + distribusi masuk.
     *
     * @return array{qty: Collection, serials: array<int, list<array{serial: string, check_key: string, check_label: string}>>}
     */
    private function stockMasuk(?string $locationType, ?int $locationId, string $dateFrom, string $dateTo): array
    {
        $qty = collect();
        $serials = [];

        $unitsQuery = ProductUnit::query()
            ->whereDate('received_date', '>=', $dateFrom)
            ->whereDate('received_date', '<=', $dateTo)
            ->whereNotIn('status', [ProductUnit::STATUS_CANCEL]);
        $this->applyLocation($unitsQuery, $locationType, $locationId);

        foreach ($unitsQuery->get(['product_id', 'serial_number', 'status', 'check_status']) as $unit) {
            $pid = (int) $unit->product_id;
            $qty[$pid] = (int) ($qty[$pid] ?? 0) + 1;
            $sn = trim((string) $unit->serial_number);
            if ($sn !== '') {
                $check = $this->unitCheckMeta($unit->check_status, $unit->status);
                $serials[$pid][] = [
                    'serial' => $sn,
                    'check_key' => $check['key'],
                    'check_label' => $check['label'],
                ];
            }
        }

        $distQuery = StockMutation::query()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', StockMutation::STATUS_CANCELLED);
            })
            ->whereDate('mutation_date', '>=', $dateFrom)
            ->whereDate('mutation_date', '<=', $dateTo)
            ->where(function ($q) {
                $q->whereColumn('from_location_type', '!=', 'to_location_type')
                    ->orWhereColumn('from_location_id', '!=', 'to_location_id');
            });
        if ($locationType && $locationId) {
            $distQuery->where('to_location_type', $locationType)->where('to_location_id', $locationId);
        }

        foreach ($distQuery->get(['product_id', 'quantity', 'serial_numbers']) as $mut) {
            $pid = (int) $mut->product_id;
            $qty[$pid] = (int) ($qty[$pid] ?? 0) + (int) $mut->quantity;
            foreach ($this->parseSerials($mut->serial_numbers) as $sn) {
                $serials[$pid][] = [
                    'serial' => $sn,
                    'check_key' => 'distribusi',
                    'check_label' => __('Distribusi'),
                ];
            }
        }

        return [
            'qty' => $qty,
            'serials' => $this->uniqueSerialEntries($serials),
        ];
    }

    /**
     * Stok diterima: masuk di periode, sudah dicek baik, status in_stock.
     *
     * @return array{qty: Collection, serials: array<int, list<string>>}
     */
    private function stockDiterima(?string $locationType, ?int $locationId, string $dateFrom, string $dateTo): array
    {
        $qty = collect();
        $serials = [];

        $unitsQuery = ProductUnit::query()
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->where('check_status', ProductUnit::CHECK_CHECKED)
            ->whereDate('received_date', '>=', $dateFrom)
            ->whereDate('received_date', '<=', $dateTo);
        $this->applyLocation($unitsQuery, $locationType, $locationId);

        foreach ($unitsQuery->get(['product_id', 'serial_number']) as $unit) {
            $pid = (int) $unit->product_id;
            $qty[$pid] = (int) ($qty[$pid] ?? 0) + 1;
            $sn = trim((string) $unit->serial_number);
            if ($sn !== '') {
                $serials[$pid][] = $sn;
            }
        }

        // Distribusi masuk yang saat ini in_stock (dianggap diterima)
        $distQuery = StockMutation::query()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', StockMutation::STATUS_CANCELLED);
            })
            ->whereDate('mutation_date', '>=', $dateFrom)
            ->whereDate('mutation_date', '<=', $dateTo)
            ->where(function ($q) {
                $q->whereColumn('from_location_type', '!=', 'to_location_type')
                    ->orWhereColumn('from_location_id', '!=', 'to_location_id');
            });
        if ($locationType && $locationId) {
            $distQuery->where('to_location_type', $locationType)->where('to_location_id', $locationId);
        }

        foreach ($distQuery->get(['product_id', 'quantity', 'serial_numbers']) as $mut) {
            $pid = (int) $mut->product_id;
            $sns = $this->parseSerials($mut->serial_numbers);
            if ($sns === []) {
                // Non-serial distribution: count quantity as diterima if product has stock qty model
                $qty[$pid] = (int) ($qty[$pid] ?? 0) + (int) $mut->quantity;
                continue;
            }
            $inStockSerials = ProductUnit::query()
                ->where('product_id', $pid)
                ->where('status', ProductUnit::STATUS_IN_STOCK)
                ->whereIn('serial_number', $sns)
                ->pluck('serial_number')
                ->map(fn ($s) => (string) $s)
                ->all();
            foreach ($inStockSerials as $sn) {
                $qty[$pid] = (int) ($qty[$pid] ?? 0) + 1;
                $serials[$pid][] = $sn;
            }
        }

        return [
            'qty' => $qty,
            'serials' => $this->uniqueSerials($serials),
        ];
    }

    /**
     * @return array{qty: Collection, serials: array<int, list<string>>}
     */
    private function stockTerjual(?string $locationType, ?int $locationId, string $dateFrom, string $dateTo): array
    {
        $qty = collect();
        $serials = [];

        if ($locationType === Stock::LOCATION_WAREHOUSE) {
            return ['qty' => $qty, 'serials' => []];
        }

        $salesQuery = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->select(['sale_details.product_id', 'sale_details.quantity', 'sale_details.serial_numbers'])
            ->where('sales.status', Sale::STATUS_RELEASED)
            ->whereDate('sales.sale_date', '>=', $dateFrom)
            ->whereDate('sales.sale_date', '<=', $dateTo);

        if ($locationType === Stock::LOCATION_BRANCH && $locationId) {
            $salesQuery->where('sales.branch_id', $locationId);
        }

        foreach ($salesQuery->get() as $detail) {
            $pid = (int) $detail->product_id;
            $qty[$pid] = (int) ($qty[$pid] ?? 0) + (int) $detail->quantity;
            foreach ($this->parseSerials($detail->serial_numbers) as $sn) {
                $serials[$pid][] = $sn;
            }
        }

        return [
            'qty' => $qty,
            'serials' => $this->uniqueSerials($serials),
        ];
    }

    /**
     * @return array{qty: Collection, serials: array<int, list<string>>}
     */
    private function stockRusak(?string $locationType, ?int $locationId, string $dateFrom, string $dateTo): array
    {
        $qty = collect();
        $serials = [];

        $damagedQuery = DamagedGood::query()
            ->with(['productUnit:id,product_id,location_type,location_id,serial_number'])
            ->whereDate('recorded_date', '>=', $dateFrom)
            ->whereDate('recorded_date', '<=', $dateTo);

        if ($locationType && $locationId) {
            $damagedQuery->whereHas('productUnit', function ($q) use ($locationType, $locationId) {
                $q->where('location_type', $locationType)->where('location_id', $locationId);
            });
        }

        foreach ($damagedQuery->get() as $dg) {
            $pid = (int) ($dg->productUnit?->product_id ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qty[$pid] = (int) ($qty[$pid] ?? 0) + 1;
            $sn = trim((string) ($dg->serial_number ?: $dg->productUnit?->serial_number));
            if ($sn !== '') {
                $serials[$pid][] = $sn;
            }
        }

        return [
            'qty' => $qty,
            'serials' => $this->uniqueSerials($serials),
        ];
    }

    /**
     * @return Collection<int|string, int>
     */
    private function inStockTotals(?string $locationType, ?int $locationId): Collection
    {
        $query = ProductUnit::query()
            ->selectRaw('product_id, COUNT(*) as qty')
            ->where('status', ProductUnit::STATUS_IN_STOCK);
        $this->applyLocation($query, $locationType, $locationId);

        return $query->groupBy('product_id')->pluck('qty', 'product_id');
    }

    /**
     * @return array<int, list<string>>
     */
    private function inStockSerials(?string $locationType, ?int $locationId): array
    {
        $query = ProductUnit::query()
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '');
        $this->applyLocation($query, $locationType, $locationId);

        $serials = [];
        foreach ($query->get(['product_id', 'serial_number']) as $unit) {
            $serials[(int) $unit->product_id][] = (string) $unit->serial_number;
        }

        return $this->uniqueSerials($serials);
    }

    private function applyLocation($query, ?string $locationType, ?int $locationId): void
    {
        if ($locationType && $locationId) {
            $query->where('location_type', $locationType)->where('location_id', $locationId);
        }
    }

    /**
     * @return array{key: string, label: string}
     */
    private function unitCheckMeta(?string $checkStatus, ?string $unitStatus): array
    {
        if ($checkStatus === ProductUnit::CHECK_UNCHECKED) {
            return ['key' => 'unchecked', 'label' => __('Belum Dicek')];
        }

        return match ($unitStatus) {
            ProductUnit::STATUS_RETURE => ['key' => 'reture', 'label' => __('Reture')],
            ProductUnit::STATUS_PENDING => ['key' => 'pending', 'label' => __('Pending')],
            ProductUnit::STATUS_IN_STOCK => ['key' => 'baik', 'label' => __('Baik')],
            ProductUnit::STATUS_NOT_IN_STOCK => ['key' => 'unchecked', 'label' => __('Belum Dicek')],
            default => ['key' => 'checked', 'label' => __('Sudah Dicek')],
        };
    }

    /**
     * @return list<string>
     */
    private function parseSerials(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,;]+/', (string) $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /**
     * @param  array<int, list<string>>  $serials
     * @return array<int, list<string>>
     */
    private function uniqueSerials(array $serials): array
    {
        $out = [];
        foreach ($serials as $pid => $list) {
            $out[(int) $pid] = array_values(array_unique($list));
        }

        return $out;
    }

    /**
     * @param  array<int, list<array{serial: string, check_key: string, check_label: string}>>  $serials
     * @return array<int, list<array{serial: string, check_key: string, check_label: string}>>
     */
    private function uniqueSerialEntries(array $serials): array
    {
        $out = [];
        foreach ($serials as $pid => $list) {
            $seen = [];
            $unique = [];
            foreach ($list as $entry) {
                $sn = (string) ($entry['serial'] ?? '');
                if ($sn === '' || isset($seen[$sn])) {
                    continue;
                }
                $seen[$sn] = true;
                $unique[] = $entry;
            }
            $out[(int) $pid] = $unique;
        }

        return $out;
    }
}
