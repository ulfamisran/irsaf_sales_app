<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Distributor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Stock;
use App\Services\KasBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller to fetch data filtered by location (branch or warehouse).
 * Used by product create, sales, service, rental forms.
 */
class DataByLocationController extends Controller
{
    /**
     * Get distributors by location.
     */
    public function distributors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse,cabang,gudang'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = in_array($validated['location_type'], ['branch', 'cabang']) ? 'branch' : 'warehouse';
        $locationId = (int) $validated['location_id'];

        $query = Distributor::orderBy('name');
        if ($locationType === 'branch') {
            $query->where(function ($q) use ($locationId) {
                $q->where('branch_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        } else {
            $query->where(function ($q) use ($locationId) {
                $q->where('warehouse_id', $locationId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('branch_id')->whereNull('warehouse_id');
                    });
            });
        }

        $distributors = $query->get(['id', 'name']);

        return response()->json([
            'distributors' => $distributors->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        ]);
    }

    /**
     * Get payment methods and customers by location.
     * For sales/service: location_type=branch
     * For rental: location_type=warehouse
     */
    public function formData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_type' => ['required', 'in:branch,warehouse,cabang,gudang'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $locationType = in_array($validated['location_type'], ['branch', 'cabang']) ? 'branch' : 'warehouse';
        $locationId = (int) $validated['location_id'];

        $branchId = $locationType === 'branch' ? $locationId : null;
        $warehouseId = $locationType === 'warehouse' ? $locationId : null;

        $paymentMethods = $this->getPaymentMethodsByLocation($branchId, $warehouseId);
        $customers = $this->getCustomersByLocation($branchId, $warehouseId);
        $inStockProducts = $this->getInStockProductsByLocation($branchId, $warehouseId);

        $saldoPerPm = [];
        if ($branchId) {
            $saldoPerPm = (new KasBalanceService)->getSaldoPerPaymentMethod($branchId);
        } elseif ($warehouseId) {
            $saldoPerPm = (new KasBalanceService)->getSaldoPerPaymentMethodForWarehouse($warehouseId);
        }

        return response()->json([
            'payment_methods' => $paymentMethods,
            'customers' => $customers,
            'saldo_per_pm' => $saldoPerPm,
            'in_stock_products' => $inStockProducts,
        ]);
    }

    private function getPaymentMethodsByLocation(?int $branchId, ?int $warehouseId): array
    {
        $query = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->orderBy('id');

        if ($branchId) {
            $query->forLocation($branchId, null);
        } elseif ($warehouseId) {
            $query->forLocation(null, $warehouseId);
        } else {
            return [];
        }

        return $query->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening'])
            ->map(fn ($pm) => [
                'id' => $pm->id,
                'label' => $pm->display_label,
            ])
            ->values()
            ->all();
    }

    private function getCustomersByLocation(?int $branchId, ?int $warehouseId): array
    {
        $query = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            return [];
        }

        return $query->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
            ])
            ->values()
            ->all();
    }

    private function getInStockProductsByLocation(?int $branchId, ?int $warehouseId): array
    {
        if (! $branchId && ! $warehouseId) {
            return [];
        }

        $locationType = $branchId ? Stock::LOCATION_BRANCH : Stock::LOCATION_WAREHOUSE;
        $locationId = $branchId ?: $warehouseId;

        return Product::query()
            ->join('stocks', function ($join) use ($locationType, $locationId) {
                $join->on('stocks.product_id', '=', 'products.id')
                    ->where('stocks.location_type', $locationType)
                    ->where('stocks.location_id', $locationId)
                    ->where('stocks.quantity', '>', 0);
            })
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->orderBy('categories.name')
            ->orderBy('products.sku')
            ->get([
                'products.id',
                'products.category_id',
                'categories.name as category_name',
                'products.sku',
                'products.brand',
                'products.series',
                'products.purchase_price',
                'products.selling_price',
                'stocks.quantity as stock_qty',
            ])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'category_id' => (int) ($p->category_id ?? 0),
                'category_name' => (string) ($p->category_name ?? '-'),
                'label' => trim(($p->sku ?? '').' - '.($p->brand ?? '').' '.($p->series ?? '')),
                'stock_qty' => (int) ($p->stock_qty ?? 0),
            ])
            ->values()
            ->all();
    }
}
