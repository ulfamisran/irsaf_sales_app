<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function stockWarehouse(Request $request): View
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole([Role::STAFF_GUDANG])) {
            abort(403, __('Unauthorized.'));
        }

        $warehouseId = $request->get('warehouse_id');
        $productId = $request->get('product_id');
        $query = Stock::with(['product.category', 'location'])
            ->where('location_type', Stock::LOCATION_WAREHOUSE);

        if ($warehouseId) {
            $query->where('location_id', $warehouseId);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $stocks = $query->orderBy('product_id')->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::orderBy('sku')->get(['id', 'sku', 'brand']);

        return view('reports.stock-warehouse', compact('stocks', 'warehouses', 'products'));
    }

    public function stockBranch(Request $request): View
    {
        $user = $request->user();
        $branchId = $request->get('branch_id');
        $productId = $request->get('product_id');
        $query = Stock::with(['product.category', 'location'])
            ->where('location_type', Stock::LOCATION_BRANCH);

        if (! $user->isSuperAdmin()) {
            if (! $user->branch_id) {
                abort(403, __('User branch not set.'));
            }
            $branchId = $user->branch_id;
        }

        if ($branchId) {
            $query->where('location_id', $branchId);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $stocks = $query->orderBy('product_id')->paginate(20)->withQueryString();
        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::whereKey($user->branch_id)->get();
        $products = Product::orderBy('sku')->get(['id', 'sku', 'brand']);

        return view('reports.stock-branch', compact('stocks', 'branches', 'products'));
    }
}
