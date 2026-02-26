<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockInOutController extends Controller
{
    /**
     * Laporan mutasi stok (IN/OUT) per produk dan per lokasi.
     *
     * Sumber data:
     * - IN: Incoming Goods (ke gudang), Distribusi masuk (to_location)
     * - OUT: Penjualan (dari cabang), Distribusi keluar (from_location)
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'location_type' => ['nullable', 'in:'.Stock::LOCATION_BRANCH.','.Stock::LOCATION_WAREHOUSE],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'include_distribution' => ['nullable', 'boolean'],
        ]);

        $includeDistribution = (bool) ($validated['include_distribution'] ?? false);

        // Role-based restriction: admin cabang/kasir hanya boleh lihat cabangnya.
        $effectiveLocationType = $validated['location_type'] ?? null;
        $effectiveLocationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;

        if (! $user->isSuperAdmin() && $user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR])) {
            $effectiveLocationType = Stock::LOCATION_BRANCH;
            $effectiveLocationId = (int) $user->branch_id;
        }

        $productId = isset($validated['product_id']) ? (int) $validated['product_id'] : null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        // --- IN: Incoming goods (warehouse only)
        $incomingByProduct = collect();
        if ($effectiveLocationType !== Stock::LOCATION_BRANCH) {
            $incomingQuery = DB::table('incoming_goods')
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                ->when($effectiveLocationType === Stock::LOCATION_WAREHOUSE && $effectiveLocationId, fn ($q) => $q->where('warehouse_id', $effectiveLocationId))
                ->when($dateFrom, fn ($q) => $q->whereDate('received_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('received_date', '<=', $dateTo))
                ->groupBy('product_id');

            $incomingByProduct = $incomingQuery->pluck('qty', 'product_id');
        }

        // --- OUT: Sales (branch only, from sale_details)
        $salesByProduct = collect();
        if ($effectiveLocationType !== Stock::LOCATION_WAREHOUSE) {
            $salesQuery = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->selectRaw('sale_details.product_id as product_id, SUM(sale_details.quantity) as qty')
                ->when($productId, fn ($q) => $q->where('sale_details.product_id', $productId))
                ->when($effectiveLocationType === Stock::LOCATION_BRANCH && $effectiveLocationId, fn ($q) => $q->where('sales.branch_id', $effectiveLocationId))
                ->when($dateFrom, fn ($q) => $q->whereDate('sales.sale_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('sales.sale_date', '<=', $dateTo))
                ->groupBy('sale_details.product_id');

            $salesByProduct = $salesQuery->pluck('qty', 'product_id');
        }

        // --- Distribusi masuk / keluar dari stock_mutations
        $distInByProduct = collect();
        $distOutByProduct = collect();
        if ($includeDistribution) {
            $distInByProduct = DB::table('stock_mutations')
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                ->when($effectiveLocationType && $effectiveLocationId, function ($q) use ($effectiveLocationType, $effectiveLocationId) {
                    return $q->where('to_location_type', $effectiveLocationType)
                        ->where('to_location_id', $effectiveLocationId);
                })
                ->when($dateFrom, fn ($q) => $q->whereDate('mutation_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('mutation_date', '<=', $dateTo))
                ->groupBy('product_id')
                ->pluck('qty', 'product_id');

            $distOutByProduct = DB::table('stock_mutations')
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                ->when($effectiveLocationType && $effectiveLocationId, function ($q) use ($effectiveLocationType, $effectiveLocationId) {
                    return $q->where('from_location_type', $effectiveLocationType)
                        ->where('from_location_id', $effectiveLocationId);
                })
                ->when($dateFrom, fn ($q) => $q->whereDate('mutation_date', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('mutation_date', '<=', $dateTo))
                ->groupBy('product_id')
                ->pluck('qty', 'product_id');
        }

        // Build row set
        $productIds = collect()
            ->merge($incomingByProduct->keys())
            ->merge($salesByProduct->keys())
            ->merge($distInByProduct->keys())
            ->merge($distOutByProduct->keys())
            ->unique()
            ->filter()
            ->values();

        if ($productId) {
            $productIds = collect([$productId]);
        }

        $products = $productIds->isNotEmpty()
            ? Product::query()->whereIn('id', $productIds)->orderBy('sku')->get(['id', 'sku', 'brand', 'series'])
            : collect();

        $rows = $products->map(function ($p) use ($incomingByProduct, $salesByProduct, $distInByProduct, $distOutByProduct) {
            $incoming = (int) ($incomingByProduct[$p->id] ?? 0);
            $distIn = (int) ($distInByProduct[$p->id] ?? 0);
            $salesOut = (int) ($salesByProduct[$p->id] ?? 0);
            $distOut = (int) ($distOutByProduct[$p->id] ?? 0);
            $totalIn = $incoming + $distIn;
            $totalOut = $salesOut + $distOut;

            return [
                'product' => $p,
                'incoming_in' => $incoming,
                'distribution_in' => $distIn,
                'sales_out' => $salesOut,
                'distribution_out' => $distOut,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net' => $totalIn - $totalOut,
            ];
        })->values();

        $totals = [
            'total_in' => (int) $rows->sum('total_in'),
            'total_out' => (int) $rows->sum('total_out'),
            'net' => (int) $rows->sum('net'),
        ];

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $productOptions = Product::orderBy('sku')->limit(500)->get(['id', 'sku', 'brand', 'series']);

        return view('stock-inout.index', [
            'rows' => $rows,
            'totals' => $totals,
            'branches' => $branches,
            'warehouses' => $warehouses,
            'products' => $productOptions,
            'effectiveLocationType' => $effectiveLocationType,
            'effectiveLocationId' => $effectiveLocationId,
            'includeDistribution' => $includeDistribution,
        ]);
    }
}

