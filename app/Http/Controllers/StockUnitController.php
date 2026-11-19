<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleTradeIn;
use App\Models\Stock;
use App\Models\UnitExchange;
use App\Models\UnitExchangeItem;
use App\Models\Warehouse;
use App\Support\CatatRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockUnitController extends Controller
{
    /**
     * Display a listing of product units (serials).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $listBase = ProductUnit::query();
        $countBase = ProductUnit::query();

        $this->applyFilters($request, $user, $listBase, $countBase);

        $productIdsQuery = (clone $listBase)->select('product_id')->distinct();
        $productsPage = Product::query()
            ->with(['category', 'distributor'])
            ->whereIn('id', $productIdsQuery)
            ->orderBy('sku')
            ->paginate(15)
            ->withQueryString();

        $productIds = $productsPage->getCollection()->pluck('id')->all();
        $tradeInProductIds = SaleTradeIn::whereNotNull('product_id')
            ->whereIn('product_id', $productIds ?: [0])
            ->pluck('product_id')
            ->unique()
            ->flip()
            ->all();
        $unitsByProduct = collect();
        $soldInfoBySerial = [];
        $exchangesByUnit = [];
        if (! empty($productIds)) {
            $units = (clone $listBase)
                ->with(['product.category', 'product.distributor', 'warehouse', 'branch', 'user'])
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->orderByDesc('id')
                ->get();
            $unitsByProduct = $units->groupBy('product_id');
            $exchangesByUnit = $this->exchangesByUnit($units, $user);

            $saleDetails = SaleDetail::with(['sale:id,invoice_number,sale_date,status'])
                ->whereIn('product_id', $productIds)
                ->whereNotNull('serial_numbers')
                ->whereHas('sale', fn ($q) => $q->where('status', Sale::STATUS_RELEASED))
                ->get(['id', 'sale_id', 'product_id', 'serial_numbers']);
            foreach ($saleDetails as $detail) {
                $serials = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
                foreach ($serials as $serial) {
                    $serial = trim($serial);
                    if ($serial === '' || isset($soldInfoBySerial[$serial])) {
                        continue;
                    }
                    $sale = $detail->sale;
                    $soldInfoBySerial[$serial] = [
                        'invoice_number' => $sale?->invoice_number,
                        'sale_date' => $sale?->sale_date,
                    ];
                }
            }
        }

        $products = Product::orderBy('sku')->get(['id', 'sku', 'brand']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $filterLocked = false;
        $locationType = null;
        $locationId = null;
        $locationLabel = null;
        if (! $canFilterLocation) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
                $filterLocked = true;
                $branch = Branch::find($user->branch_id);
                $locationType = 'branch';
                $locationId = (int) $user->branch_id;
                $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                $filterLocked = true;
                $warehouse = Warehouse::find($user->warehouse_id);
                $locationType = 'warehouse';
                $locationId = (int) $user->warehouse_id;
                $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $user->warehouse_id);
            }
        }

        $statusOptions = [
            ProductUnit::STATUS_IN_STOCK => __('In Stock'),
            ProductUnit::STATUS_KEEP => __('Reserved'),
            ProductUnit::STATUS_SOLD => __('Sold'),
            ProductUnit::STATUS_IN_RENT => __('In Rent'),
            ProductUnit::STATUS_INACTIVE => __('Inactive'),
            ProductUnit::STATUS_CANCEL => __('Cancel'),
            ProductUnit::STATUS_NOT_IN_STOCK => __('Not in stock'),
        ];

        $inStockCounts = (clone $countBase)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
        $inStockHppSums = (clone $countBase)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->selectRaw('product_id, COALESCE(SUM(harga_hpp), 0) as total_hpp')
            ->groupBy('product_id')
            ->pluck('total_hpp', 'product_id');

        $filteredProductCategoryMap = Product::query()
            ->whereIn('id', (clone $countBase)->select('product_id')->distinct())
            ->pluck('category_id', 'id');
        $categoryNameMap = Category::query()->pluck('name', 'id');
        $inStockCategoryTotals = [];
        foreach ($inStockCounts as $productId => $total) {
            $categoryId = (int) ($filteredProductCategoryMap[$productId] ?? 0);
            $categoryKey = $categoryId > 0 ? (string) $categoryId : 'uncategorized';
            $categoryName = $categoryId > 0
                ? (string) ($categoryNameMap[$categoryId] ?? ('#' . $categoryId))
                : (string) __('Tanpa Kategori');

            if (! isset($inStockCategoryTotals[$categoryKey])) {
                $inStockCategoryTotals[$categoryKey] = [
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'category_name' => $categoryName,
                    'total' => 0,
                    'total_hpp' => 0,
                ];
            }
            $inStockCategoryTotals[$categoryKey]['total'] += (int) $total;
            $inStockCategoryTotals[$categoryKey]['total_hpp'] += (int) round((float) ($inStockHppSums[$productId] ?? 0));
        }
        $inStockCategoryTotals = collect($inStockCategoryTotals)
            ->sortByDesc('total')
            ->values();
        $totalInStockUnits = (int) $inStockCategoryTotals->sum('total');
        $totalInStockHpp = (int) round((clone $countBase)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->sum('harga_hpp'));

        return view('stock-units.index', compact(
            'productsPage',
            'unitsByProduct',
            'products',
            'categories',
            'statusOptions',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationType',
            'locationId',
            'locationLabel',
            'inStockCounts',
            'inStockCategoryTotals',
            'totalInStockUnits',
            'totalInStockHpp',
            'soldInfoBySerial',
            'tradeInProductIds',
            'exchangesByUnit'
        ));
    }

    /**
     * Display unit detail and sale info (if sold).
     */
    public function show(Request $request, ProductUnit $unit): View
    {
        $user = $request->user();
        $unit->load(['product.category', 'product.distributor', 'warehouse', 'branch', 'user', 'changeLogs.user']);

        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
                if ($unit->location_type !== Stock::LOCATION_BRANCH || (int) $unit->location_id !== (int) $user->branch_id) {
                    abort(403, __('Unauthorized.'));
                }
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                if ($unit->location_type !== Stock::LOCATION_WAREHOUSE || (int) $unit->location_id !== (int) $user->warehouse_id) {
                    abort(403, __('Unauthorized.'));
                }
            } else {
                abort(403, __('Unauthorized.'));
            }
        }

        $saleInfo = null;
        $details = SaleDetail::with(['sale.customer', 'sale.branch', 'sale.user'])
            ->where('product_id', $unit->product_id)
            ->whereNotNull('serial_numbers')
            ->whereHas('sale', fn ($q) => $q->where('status', Sale::STATUS_RELEASED))
            ->get(['sale_id', 'serial_numbers']);
        foreach ($details as $detail) {
            $serials = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
            if (in_array($unit->serial_number, array_map('trim', $serials), true)) {
                $saleInfo = $detail->sale;
                break;
            }
        }

        return view('stock-units.show', compact('unit', 'saleInfo'));
    }

    /**
     * Export unit list to Excel-compatible format (HTML table).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $listBase = ProductUnit::query();
        $countBase = ProductUnit::query();

        $this->applyFilters($request, $user, $listBase, $countBase);

        $productIdsQuery = (clone $listBase)->select('product_id')->distinct();
        $products = Product::query()
            ->with(['category', 'distributor'])
            ->whereIn('id', $productIdsQuery)
            ->orderBy('sku')
            ->get(['id', 'sku', 'brand', 'series', 'processor', 'ram', 'storage', 'color', 'specs', 'laptop_type', 'purchase_price', 'category_id', 'distributor_id']);

        $productIds = $products->pluck('id')->all();
        $tradeInProductIds = SaleTradeIn::whereNotNull('product_id')
            ->whereIn('product_id', $productIds ?: [0])
            ->pluck('product_id')
            ->unique()
            ->flip()
            ->all();
        $unitsByProduct = collect();
        $soldInfoBySerial = [];
        if (! empty($productIds)) {
            $units = (clone $listBase)
                ->with(['product.category', 'product.distributor', 'warehouse', 'branch', 'user'])
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->orderByDesc('id')
                ->get();
            $unitsByProduct = $units->groupBy('product_id');

            $saleDetails = SaleDetail::with(['sale:id,invoice_number,sale_date,status'])
                ->whereIn('product_id', $productIds)
                ->whereNotNull('serial_numbers')
                ->whereHas('sale', fn ($q) => $q->where('status', Sale::STATUS_RELEASED))
                ->get(['id', 'sale_id', 'product_id', 'serial_numbers']);
            foreach ($saleDetails as $detail) {
                $serials = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
                foreach ($serials as $serial) {
                    $serial = trim($serial);
                    if ($serial === '' || isset($soldInfoBySerial[$serial])) {
                        continue;
                    }
                    $sale = $detail->sale;
                    $soldInfoBySerial[$serial] = [
                        'invoice_number' => $sale?->invoice_number,
                        'sale_date' => $sale?->sale_date,
                    ];
                }
            }
        }

        $statusOptions = [
            ProductUnit::STATUS_IN_STOCK => __('In Stock'),
            ProductUnit::STATUS_KEEP => __('Reserved'),
            ProductUnit::STATUS_SOLD => __('Sold'),
            ProductUnit::STATUS_IN_RENT => __('In Rent'),
            ProductUnit::STATUS_INACTIVE => __('Inactive'),
            ProductUnit::STATUS_CANCEL => __('Cancel'),
            ProductUnit::STATUS_NOT_IN_STOCK => __('Not in stock'),
        ];

        $inStockCounts = (clone $countBase)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $filename = 'stock-units-' . now()->format('Ymd-His') . '.xlsx';
        $html = view('stock-units.export', compact(
            'products',
            'unitsByProduct',
            'statusOptions',
            'inStockCounts',
            'soldInfoBySerial',
            'tradeInProductIds'
        ))->render();

        return ExcelExporter::downloadFromHtml($html, $filename, 'stock');
    }

    /**
     * Export unit list to PDF (landscape).
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $pdfRowLimit = 1500;

        $listBase = ProductUnit::query();
        $countBase = ProductUnit::query();

        $this->applyFilters($request, $user, $listBase, $countBase);

        $productIdsQuery = (clone $listBase)->select('product_id')->distinct();
        $products = Product::query()
            ->with(['category', 'distributor'])
            ->whereIn('id', $productIdsQuery)
            ->orderBy('sku')
            ->get(['id', 'sku', 'brand', 'series', 'processor', 'ram', 'storage', 'color', 'specs', 'laptop_type', 'purchase_price', 'category_id', 'distributor_id']);

        $productIds = $products->pluck('id')->all();
        $tradeInProductIds = SaleTradeIn::whereNotNull('product_id')
            ->whereIn('product_id', $productIds ?: [0])
            ->pluck('product_id')
            ->unique()
            ->flip()
            ->all();
        $unitsByProduct = collect();
        $soldInfoBySerial = [];
        if (! empty($productIds)) {
            $units = (clone $listBase)
                ->with(['product.category', 'product.distributor', 'warehouse', 'branch', 'user'])
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->orderByDesc('id')
                ->get();
            $unitsByProduct = $units->groupBy('product_id');

            $saleDetails = SaleDetail::with(['sale:id,invoice_number,sale_date,status'])
                ->whereIn('product_id', $productIds)
                ->whereNotNull('serial_numbers')
                ->whereHas('sale', fn ($q) => $q->where('status', Sale::STATUS_RELEASED))
                ->get(['id', 'sale_id', 'product_id', 'serial_numbers']);
            foreach ($saleDetails as $detail) {
                $serials = preg_split('/[\r\n,]+/', (string) $detail->serial_numbers) ?: [];
                foreach ($serials as $serial) {
                    $serial = trim($serial);
                    if ($serial === '' || isset($soldInfoBySerial[$serial])) {
                        continue;
                    }
                    $sale = $detail->sale;
                    $soldInfoBySerial[$serial] = [
                        'invoice_number' => $sale?->invoice_number,
                        'sale_date' => $sale?->sale_date,
                    ];
                }
            }
        }

        $totalUnitsForPdf = (int) $unitsByProduct->flatten(1)->count();
        $isTruncated = $totalUnitsForPdf > $pdfRowLimit;
        if ($isTruncated) {
            $remaining = $pdfRowLimit;
            $unitsByProduct = $unitsByProduct->map(function ($group) use (&$remaining) {
                if ($remaining <= 0) {
                    return collect();
                }
                $slice = $group->take($remaining);
                $remaining -= $slice->count();
                return $slice;
            })->filter(fn ($group) => $group->isNotEmpty());
            $productIdsInPdf = $unitsByProduct->keys()->all();
            $products = $products->whereIn('id', $productIdsInPdf)->values();
        }

        $statusOptions = [
            ProductUnit::STATUS_IN_STOCK => __('In Stock'),
            ProductUnit::STATUS_KEEP => __('Reserved'),
            ProductUnit::STATUS_SOLD => __('Sold'),
            ProductUnit::STATUS_IN_RENT => __('In Rent'),
            ProductUnit::STATUS_INACTIVE => __('Inactive'),
            ProductUnit::STATUS_CANCEL => __('Cancel'),
            ProductUnit::STATUS_NOT_IN_STOCK => __('Not in stock'),
        ];

        $inStockCounts = (clone $countBase)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $locationType = (string) $request->get('location_type', '');
        $locationId = (int) $request->get('location_id', 0);
        $locationLabel = __('Semua');
        if ($locationType === 'branch') {
            $name = $branches->firstWhere('id', $locationId)?->name;
            $locationLabel = $name ? __('Cabang') . ': ' . $name : __('Cabang');
        } elseif ($locationType === 'warehouse') {
            $name = $warehouses->firstWhere('id', $locationId)?->name;
            $locationLabel = $name ? __('Gudang') . ': ' . $name : __('Gudang');
        } elseif ($locationId) {
            $name = $branches->firstWhere('id', $locationId)?->name;
            if ($name) {
                $locationLabel = __('Cabang') . ': ' . $name;
            } else {
                $name = $warehouses->firstWhere('id', $locationId)?->name;
                if ($name) {
                    $locationLabel = __('Gudang') . ': ' . $name;
                }
            }
        }

        $statusFilter = (string) $request->get('status', '');
        $statusLabel = $statusFilter && isset($statusOptions[$statusFilter])
            ? $statusOptions[$statusFilter]
            : __('Semua');

        $categoryId = (int) $request->get('category_id', 0);
        $categoryLabel = __('Semua');
        if ($categoryId > 0) {
            $cat = Category::find($categoryId);
            $categoryLabel = $cat?->name ?? __('Semua');
        }

        $pdf = Pdf::loadView('stock-units.pdf', compact(
            'products',
            'unitsByProduct',
            'statusOptions',
            'inStockCounts',
            'soldInfoBySerial',
            'tradeInProductIds',
            'locationLabel',
            'statusLabel',
            'categoryLabel',
            'isTruncated',
            'pdfRowLimit',
            'totalUnitsForPdf'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('monitoring-stok-' . now()->format('Ymd-His') . '.pdf');
    }

    private function applyFilters(Request $request, $user, $listBase, $countBase): void
    {
        if (! $user->isSuperAdminOrAdminPusat()) {
            if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id) {
                $listBase->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', (int) $user->branch_id);
                $countBase->where('location_type', Stock::LOCATION_BRANCH)
                    ->where('location_id', (int) $user->branch_id);
            } elseif ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
                $listBase->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', (int) $user->warehouse_id);
                $countBase->where('location_type', Stock::LOCATION_WAREHOUSE)
                    ->where('location_id', (int) $user->warehouse_id);
            } elseif (! $user->branch_id && ! $user->warehouse_id) {
                abort(403, __('User branch or warehouse not set.'));
            }
        }

        if ($request->filled('product_id')) {
            $listBase->where('product_id', (int) $request->product_id);
            $countBase->where('product_id', (int) $request->product_id);
        }
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id;
            if ($categoryId > 0) {
                $listBase->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
                $countBase->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
            }
        }
        if ($request->has('status')) {
            if ($request->filled('status')) {
                $listBase->where('status', (string) $request->status);
                $countBase->where('status', (string) $request->status);
            }
        } else {
            // Default daftar unit: tampilkan stok yang masih in stock.
            $listBase->where('status', ProductUnit::STATUS_IN_STOCK);
            $countBase->where('status', ProductUnit::STATUS_IN_STOCK);
        }
        if ($user->isSuperAdminOrAdminPusat()) {
            if ($request->filled('location_type')) {
                $listBase->where('location_type', (string) $request->location_type);
                $countBase->where('location_type', (string) $request->location_type);
            }
            if ($request->filled('location_id')) {
                $listBase->where('location_id', (int) $request->location_id);
                $countBase->where('location_id', (int) $request->location_id);
            }
        }
        if ($request->filled('search')) {
            $search = (string) $request->search;
            $listBase->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($p) use ($search) {
                        $p->where('sku', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('series', 'like', "%{$search}%");
                    });
            });
            $countBase->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($p) use ($search) {
                        $p->where('sku', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('series', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $listBase->whereDate('received_date', '>=', $request->date_from);
            $countBase->whereDate('received_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $listBase->whereDate('received_date', '<=', $request->date_to);
            $countBase->whereDate('received_date', '<=', $request->date_to);
        }
        CatatRange::apply($listBase, $request, 'product_units.created_at');
        CatatRange::apply($countBase, $request, 'product_units.created_at');
        CatatRange::applyBackdate($listBase, $request, 'product_units.received_date', 'product_units.created_at');
        CatatRange::applyBackdate($countBase, $request, 'product_units.received_date', 'product_units.created_at');

        if ($request->filled('jenis_barang')) {
            $jenis = (string) $request->jenis_barang;
            if (in_array($jenis, ['baru', 'bekas'], true)) {
                $listBase->whereHas('product', fn ($q) => $q->where('laptop_type', $jenis));
                $countBase->whereHas('product', fn ($q) => $q->where('laptop_type', $jenis));
            }
        }
    }

    /**
     * @param  Collection<int, ProductUnit>  $units
     * @return array<int, array{user: string, created_at: mixed, exchange_date: mixed, number: string, count: int, details: list<array<string, mixed>>}>
     */
    private function exchangesByUnit(Collection $units, $user): array
    {
        if ($units->isEmpty() || ! Schema::hasTable('unit_exchange_items')) {
            return [];
        }

        $idSet = array_fill_keys($units->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $items = UnitExchangeItem::query()
            ->with(['exchange.user:id,name', 'exchange.sale:id,invoice_number'])
            ->whereHas('exchange', fn ($query) => $query->where('status', UnitExchange::STATUS_RELEASED))
            ->where(function ($query) use ($idSet) {
                $ids = array_keys($idSet);
                $query->whereIn('old_unit_id', $ids)->orWhereIn('new_unit_id', $ids);
            })
            ->get();

        $canOpen = (bool) $user?->canModule('sales') && Route::has('sales.unit-exchanges.show');
        $grouped = [];
        foreach ($items as $item) {
            $exchange = $item->exchange;
            if (! $exchange) {
                continue;
            }
            $oldId = (int) $item->old_unit_id;
            $newId = (int) $item->new_unit_id;
            foreach ([$oldId, $newId] as $unitId) {
                if ($unitId <= 0 || ! isset($idSet[$unitId])) {
                    continue;
                }
                $role = $oldId === $unitId && $newId === $unitId
                    ? __('Unit lama dan pengganti')
                    : ($oldId === $unitId ? __('Unit dikembalikan') : __('Unit pengganti'));
                $grouped[$unitId][] = [
                    'exchange_id' => (int) $exchange->id,
                    'item_id' => (int) $item->id,
                    'number' => (string) $exchange->exchange_number,
                    'user' => (string) ($exchange->user?->name ?? '-'),
                    'created_at' => $exchange->created_at,
                    'exchange_date' => $exchange->exchange_date,
                    'recorded_label' => $exchange->created_at?->timezone(CatatRange::TZ)->format('d/m/Y H:i') ?? '-',
                    'exchange_label' => $exchange->exchange_date?->timezone(CatatRange::TZ)->format('d/m/Y') ?? '-',
                    'invoice' => (string) ($exchange->sale?->invoice_number ?? '-'),
                    'old_serial' => (string) $item->old_serial,
                    'new_serial' => (string) $item->new_serial,
                    'role' => $role,
                    'difference' => (float) $item->price_difference,
                    'url' => $canOpen ? route('sales.unit-exchanges.show', $exchange) : null,
                ];
            }
        }

        $result = [];
        foreach ($grouped as $unitId => $rows) {
            usort($rows, fn (array $a, array $b) => $b['exchange_id'] <=> $a['exchange_id'] ?: $b['item_id'] <=> $a['item_id']);
            $latest = $rows[0];
            $result[$unitId] = [
                'user' => $latest['user'],
                'created_at' => $latest['created_at'],
                'exchange_date' => $latest['exchange_date'],
                'number' => $latest['number'],
                'count' => count($rows),
                'details' => array_map(function (array $row) {
                    unset($row['created_at'], $row['exchange_date'], $row['exchange_id'], $row['item_id']);

                    return $row;
                }, $rows),
            ];
        }

        return $result;
    }
}

