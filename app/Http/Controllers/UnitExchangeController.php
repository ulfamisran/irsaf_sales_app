<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Sale;
use App\Models\UnitExchange;
use App\Services\TransactionProofService;
use App\Services\UnitExchangeService;
use App\Support\CatatRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class UnitExchangeController extends Controller
{
    use Concerns\RedirectsFailedRelease;

    public function __construct(
        private UnitExchangeService $exchanges
    ) {}

    public function index(Request $request): View
    {
        $query = UnitExchange::query()
            ->with(['sale.customer', 'user', 'items'])
            ->orderByDesc('id');
        $this->scopeLocation($query, $request);
        if ($request->filled('search')) {
            $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('exchange_number', 'like', $term)
                    ->orWhereHas('sale', fn ($sale) => $sale->where('invoice_number', 'like', $term));
            });
        }

        $exchanges = $query->paginate(20)->withQueryString();

        return view('unit-exchanges.index', compact('exchanges'));
    }

    public function create(Request $request): View
    {
        $today = $this->exchanges->today();
        $number = $this->exchanges->peekNumber();
        $initial = null;
        $saleId = (int) ($request->old('sale_id') ?: $request->integer('sale'));
        if ($saleId > 0) {
            $sale = Sale::query()->with(['saleDetails.product', 'customer', 'user:id,name'])->find($saleId);
            if ($sale && $sale->status === Sale::STATUS_RELEASED) {
                $this->authorizeSale($sale);
                $initial = $this->exchanges->invoicePayload($sale);
            }
        }

        $presetItems = [];
        $oldItems = old('items');
        if (is_array($oldItems) && count($oldItems) > 0) {
            $presetItems = collect($oldItems)->map(function ($item) {
                return [
                    'old_serial' => (string) ($item['old_serial'] ?? ''),
                    'new_serial' => (string) ($item['new_serial'] ?? ''),
                    'price_difference' => (float) ($item['price_difference'] ?? 0),
                    'payment_method_id' => $item['payment_method_id'] ?? null,
                    'label' => trim((string) (($item['new_serial'] ?? '').'')),
                ];
            })->filter(fn ($item) => $item['old_serial'] !== '' && $item['new_serial'] !== '')->values()->all();
        }

        return view('unit-exchanges.create', [
            'today' => $today,
            'number' => $number,
            'recordedAt' => now(CatatRange::TZ)->format('d/m/Y H:i'),
            'userName' => (string) $request->user()?->name,
            'initial' => $initial,
            'exchange' => null,
            'presetItems' => $presetItems,
            'lockInvoice' => false,
        ]);
    }

    public function edit(Request $request, UnitExchange $exchange): View
    {
        if ($exchange->status !== UnitExchange::STATUS_OPEN) {
            abort(403, __('Tukar unit yang sudah Release tidak dapat diubah.'));
        }
        $exchange->load(['sale.saleDetails.product', 'sale.customer', 'sale.user:id,name', 'items.product', 'user', 'proofs']);
        $this->authorizeSale($exchange->sale);

        return view('unit-exchanges.create', [
            'today' => $this->exchanges->today(),
            'number' => $exchange->exchange_number,
            'recordedAt' => $exchange->created_at?->timezone(CatatRange::TZ)->format('d/m/Y H:i') ?? now(CatatRange::TZ)->format('d/m/Y H:i'),
            'userName' => (string) ($exchange->user?->name ?: $request->user()?->name),
            'initial' => $this->exchanges->invoicePayload($exchange->sale),
            'exchange' => $exchange,
            'presetItems' => $exchange->items->map(fn ($item) => [
                'old_serial' => $item->old_serial,
                'new_serial' => $item->new_serial,
                'price_difference' => (float) $item->price_difference,
                'payment_method_id' => $item->payment_method_id,
                'label' => trim((string) ($item->product?->sku.' · '.$item->new_serial)),
            ])->values(),
            'lockInvoice' => true,
        ]);
    }

    public function searchInvoices(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $query = Sale::query()
            ->with('customer:id,name')
            ->where('status', Sale::STATUS_RELEASED)
            ->orderByDesc('id')
            ->limit(15);
        $this->scopeSale($query, $request);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like));
            });
        }

        return response()->json([
            'invoices' => $query->get()->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer?->name ?? '-',
                'sale_date' => $sale->sale_date?->format('d/m/Y') ?? '-',
                'total' => (float) $sale->total,
            ])->values(),
        ]);
    }

    public function invoice(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeSale($sale);
        if ($sale->status !== Sale::STATUS_RELEASED) {
            abort(404);
        }

        return response()->json($this->exchanges->invoicePayload($sale));
    }

    public function replacementProducts(Request $request): JsonResponse
    {
        $sale = Sale::query()->findOrFail($request->integer('sale_id'));
        $this->authorizeSale($sale);

        $productIds = ProductUnit::query()
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->where('location_type', $sale->stockLocationType())
            ->where('location_id', $sale->stockLocationId())
            ->where('serial_number', '!=', '')
            ->distinct()
            ->pluck('product_id');

        $products = \App\Models\Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku')
            ->limit(500)
            ->get(['id', 'sku', 'brand', 'series', 'color', 'category_id', 'selling_price']);

        return response()->json([
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'sku' => (string) ($product->sku ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'series' => (string) ($product->series ?? ''),
                'color' => (string) ($product->color ?? ''),
                'category_id' => $product->category_id,
                'category_name' => (string) ($product->category?->name ?? ''),
                'price' => (float) $product->selling_price,
            ])->values(),
        ]);
    }

    public function searchSerials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);
        $sale = Sale::query()->findOrFail($validated['sale_id']);
        $this->authorizeSale($sale);
        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '' && mb_strlen($term) < 2 && empty($validated['product_id'])) {
            return response()->json(['units' => []]);
        }
        if ($term === '' && empty($validated['product_id'])) {
            return response()->json(['units' => []]);
        }

        $units = ProductUnit::query()
            ->with(['product:id,sku,brand,series,color,category_id', 'product.category:id,name'])
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->where('location_type', $sale->stockLocationType())
            ->where('location_id', $sale->stockLocationId())
            ->when(! empty($validated['product_id']), fn ($query) => $query->where('product_id', (int) $validated['product_id']))
            ->when($term !== '', function ($query) use ($term, $validated) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                if (! empty($validated['product_id'])) {
                    $query->where('serial_number', 'like', $like);

                    return;
                }
                $query->where(function ($inner) use ($like) {
                    $inner->where('serial_number', 'like', $like)
                        ->orWhereHas('product', function ($product) use ($like) {
                            $product->where('sku', 'like', $like)
                                ->orWhere('brand', 'like', $like)
                                ->orWhere('series', 'like', $like);
                        });
                });
            })
            ->orderBy('serial_number')
            ->limit(30)
            ->get();

        return response()->json([
            'units' => $units->map(function (ProductUnit $unit) {
                $name = trim((string) ($unit->product?->brand.' '.($unit->product?->series ?? '')));

                return [
                    'serial' => $unit->serial_number,
                    'product_id' => $unit->product_id,
                    'sku' => (string) ($unit->product?->sku ?? ''),
                    'brand' => (string) ($unit->product?->brand ?? ''),
                    'series' => (string) ($unit->product?->series ?? ''),
                    'category_id' => $unit->product?->category_id,
                    'product' => $name !== '' ? $name : (string) ($unit->product?->sku ?? ''),
                    'price' => round((float) $unit->harga_jual, 2),
                    'hpp' => round((float) $unit->harga_hpp, 2),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, TransactionProofService $proofs): RedirectResponse
    {
        return $this->persist($request, $proofs, null);
    }

    public function update(Request $request, UnitExchange $exchange, TransactionProofService $proofs): RedirectResponse
    {
        if ($exchange->status !== UnitExchange::STATUS_OPEN) {
            abort(403, __('Tukar unit yang sudah Release tidak dapat diubah.'));
        }

        return $this->persist($request, $proofs, $exchange);
    }

    public function show(Request $request, UnitExchange $exchange): View
    {
        $exchange->load(['sale.customer', 'sale.user:id,name', 'user', 'branch', 'warehouse', 'items.product', 'items.paymentMethod', 'proofs']);
        $this->authorizeSale($exchange->sale);

        return view('unit-exchanges.show', compact('exchange'));
    }

    public function report(UnitExchange $exchange): View
    {
        $exchange->load([
            'sale' => fn ($query) => $query->with(\App\Models\Sale::invoiceEagerLoads()),
            'user',
            'branch',
            'warehouse',
            'proofs',
        ]);
        $this->authorizeSale($exchange->sale);

        return view('unit-exchanges.report', compact('exchange'));
    }

    private function persist(Request $request, TransactionProofService $proofs, ?UnitExchange $existing): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'status' => ['required', 'in:open,released'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.old_serial' => ['required', 'string', 'max:100'],
            'items.*.new_serial' => ['required', 'string', 'max:100'],
            'items.*.price_difference' => ['required', 'numeric'],
            'items.*.payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ], [
            'items.required' => __('Pilih minimal 1 unit untuk ditukar.'),
            'items.*.new_serial.required' => __('Nomor serial pengganti wajib diisi.'),
            'items.*.price_difference.required' => __('Selisih harga wajib diisi. Isi 0 jika tidak ada selisih.'),
        ]);

        $sale = Sale::query()->findOrFail($validated['sale_id']);
        $this->authorizeSale($sale);
        if ($existing && (int) $existing->sale_id !== (int) $sale->id) {
            abort(403, __('Invoice tukar unit tidak dapat diganti.'));
        }

        $authorization = app(\App\Services\AuthorizationService::class);
        $releasing = $validated['status'] === UnitExchange::STATUS_RELEASED;
        $moduleOn = $authorization->enabled('unit-exchanges');
        $alreadyApproved = $existing && $authorization->isApproved($existing);
        if ($moduleOn && ! $alreadyApproved) {
            $releasing = false;
        }
        if ($releasing && $existing && $existing->requires_authorization) {
            $authorization->assertCanRelease($existing);
            app(\App\Services\StockMutationService::class)->unreserveExchangeUnits($existing);
        }
        if ($releasing) {
            $proofs->validateUploads($request, $existing, true);
        } elseif ($request->has('proof_files')) {
            $proofs->validateUploads($request, $existing, false);
        }

        try {
            $exchange = $this->exchanges->exchange(
                $sale,
                $validated['items'],
                (int) $request->user()->id,
                UnitExchange::STATUS_OPEN,
                $existing
            );
            if ($request->has('proof_files')) {
                $proofs->storeUploaded($request, $exchange, $request->user()?->id);
            }
            if (! $releasing && ($moduleOn || ($existing && $existing->requires_authorization))) {
                $authorization->attach($exchange);
                app(\App\Services\StockMutationService::class)->reserveExchangeUnits($exchange->fresh('items.product'));
            }
            if ($releasing) {
                $proofs->assertHasProof($exchange);
                $exchange = $this->exchanges->exchange(
                    $sale,
                    $validated['items'],
                    (int) $request->user()->id,
                    UnitExchange::STATUS_RELEASED,
                    $exchange
                );
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            if (isset($exchange)) {
                return redirect()
                    ->route('sales.unit-exchanges.edit', $exchange)
                    ->withErrors($e->errors())
                    ->withInput();
            }

            throw $e;
        }

        return redirect()
            ->route('sales.unit-exchanges.show', $exchange)
            ->with('success', $releasing
                ? __('Tukar unit :number berhasil di-release.', ['number' => $exchange->exchange_number])
                : __('Tukar unit :number disimpan sebagai Open.', ['number' => $exchange->exchange_number]));
    }

    public function release(Request $request, UnitExchange $exchange, TransactionProofService $proofs): RedirectResponse
    {
        $exchange->loadMissing(['sale', 'items']);
        $this->authorizeSale($exchange->sale);
        if ($exchange->sale->status !== Sale::STATUS_RELEASED) {
            return $this->redirectReleaseFailure($request, $exchange, __('Penjualan terkait harus sudah release.'));
        }

        try {
            app(\App\Services\AuthorizationService::class)->assertCanRelease($exchange);
            $proofs->assertHasProof($exchange);
            app(\App\Services\StockMutationService::class)->unreserveExchangeUnits($exchange);
            $items = $exchange->items->map(fn ($item) => [
                'old_serial' => $item->old_serial,
                'new_serial' => $item->new_serial,
                'price_difference' => $item->price_difference,
                'payment_method_id' => $item->payment_method_id,
            ])->all();
            $this->exchanges->exchange(
                $exchange->sale,
                $items,
                (int) $request->user()->id,
                UnitExchange::STATUS_RELEASED,
                $exchange
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectReleaseFailure($request, $exchange, $e->getMessage());
        }

        return redirect()->route('sales.unit-exchanges.show', $exchange)->with('success', __('Tukar unit berhasil di-release.'));
    }

    private function authorizeSale(Sale $sale): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, __('Unauthorized.'));
        }
        if ($user->isSuperAdminOrAdminPusat()) {
            return;
        }
        if ($user->hasAnyRole([Role::ADMIN_CABANG, Role::KASIR, Role::KEPALA_CABANG]) && $user->branch_id && (int) $sale->branch_id === (int) $user->branch_id && ! $sale->isWarehouseSale()) {
            return;
        }
        if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id && $sale->isWarehouseSale() && (int) $sale->warehouse_id === (int) $user->warehouse_id) {
            return;
        }
        abort(403, __('Unauthorized.'));
    }

    private function scopeLocation($query, Request $request): void
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdminOrAdminPusat()) {
            return;
        }
        if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);

            return;
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id)->whereNull('warehouse_id');
        }
    }

    private function scopeSale($query, Request $request): void
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdminOrAdminPusat()) {
            return;
        }
        if ($user->hasAnyRole([Role::ADMIN_GUDANG, Role::KEPALA_GUDANG]) && $user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);

            return;
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id)->whereNull('warehouse_id');
        }
    }
}
