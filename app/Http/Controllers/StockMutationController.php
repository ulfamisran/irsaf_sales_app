<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMutationRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Distribution;
use App\Models\DistributionDetail;
use App\Models\DistributionPayment;
use App\Models\IncomeCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\User;
use App\Support\UserLocationScope;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Services\StockMutationService;
use App\Services\TransactionProofService;
use App\Support\DocumentStatusPresenter;
use App\Support\ExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMutationController extends Controller
{
    use Concerns\RedirectsFailedRelease;

    public function __construct(
        protected StockMutationService $stockMutationService,
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $activeTab = $request->get('tab', 'invoices');

        $filterLocked = false;
        $locationType = null;
        $locationId = null;
        $locationLabel = null;

        $accessConstraint = null;
        if ($user->hasUnrestrictedLocationAccess()) {
            // no default constraint
        } elseif ($user->isAdminPusat()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            if ($branchIds === [] && $warehouseIds === []) {
                abort(403, __('Admin Pusat belum didaftarkan ke cabang/gudang mana pun.'));
            }
            $accessConstraint = function ($q) use ($branchIds, $warehouseIds) {
                $q->where(function ($q2) use ($branchIds, $warehouseIds) {
                    if ($branchIds !== []) {
                        $q2->orWhere(function ($q3) use ($branchIds) {
                            $q3->where(function ($q4) use ($branchIds) {
                                $q4->where('from_location_type', Stock::LOCATION_BRANCH)->whereIn('from_location_id', $branchIds);
                            })->orWhere(function ($q4) use ($branchIds) {
                                $q4->where('to_location_type', Stock::LOCATION_BRANCH)->whereIn('to_location_id', $branchIds);
                            });
                        });
                    }
                    if ($warehouseIds !== []) {
                        $q2->orWhere(function ($q3) use ($warehouseIds) {
                            $q3->where(function ($q4) use ($warehouseIds) {
                                $q4->where('from_location_type', Stock::LOCATION_WAREHOUSE)->whereIn('from_location_id', $warehouseIds);
                            })->orWhere(function ($q4) use ($warehouseIds) {
                                $q4->where('to_location_type', Stock::LOCATION_WAREHOUSE)->whereIn('to_location_id', $warehouseIds);
                            });
                        });
                    }
                });
            };
            $filterLocked = (count($branchIds) + count($warehouseIds)) <= 1;
        } elseif ($user->warehouse_id) {
            $warehouseId = (int) $user->warehouse_id;
            $accessConstraint = function ($q) use ($warehouseId) {
                $q->where(function ($q2) use ($warehouseId) {
                    $q2->where(function ($q3) use ($warehouseId) {
                        $q3->where('from_location_type', Stock::LOCATION_WAREHOUSE)->where('from_location_id', $warehouseId);
                    })->orWhere(function ($q3) use ($warehouseId) {
                        $q3->where('to_location_type', Stock::LOCATION_WAREHOUSE)->where('to_location_id', $warehouseId);
                    });
                });
            };
            $filterLocked = true;
            $warehouse = Warehouse::find($warehouseId);
            $locationType = 'warehouse';
            $locationId = $warehouseId;
            $locationLabel = __('Gudang') . ': ' . ($warehouse?->name ?? '#' . $warehouseId);
        } elseif ($user->branch_id) {
            $branchId = (int) $user->branch_id;
            $accessConstraint = function ($q) use ($branchId) {
                $q->where(function ($q2) use ($branchId) {
                    $q2->where(function ($q3) use ($branchId) {
                        $q3->where('from_location_type', Stock::LOCATION_BRANCH)->where('from_location_id', $branchId);
                    })->orWhere(function ($q3) use ($branchId) {
                        $q3->where('to_location_type', Stock::LOCATION_BRANCH)->where('to_location_id', $branchId);
                    });
                });
            };
            $filterLocked = true;
            $branch = Branch::find($branchId);
            $locationType = 'branch';
            $locationId = $branchId;
            $locationLabel = __('Cabang') . ': ' . ($branch?->name ?? '#' . $branchId);
        } else {
            abort(403, __('User branch or warehouse not set.'));
        }

        $applyFilters = function ($query, bool $isDistribution = true) use ($user, $request, $accessConstraint) {
            if ($accessConstraint) {
                $accessConstraint($query);
            }
            if ($user->isSuperAdminOrAdminPusat() && $request->filled('location_type') && $request->filled('location_id')) {
                $locType = (string) $request->location_type;
                $locId = (int) $request->location_id;
                if (in_array($locType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH]) && $locId > 0) {
                    $query->where(function ($q) use ($locType, $locId) {
                        $q->where(function ($q2) use ($locType, $locId) {
                            $q2->where('from_location_type', $locType)->where('from_location_id', $locId);
                        })->orWhere(function ($q2) use ($locType, $locId) {
                            $q2->where('to_location_type', $locType)->where('to_location_id', $locId);
                        });
                    });
                }
            }
            if ($request->filled('product_id')) {
                if ($isDistribution) {
                    $query->whereHas('details', fn ($q) => $q->where('product_id', $request->product_id));
                } else {
                    $query->where('product_id', $request->product_id);
                }
            }
            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                if ($isDistribution) {
                    $query->where(function ($q) use ($term) {
                        $q->where('invoice_number', 'like', $term)
                            ->orWhereHas('details.product', function ($q2) use ($term) {
                                $q2->where('sku', 'like', $term)
                                    ->orWhere('brand', 'like', $term)
                                    ->orWhere('series', 'like', $term);
                            })
                            ->orWhereHas('details', fn ($q2) => $q2->where('serial_numbers', 'like', $term));
                    });
                } else {
                    $query->where(function ($q) use ($term) {
                        $q->whereHas('product', function ($q2) use ($term) {
                            $q2->where('sku', 'like', $term)
                                ->orWhere('brand', 'like', $term)
                                ->orWhere('series', 'like', $term);
                        })->orWhere('serial_numbers', 'like', $term);
                    });
                }
            }
            if ($request->filled('status') && $isDistribution) {
                $status = (string) $request->status;
                if (in_array($status, [Distribution::STATUS_OPEN, Distribution::STATUS_ACTIVE, Distribution::STATUS_CANCELLED], true)) {
                    $query->where('status', $status);
                } elseif ($status === 'paid_off') {
                    $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                        ->where(function ($q) {
                            $q->where('total', '<=', 0)
                                ->orWhereRaw('total_paid >= (total - 0.02)');
                        });
                } elseif ($status === 'unpaid') {
                    $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                        ->where('total', '>', 0)
                        ->whereRaw('total_paid < (total - 0.02)');
                }
            }
            $dateField = $isDistribution ? 'distribution_date' : 'mutation_date';
            if ($request->filled('date_from')) {
                $query->whereDate($dateField, '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate($dateField, '<=', $request->date_to);
            }
        };

        // === INVOICE TAB (distributions) ===
        $invoiceQuery = Distribution::with(['details.product', 'payments.paymentMethod', 'user', 'cancelUser'])
            ->orderByDesc('distribution_date')
            ->orderByDesc('id');
        $applyFilters($invoiceQuery, true);
        $distributions = $invoiceQuery->paginate(20, ['*'], 'inv_page')->withQueryString();

        // === RIWAYAT TAB (per-product from distribution_details) ===
        $riwayatQuery = DistributionDetail::with(['distribution.user', 'distribution.cancelUser', 'product'])
            ->whereHas('distribution')
            ->orderByDesc(
                Distribution::select('distribution_date')
                    ->whereColumn('distributions.id', 'distribution_details.distribution_id')
                    ->limit(1)
            )
            ->orderByDesc('id');

        if ($accessConstraint || $request->filled('location_type') || $request->filled('date_from') || $request->filled('date_to')) {
            $riwayatQuery->whereHas('distribution', function ($q) use ($applyFilters) {
                $applyFilters($q, true);
            });
        }
        if ($request->filled('product_id')) {
            $riwayatQuery->where('product_id', $request->product_id);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $riwayatQuery->where(function ($q) use ($term) {
                $q->where('serial_numbers', 'like', $term)
                    ->orWhereHas('product', function ($q2) use ($term) {
                        $q2->where('sku', 'like', $term)
                            ->orWhere('brand', 'like', $term)
                            ->orWhere('series', 'like', $term);
                    });
            });
        }
        $riwayatDetails = $riwayatQuery->paginate(20, ['*'], 'page')->withQueryString();

        // === Location name lookups ===
        $allDistributions = $distributions->getCollection();
        $riwayatDistributions = $riwayatDetails->getCollection()->map(fn ($d) => $d->distribution)->filter()->unique('id');
        $allDists = $allDistributions->merge($riwayatDistributions);

        $warehouseIds = $allDists->flatMap(function ($d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $branchIds = $allDists->flatMap(function ($d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->to_location_id;
            }
            return $ids;
        })->filter()->unique()->values();

        $warehousesById = $warehouseIds->isNotEmpty()
            ? Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id')
            : collect();
        $branchesById = $branchIds->isNotEmpty()
            ? Branch::whereIn('id', $branchIds)->pluck('name', 'id')
            : collect();

        $products = Product::withCount([
            'units as in_stock_count' => fn ($q) => $q->where('status', ProductUnit::STATUS_IN_STOCK),
        ])->having('in_stock_count', '>', 0)->orderBy('sku')->get(['id', 'sku', 'brand']);

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $canCancelDistribution = $user->canModule('stock-mutations', 'cancel');

        return view('stock-mutations.index', compact(
            'activeTab',
            'distributions',
            'riwayatDetails',
            'products',
            'warehousesById',
            'branchesById',
            'branches',
            'warehouses',
            'canFilterLocation',
            'filterLocked',
            'locationType',
            'locationId',
            'locationLabel',
            'canCancelDistribution'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->buildDistributionsExportPayload($request);
        $filename = 'rekap-distribusi-'.now()->format('Ymd-His').'.xlsx';
        $html = view('stock-mutations.export', $payload)->render();

        return ExcelExporter::downloadFromHtml($html, $filename, 'generic');
    }

    public function exportPdf(Request $request)
    {
        $payload = $this->buildDistributionsExportPayload($request);
        $pdf = Pdf::loadView('stock-mutations.export-pdf', $payload)
            ->setPaper('a4', 'landscape');

        return $pdf->download('rekap-distribusi-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array{
     *     distributions: Collection<int, Distribution>,
     *     warehousesById: Collection<int|string, string>,
     *     branchesById: Collection<int|string, string>,
     *     filterMeta: array<string, string>,
     *     count: int,
     *     totalAmount: float,
     *     totalPaid: float,
     *     totalUnpaid: float
     * }
     */
    private function buildDistributionsExportPayload(Request $request): array
    {
        $user = $request->user();

        $filterLocked = false;
        $locationLabel = null;
        $accessConstraint = null;

        if ($user->hasUnrestrictedLocationAccess()) {
            // no default constraint
        } elseif ($user->isAdminPusat()) {
            $branchIds = $user->accessibleBranchIds();
            $warehouseIds = $user->accessibleWarehouseIds();
            if ($branchIds === [] && $warehouseIds === []) {
                abort(403, __('Admin Pusat belum didaftarkan ke cabang/gudang mana pun.'));
            }
            $accessConstraint = function ($q) use ($branchIds, $warehouseIds) {
                $q->where(function ($q2) use ($branchIds, $warehouseIds) {
                    if ($branchIds !== []) {
                        $q2->orWhere(function ($q3) use ($branchIds) {
                            $q3->where(function ($q4) use ($branchIds) {
                                $q4->where('from_location_type', Stock::LOCATION_BRANCH)->whereIn('from_location_id', $branchIds);
                            })->orWhere(function ($q4) use ($branchIds) {
                                $q4->where('to_location_type', Stock::LOCATION_BRANCH)->whereIn('to_location_id', $branchIds);
                            });
                        });
                    }
                    if ($warehouseIds !== []) {
                        $q2->orWhere(function ($q3) use ($warehouseIds) {
                            $q3->where(function ($q4) use ($warehouseIds) {
                                $q4->where('from_location_type', Stock::LOCATION_WAREHOUSE)->whereIn('from_location_id', $warehouseIds);
                            })->orWhere(function ($q4) use ($warehouseIds) {
                                $q4->where('to_location_type', Stock::LOCATION_WAREHOUSE)->whereIn('to_location_id', $warehouseIds);
                            });
                        });
                    }
                });
            };
            $filterLocked = (count($branchIds) + count($warehouseIds)) <= 1;
            if ($filterLocked) {
                if (count($branchIds) === 1) {
                    $bid = (int) $branchIds[0];
                    $locationLabel = __('Cabang').': '.(Branch::find($bid)?->name ?? '#'.$bid);
                } elseif (count($warehouseIds) === 1) {
                    $wid = (int) $warehouseIds[0];
                    $locationLabel = __('Gudang').': '.(Warehouse::find($wid)?->name ?? '#'.$wid);
                }
            }
        } elseif ($user->warehouse_id) {
            $warehouseId = (int) $user->warehouse_id;
            $accessConstraint = function ($q) use ($warehouseId) {
                $q->where(function ($q2) use ($warehouseId) {
                    $q2->where(function ($q3) use ($warehouseId) {
                        $q3->where('from_location_type', Stock::LOCATION_WAREHOUSE)->where('from_location_id', $warehouseId);
                    })->orWhere(function ($q3) use ($warehouseId) {
                        $q3->where('to_location_type', Stock::LOCATION_WAREHOUSE)->where('to_location_id', $warehouseId);
                    });
                });
            };
            $filterLocked = true;
            $locationLabel = __('Gudang').': '.(Warehouse::find($warehouseId)?->name ?? '#'.$warehouseId);
        } elseif ($user->branch_id) {
            $branchId = (int) $user->branch_id;
            $accessConstraint = function ($q) use ($branchId) {
                $q->where(function ($q2) use ($branchId) {
                    $q2->where(function ($q3) use ($branchId) {
                        $q3->where('from_location_type', Stock::LOCATION_BRANCH)->where('from_location_id', $branchId);
                    })->orWhere(function ($q3) use ($branchId) {
                        $q3->where('to_location_type', Stock::LOCATION_BRANCH)->where('to_location_id', $branchId);
                    });
                });
            };
            $filterLocked = true;
            $locationLabel = __('Cabang').': '.(Branch::find($branchId)?->name ?? '#'.$branchId);
        } else {
            abort(403, __('User branch or warehouse not set.'));
        }

        $query = Distribution::with(['details.product', 'payments.paymentMethod', 'user', 'cancelUser'])
            ->orderByDesc('distribution_date')
            ->orderByDesc('id');

        if ($accessConstraint) {
            $accessConstraint($query);
        }

        if ($user->isSuperAdminOrAdminPusat() && $request->filled('location_type') && $request->filled('location_id')) {
            $locType = (string) $request->location_type;
            $locId = (int) $request->location_id;
            if (in_array($locType, [Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH], true) && $locId > 0) {
                $query->where(function ($q) use ($locType, $locId) {
                    $q->where(function ($q2) use ($locType, $locId) {
                        $q2->where('from_location_type', $locType)->where('from_location_id', $locId);
                    })->orWhere(function ($q2) use ($locType, $locId) {
                        $q2->where('to_location_type', $locType)->where('to_location_id', $locId);
                    });
                });
            }
        }
        if ($request->filled('product_id')) {
            $query->whereHas('details', fn ($q) => $q->where('product_id', $request->product_id));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('details.product', function ($q2) use ($term) {
                        $q2->where('sku', 'like', $term)
                            ->orWhere('brand', 'like', $term)
                            ->orWhere('series', 'like', $term);
                    })
                    ->orWhereHas('details', fn ($q2) => $q2->where('serial_numbers', 'like', $term));
            });
        }
        if ($request->filled('status')) {
            $status = (string) $request->status;
            if (in_array($status, [Distribution::STATUS_OPEN, Distribution::STATUS_ACTIVE, Distribution::STATUS_CANCELLED], true)) {
                $query->where('status', $status);
            } elseif ($status === 'paid_off') {
                $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                    ->where(function ($q) {
                        $q->where('total', '<=', 0)
                            ->orWhereRaw('total_paid >= (total - 0.02)');
                    });
            } elseif ($status === 'unpaid') {
                $query->where('status', '!=', Distribution::STATUS_CANCELLED)
                    ->where('total', '>', 0)
                    ->whereRaw('total_paid < (total - 0.02)');
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('distribution_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('distribution_date', '<=', $request->date_to);
        }

        /** @var Collection<int, Distribution> $distributions */
        $distributions = $query->get();

        $warehouseIds = $distributions->flatMap(function (Distribution $d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_WAREHOUSE) {
                $ids[] = (int) $d->to_location_id;
            }

            return $ids;
        })->filter()->unique()->values();

        $branchIds = $distributions->flatMap(function (Distribution $d) {
            $ids = [];
            if ($d->from_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->from_location_id;
            }
            if ($d->to_location_type === Stock::LOCATION_BRANCH) {
                $ids[] = (int) $d->to_location_id;
            }

            return $ids;
        })->filter()->unique()->values();

        $warehousesById = $warehouseIds->isNotEmpty()
            ? Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id')
            : collect();
        $branchesById = $branchIds->isNotEmpty()
            ? Branch::whereIn('id', $branchIds)->pluck('name', 'id')
            : collect();

        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $filterMeta = $this->distributionsExportFilterMeta($request, $canFilterLocation, $filterLocked, $locationLabel);

        $active = $distributions->filter(fn (Distribution $d) => ! $d->isCancelled());
        $totalAmount = (float) $active->sum('total');
        $totalPaid = (float) $active->sum('total_paid');
        $totalUnpaid = (float) $active
            ->filter(fn (Distribution $d) => (float) $d->total > 0 && ! $d->isPaidOff())
            ->sum(fn (Distribution $d) => max(0, (float) $d->total - (float) $d->total_paid));

        return [
            'distributions' => $distributions,
            'warehousesById' => $warehousesById,
            'branchesById' => $branchesById,
            'filterMeta' => $filterMeta,
            'count' => $distributions->count(),
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalUnpaid,
        ];
    }

    /**
     * @return array{dateFrom: string, dateTo: string, locationLine: string, productLine: string, statusLine: string, searchLine: string}
     */
    private function distributionsExportFilterMeta(
        Request $request,
        bool $canFilterLocation,
        bool $filterLocked,
        ?string $locationLabel
    ): array {
        $locationLine = $locationLabel ?? __('Semua');
        if ($canFilterLocation && ! $filterLocked) {
            $lt = $request->get('location_type');
            $lid = $request->get('location_id');
            if ($lt === Stock::LOCATION_WAREHOUSE && $lid) {
                $locationLine = __('Gudang').': '.(Warehouse::find($lid)?->name ?? '#'.$lid);
            } elseif ($lt === Stock::LOCATION_BRANCH && $lid) {
                $locationLine = __('Cabang').': '.(Branch::find($lid)?->name ?? '#'.$lid);
            } elseif ($lt === Stock::LOCATION_WAREHOUSE) {
                $locationLine = __('Semua Gudang');
            } elseif ($lt === Stock::LOCATION_BRANCH) {
                $locationLine = __('Semua Cabang');
            } else {
                $locationLine = __('Semua');
            }
        }

        $productLine = __('Semua');
        if ($request->filled('product_id')) {
            $product = Product::find($request->product_id);
            $productLine = $product
                ? trim(($product->sku ?? '').' - '.($product->brand ?? ''), ' -')
                : '#'.$request->product_id;
        }

        $statusOptions = collect(DocumentStatusPresenter::filterOptions('stock-mutations', true))
            ->pluck('label', 'value');
        $statusLine = $statusOptions[(string) $request->get('status', '')] ?? __('Semua');

        return [
            'dateFrom' => $request->get('date_from') ?: '-',
            'dateTo' => $request->get('date_to') ?: '-',
            'locationLine' => $locationLine,
            'productLine' => $productLine,
            'statusLine' => $statusLine,
            'searchLine' => $request->filled('search') ? (string) $request->search : '-',
        ];
    }

    public function show(Distribution $distribution): View
    {
        $this->authorizeDistributionAccess($distribution, auth()->user());

        $data = $this->distributionInvoiceViewData($distribution);
        $distribution->loadMissing('stockMutations');

        return view('stock-mutations.show', array_merge(
            $data,
            [
                'canCancelDistribution' => auth()->user()->canModule('stock-mutations', 'cancel'),
                'canAddPayment' => $this->canAddDistributionPayment($distribution),
            ]
        ));
    }

    public function invoice(Distribution $distribution): View
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);
        try {
            app(\App\Services\AuthorizationService::class)->assertInvoice($distribution);
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return view('stock-mutations.invoice', $this->distributionInvoiceViewData($distribution));
    }

    public function report(Distribution $distribution): View
    {
        $this->authorizeDistributionAccess($distribution, auth()->user());
        $distribution->load(['user', 'details.product', 'payments.paymentMethod', 'proofs']);

        return view('stock-mutations.report', compact('distribution'));
    }

    public function showCancel(Distribution $distribution): View|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.index')
                ->with('info', __('Distribusi ini sudah dibatalkan.'));
        }

        return view('stock-mutations.cancel', $this->distributionInvoiceViewData($distribution));
    }

    public function cancel(Request $request, Distribution $distribution): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdminOrAdminPusat()) {
            abort(403, __('Unauthorized.'));
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($distribution->status === Distribution::STATUS_OPEN) {
                $this->stockMutationService->cancelOpenDistribution(
                    $distribution,
                    (int) $user->id,
                    $validated['cancel_reason']
                );
            } else {
                $this->stockMutationService->cancelDistribution(
                    $distribution,
                    (int) $user->id,
                    $validated['cancel_reason']
                );
            }
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'distribution.cancel',
                'reference_type' => 'distribution',
                'reference_id' => $distribution->id,
                'description' => 'Cancel distribusi ' . $distribution->invoice_number . '. Alasan: ' . $validated['cancel_reason'],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi berhasil dibatalkan.'));
    }

    public function release(Request $request, Distribution $distribution): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeDistributionAccess($distribution, $user);

        try {
            $auth = app(\App\Services\AuthorizationService::class);
            $auth->assertCanRelease($distribution);
            $auth->assertReadyForRelease($distribution);
            \Illuminate\Support\Facades\DB::transaction(function () use ($distribution, $user) {
                $mutations = $this->stockMutationService->releaseOpenDistribution($distribution, (int) $user->id);
                $distribution->refresh()->load('payments.paymentMethod');
                if ((float) $distribution->total <= 0 || $distribution->payments->isEmpty()) {
                    return;
                }
                $already = CashFlow::query()
                    ->where('reference_type', CashFlow::REFERENCE_DISTRIBUTION)
                    ->where('reference_id', $distribution->id)
                    ->exists();
                if ($already) {
                    return;
                }

                $incomeCategory = IncomeCategory::firstOrCreate(
                    ['code' => 'DIST-BRG'],
                    [
                        'name' => 'Distribusi Barang',
                        'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                        'is_active' => true,
                    ]
                );
                $fromBranchId = $distribution->from_location_type === Stock::LOCATION_BRANCH ? $distribution->from_location_id : null;
                $fromWarehouseId = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE ? $distribution->from_location_id : null;
                $distributionPayments = [];
                foreach ($distribution->payments as $payment) {
                    CashFlow::create([
                        'branch_id' => $fromBranchId,
                        'warehouse_id' => $fromWarehouseId,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $payment->amount,
                        'description' => __('Distribusi Barang') . ' ' . $distribution->invoice_number . ' - ' . ($payment->paymentMethod?->display_label ?? __('Pembayaran')),
                        'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                        'reference_id' => $distribution->id,
                        'income_category_id' => $incomeCategory->id,
                        'payment_method_id' => $payment->payment_method_id,
                        'transaction_date' => $distribution->distribution_date?->toDateString() ?? now()->toDateString(),
                        'user_id' => $user->id,
                    ]);
                    $distributionPayments[] = [
                        'payment_method_id' => (int) $payment->payment_method_id,
                        'amount' => (float) $payment->amount,
                    ];
                }
                $this->autoFillDestinationPurchasePayments(
                    $mutations,
                    $distributionPayments,
                    $distribution->to_location_type,
                    (int) $distribution->to_location_id,
                    $distribution->distribution_date?->toDateString() ?? now()->toDateString(),
                    (int) $user->id,
                    $distribution->invoice_number
                );
            });
        } catch (InvalidArgumentException $e) {
            return $this->redirectReleaseFailure($request, $distribution, $e->getMessage());
        }

        return redirect()->route('stock-mutations.show', $distribution)->with('success', __('Distribusi berhasil di-release.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionInvoiceViewData(Distribution $distribution): array
    {
        $distribution->load(['details.product', 'payments.paymentMethod', 'user', 'cancelUser', 'proofs']);

        $fromLocation = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->from_location_id)
            : Branch::find($distribution->from_location_id);
        $toLocation = $distribution->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->to_location_id)
            : Branch::find($distribution->to_location_id);

        $totalBiaya = (float) $distribution->total;
        $totalPaid = (float) $distribution->total_paid;
        $isLunas = $totalBiaya <= 0 || ($totalPaid + 0.02 >= $totalBiaya);

        return [
            'distribution' => $distribution,
            'fromLocation' => $fromLocation,
            'toLocation' => $toLocation,
            'totalBiaya' => $totalBiaya,
            'totalPaid' => $totalPaid,
            'isLunas' => $isLunas,
        ];
    }

    public function proofs(Distribution $distribution): View
    {
        $this->authorizeDistributionAccess($distribution, auth()->user());
        $distribution->load('proofs');

        return view('stock-mutations.proofs', [
            'distribution' => $distribution,
        ]);
    }

    public function addPayment(Distribution $distribution): View|RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.show', $distribution)->with('error', __('Distribusi ini sudah dibatalkan.'));
        }
        if (! $this->canAddDistributionPayment($distribution)) {
            return redirect()->route('stock-mutations.show', $distribution)
                ->with('error', __('Pembayaran hanya bisa ditambah setelah otorisasi disetujui, atau setelah distribusi aktif dan belum lunas.'));
        }
        $totalBiaya = (float) $distribution->total;
        if ($totalBiaya <= 0) {
            return redirect()->route('stock-mutations.show', $distribution)->with('info', __('Distribusi ini tidak memiliki biaya.'));
        }

        $totalPaid = (float) $distribution->total_paid;
        $sisa = max(0, $totalBiaya - $totalPaid);
        if ($sisa < 0.01) {
            return redirect()->route('stock-mutations.show', $distribution)->with('info', __('Pembayaran sudah lunas.'));
        }

        $branchId = $distribution->from_location_type === Stock::LOCATION_BRANCH ? (int) $distribution->from_location_id : null;
        $warehouseId = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $distribution->from_location_id : null;

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $branchId && ! $warehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('jenis_pembayaran')
            ->orderBy('nama_bank')
            ->get(['id', 'jenis_pembayaran', 'nama_bank', 'atas_nama_bank', 'no_rekening']);

        $fromLocation = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->from_location_id)
            : Branch::find($distribution->from_location_id);
        $toLocation = $distribution->to_location_type === Stock::LOCATION_WAREHOUSE
            ? Warehouse::find($distribution->to_location_id)
            : Branch::find($distribution->to_location_id);

        return view('stock-mutations.add-payment', compact(
            'distribution',
            'paymentMethods',
            'totalBiaya',
            'totalPaid',
            'sisa',
            'fromLocation',
            'toLocation'
        ));
    }

    public function storePayment(Request $request, Distribution $distribution): RedirectResponse
    {
        $user = auth()->user();
        $this->authorizeDistributionAccess($distribution, $user);

        if ($distribution->isCancelled()) {
            return redirect()->route('stock-mutations.show', $distribution)->with('error', __('Distribusi ini sudah dibatalkan.'));
        }
        if (! $this->canAddDistributionPayment($distribution)) {
            return back()->with('error', __('Pembayaran hanya bisa ditambah setelah otorisasi disetujui, atau setelah distribusi aktif dan belum lunas.'));
        }
        $totalBiaya = (float) $distribution->total;
        $totalPaid = (float) $distribution->total_paid;
        $sisa = max(0, $totalBiaya - $totalPaid);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($sisa + 0.02)],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $pmId = (int) $validated['payment_method_id'];
        $transactionDate = $validated['transaction_date'] ?? $distribution->distribution_date?->toDateString() ?? now()->toDateString();

        $pm = PaymentMethod::find($pmId);
        $branchId = $distribution->from_location_type === Stock::LOCATION_BRANCH ? (int) $distribution->from_location_id : null;
        $warehouseId = $distribution->from_location_type === Stock::LOCATION_WAREHOUSE ? (int) $distribution->from_location_id : null;
        $validPm = ($branchId && (int) ($pm->branch_id ?? 0) === $branchId)
            || ($warehouseId && (int) ($pm->warehouse_id ?? 0) === $warehouseId);
        if (! $validPm) {
            return back()->withInput()->with('error', __('Metode pembayaran harus dari lokasi asal.'));
        }

        // Saat masih Open (sudah otorisasi): catat pembayaran saja; kas masuk diposting saat Release.
        $deferCashFlow = $distribution->status === Distribution::STATUS_OPEN;

        $incomeCategory = null;
        if (! $deferCashFlow) {
            $incomeCategory = IncomeCategory::firstOrCreate(
                ['code' => 'DIST-BRG'],
                [
                    'name' => 'Distribusi Barang',
                    'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                    'is_active' => true,
                ]
            );
        }

        DB::transaction(function () use ($distribution, $pmId, $amount, $transactionDate, $branchId, $warehouseId, $incomeCategory, $pm, $user, $deferCashFlow) {
            DistributionPayment::create([
                'distribution_id' => $distribution->id,
                'payment_method_id' => $pmId,
                'amount' => $amount,
            ]);

            if (! $deferCashFlow && $incomeCategory) {
                CashFlow::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'type' => CashFlow::TYPE_IN,
                    'amount' => $amount,
                    'description' => __('Distribusi Barang') . ' ' . $distribution->invoice_number . ' - ' . ($pm->display_label ?? __('Pembayaran')),
                    'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                    'reference_id' => $distribution->id,
                    'income_category_id' => $incomeCategory->id,
                    'payment_method_id' => $pmId,
                    'transaction_date' => $transactionDate,
                    'user_id' => $user->id,
                ]);
            }

            $distribution->update([
                'total_paid' => (float) $distribution->total_paid + $amount,
            ]);
        });

        return redirect()->route('stock-mutations.show', $distribution)
            ->with('success', __('Pembayaran berhasil dicatat.'));
    }

    private function canAddDistributionPayment(Distribution $distribution): bool
    {
        if ($distribution->isCancelled()) {
            return false;
        }
        if ((float) $distribution->total <= 0) {
            return false;
        }
        if ($distribution->isPaidOff()) {
            return false;
        }

        $auth = app(\App\Services\AuthorizationService::class);
        if ($distribution->status === Distribution::STATUS_ACTIVE) {
            return ! $auth->blocksMutation($distribution);
        }
        if ($distribution->status === Distribution::STATUS_OPEN) {
            return $auth->allowsCommit($distribution) && ! $auth->blocksMutation($distribution);
        }

        return false;
    }

    private function authorizeDistributionAccess(Distribution $distribution, $user): void
    {
        if ($user->hasUnrestrictedLocationAccess()) {
            return;
        }
        if ($user->isAdminPusat()) {
            $fromOk = ($distribution->from_location_type === Stock::LOCATION_BRANCH && $user->canAccessBranch((int) $distribution->from_location_id))
                || ($distribution->from_location_type === Stock::LOCATION_WAREHOUSE && $user->canAccessWarehouse((int) $distribution->from_location_id));
            $toOk = ($distribution->to_location_type === Stock::LOCATION_BRANCH && $user->canAccessBranch((int) $distribution->to_location_id))
                || ($distribution->to_location_type === Stock::LOCATION_WAREHOUSE && $user->canAccessWarehouse((int) $distribution->to_location_id));
            if (! $fromOk && ! $toOk) {
                abort(403, __('Unauthorized.'));
            }

            return;
        }
        if ($user->branch_id) {
            $involved = ($distribution->from_location_type === Stock::LOCATION_BRANCH && (int) $distribution->from_location_id === (int) $user->branch_id)
                || ($distribution->to_location_type === Stock::LOCATION_BRANCH && (int) $distribution->to_location_id === (int) $user->branch_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
            return;
        }
        if ($user->warehouse_id) {
            $involved = ($distribution->from_location_type === Stock::LOCATION_WAREHOUSE && (int) $distribution->from_location_id === (int) $user->warehouse_id)
                || ($distribution->to_location_type === Stock::LOCATION_WAREHOUSE && (int) $distribution->to_location_id === (int) $user->warehouse_id);
            if (! $involved) {
                abort(403, __('Unauthorized.'));
            }
        }
    }

    private function normalizeNoRekening(?string $no): string
    {
        return preg_replace('/\s+/', '', trim((string) $no));
    }

    private function matchDestinationPaymentMethod(PaymentMethod $originPm, $destPaymentMethods): ?PaymentMethod
    {
        $originRek = $this->normalizeNoRekening($originPm->no_rekening ?? '');

        if ($originRek !== '') {
            return $destPaymentMethods->first(
                fn ($pm) => $this->normalizeNoRekening($pm->no_rekening ?? '') === $originRek
            );
        }

        $originJenis = strtolower(trim($originPm->jenis_pembayaran ?? ''));

        return $destPaymentMethods->first(function ($pm) use ($originJenis) {
            if ($this->normalizeNoRekening($pm->no_rekening ?? '') !== '') {
                return false;
            }

            return strtolower(trim($pm->jenis_pembayaran ?? '')) === $originJenis;
        });
    }

    private function autoFillDestinationPurchasePayments(
        array $mutations,
        array $distributionPayments,
        string $toLocationType,
        int $toLocationId,
        string $mutationDate,
        int $userId,
        string $invoiceNumber
    ): void {
        $toBranchId = $toLocationType === Stock::LOCATION_BRANCH ? $toLocationId : null;
        $toWarehouseId = $toLocationType === Stock::LOCATION_WAREHOUSE ? $toLocationId : null;

        $destPaymentMethods = PaymentMethod::where('is_active', true)
            ->when($toBranchId, fn ($q) => $q->where('branch_id', $toBranchId))
            ->when($toWarehouseId, fn ($q) => $q->where('warehouse_id', $toWarehouseId))
            ->when(! $toBranchId && ! $toWarehouseId, fn ($q) => $q->whereRaw('1=0'))
            ->get();

        if ($destPaymentMethods->isEmpty()) {
            return;
        }

        $matchedPayments = [];
        foreach ($distributionPayments as $dp) {
            $originPm = PaymentMethod::find($dp['payment_method_id']);
            if (! $originPm) {
                continue;
            }

            $destPm = $this->matchDestinationPaymentMethod($originPm, $destPaymentMethods);

            if ($destPm) {
                $matchedPayments[] = [
                    'payment_method_id' => $destPm->id,
                    'amount' => $dp['amount'],
                ];
            }
        }

        if (empty($matchedPayments)) {
            return;
        }

        $purchases = [];
        foreach ($mutations as $m) {
            $m->load('purchase');
            if ($m->purchase && (float) $m->purchase->total > 0) {
                $purchases[] = $m->purchase;
            }
        }

        if (empty($purchases)) {
            return;
        }

        foreach ($matchedPayments as $mp) {
            $remaining = round($mp['amount'], 2);

            foreach ($purchases as $purchase) {
                if ($remaining <= 0) {
                    break;
                }

                $purchaseRemaining = round((float) $purchase->total - (float) $purchase->total_paid, 2);
                if ($purchaseRemaining <= 0) {
                    continue;
                }

                $payAmount = round(min($remaining, $purchaseRemaining), 2);
                $this->purchaseService->addPayment(
                    $purchase,
                    $mp['payment_method_id'],
                    $payAmount,
                    $mutationDate,
                    $userId,
                    __('Auto dari distribusi') . ' ' . $invoiceNumber
                );
                $purchase->refresh();
                $remaining = round($remaining - $payAmount, 2);
            }
        }
    }

    public function create(): View
    {
        $user = auth()->user();
        if (! $user->hasUnrestrictedLocationAccess()
            && ! $user->isAdminPusat()
            && ! $user->branch_id
            && ! $user->warehouse_id) {
            abort(403, __('User branch or warehouse not set.'));
        }

        $locationOptions = \App\Services\LocationService::getLocationOptionsForUser($user);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        // Lokasi asal: sesuai akses user (bisa dikunci ke placement).
        $warehouses = $locationOptions['warehouses'];
        $branches = $locationOptions['branches'];
        // Lokasi tujuan: semua cabang & gudang — distribusi mengirim ke lokasi lain.
        $destinationWarehouses = Warehouse::query()->orderBy('name')->get(['id', 'name']);
        $destinationBranches = Branch::query()->orderBy('name')->get(['id', 'name']);

        $lockFromLocation = false;
        $defaultFromLocationType = old('from_location_type');
        $defaultFromLocationId = old('from_location_id');
        $fromLocationLabel = null;

        if (! $user->hasUnrestrictedLocationAccess() && ! $user->isAdminPusat()) {
            $lockFromLocation = true;
            if ($user->warehouse_id) {
                $defaultFromLocationType = Stock::LOCATION_WAREHOUSE;
                $defaultFromLocationId = (int) $user->warehouse_id;
                $wh = Warehouse::find($defaultFromLocationId);
                $fromLocationLabel = __('Gudang') . ': ' . ($wh?->name ?? '#' . $defaultFromLocationId);
            } elseif ($user->branch_id) {
                $defaultFromLocationType = Stock::LOCATION_BRANCH;
                $defaultFromLocationId = (int) $user->branch_id;
                $br = Branch::find($defaultFromLocationId);
                $fromLocationLabel = __('Cabang') . ': ' . ($br?->name ?? '#' . $defaultFromLocationId);
            }
        } elseif ($user->isAdminPusat() && ($branches->count() + $warehouses->count()) === 1) {
            $lockFromLocation = true;
            if ($warehouses->count() === 1) {
                $defaultFromLocationType = Stock::LOCATION_WAREHOUSE;
                $defaultFromLocationId = (int) $warehouses->first()->id;
                $fromLocationLabel = __('Gudang') . ': ' . $warehouses->first()->name;
            } else {
                $defaultFromLocationType = Stock::LOCATION_BRANCH;
                $defaultFromLocationId = (int) $branches->first()->id;
                $fromLocationLabel = __('Cabang') . ': ' . $branches->first()->name;
            }
        }

        return view('stock-mutations.create', compact(
            'categories',
            'warehouses',
            'branches',
            'destinationWarehouses',
            'destinationBranches',
            'lockFromLocation',
            'defaultFromLocationType',
            'defaultFromLocationId',
            'fromLocationLabel'
        ));
    }

    public function availableProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'from_location_type' => ['required', 'in:' . Stock::LOCATION_WAREHOUSE . ',' . Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $fromType = (string) $validated['from_location_type'];
        $fromId = (int) $validated['from_location_id'];
        $this->assertCanAccessStockLocation($user, $fromType, $fromId);
        $categoryId = $validated['category_id'] ?? null;

        $productIds = ProductUnit::query()
            ->where('location_type', $fromType)
            ->where('location_id', $fromId)
            ->where('status', ProductUnit::STATUS_IN_STOCK)
            ->select('product_id')
            ->distinct()
            ->pluck('product_id');

        $query = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->withCount([
                'units as in_stock_count' => fn ($q) => $q
                    ->where('location_type', $fromType)
                    ->where('location_id', $fromId)
                    ->where('status', ProductUnit::STATUS_IN_STOCK),
            ])
            ->orderBy('brand')
            ->orderBy('series')
            ->orderBy('sku');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(500)->get(['id', 'sku', 'brand', 'series', 'color', 'selling_price', 'category_id']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku ?? '',
                'brand' => $p->brand ?? '',
                'series' => $p->series ?? '',
                'color' => $p->color ?? '',
                'selling_price' => $p->selling_price,
                'in_stock_count' => $p->in_stock_count ?? 0,
            ])->values(),
        ]);
    }

    public function availableSerials(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'from_location_type' => ['required', 'in:' . Stock::LOCATION_WAREHOUSE . ',' . Stock::LOCATION_BRANCH],
            'from_location_id' => ['required', 'integer', 'min:1'],
        ]);

        $fromType = (string) $validated['from_location_type'];
        $fromId = (int) $validated['from_location_id'];
        $this->assertCanAccessStockLocation($user, $fromType, $fromId);

        $limit = 500;
        $query = ProductUnit::query()
            ->where('product_id', (int) $validated['product_id'])
            ->where('location_type', $fromType)
            ->where('location_id', $fromId)
            ->where('status', ProductUnit::STATUS_IN_STOCK);

        $total = (clone $query)->count();
        $units = $query
            ->orderBy('id')
            ->limit($limit)
            ->get(['serial_number', 'harga_hpp']);

        return response()->json([
            'serial_numbers' => $units->pluck('serial_number')->all(),
            'serial_hpp' => $units->pluck('harga_hpp', 'serial_number')->all(),
            'total_available' => $total,
            'truncated' => $total > $limit,
        ]);
    }

    public function store(StockMutationRequest $request): RedirectResponse
    {
        $user = $request->user();

        app(TransactionProofService::class)->validateUploads($request, null, false);

        try {
            $fromLocationType = $request->from_location_type;
            $fromLocationId = (int) $request->from_location_id;

            if (! $user->hasUnrestrictedLocationAccess() && ! $user->isAdminPusat()) {
                if ($user->warehouse_id) {
                    $fromLocationType = Stock::LOCATION_WAREHOUSE;
                    $fromLocationId = (int) $user->warehouse_id;
                } elseif ($user->branch_id) {
                    $fromLocationType = Stock::LOCATION_BRANCH;
                    $fromLocationId = (int) $user->branch_id;
                }
            }

            $this->assertCanAccessStockLocation($user, (string) $fromLocationType, (int) $fromLocationId);

            $items = collect($request->input('items', []))->values()->all();
            if (empty($items)) {
                return back()->withInput()->with('error', __('Minimal 1 produk harus ditambahkan.'));
            }

            $invoiceNumber = $this->stockMutationService->generateDistributionInvoiceNumber();

            DB::beginTransaction();

            $totalBiaya = 0;
            $details = [];
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $serialNumbers = $this->normalizeSerialNumbersInput($item['serial_numbers'] ?? null);
                $quantity = ! empty($serialNumbers) ? count($serialNumbers) : (int) ($item['quantity'] ?? 0);
                $biayaDistribusi = (float) ($item['biaya_distribusi_per_unit'] ?? 0);
                $affectsHpp = filter_var($item['affects_hpp'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($biayaDistribusi > 0 && empty($serialNumbers)) {
                    throw new \InvalidArgumentException(
                        __('Distribusi dengan biaya wajib menggunakan nomor serial untuk produk: ') . ($product->sku ?? $product->id)
                    );
                }

                $hppPerUnit = isset($item['hpp_per_unit']) && $item['hpp_per_unit'] !== '' && $item['hpp_per_unit'] !== null
                    ? round((float) $item['hpp_per_unit'], 2)
                    : null;

                $totalBiaya += $biayaDistribusi * $quantity;
                $details[] = [
                    'product' => $product,
                    'serial_numbers' => $serialNumbers,
                    'quantity' => $quantity,
                    'biaya_distribusi' => $biayaDistribusi,
                    'affects_hpp' => $affectsHpp,
                    'hpp_per_unit' => $hppPerUnit,
                ];
            }

            $distributionPayments = collect($request->input('distribution_payments', []))
                ->filter(fn ($p) => ! empty($p['payment_method_id']) && (float) ($p['amount'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'payment_method_id' => (int) $p['payment_method_id'],
                    'amount' => round((float) $p['amount'], 2),
                ])
                ->values()
                ->all();

            $totalPaid = collect($distributionPayments)->sum('amount');

            $deferAuthorization = app(\App\Services\AuthorizationService::class)->enabled('stock-mutations');
            $distribution = Distribution::create([
                'invoice_number' => $invoiceNumber,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $request->to_location_type,
                'to_location_id' => (int) $request->to_location_id,
                'total' => round($totalBiaya, 2),
                'total_paid' => round($totalPaid, 2),
                'notes' => $request->notes,
                'user_id' => $user->id,
                'distribution_date' => $request->mutation_date,
                'status' => $deferAuthorization ? Distribution::STATUS_OPEN : Distribution::STATUS_ACTIVE,
                'requires_authorization' => $deferAuthorization,
            ]);

            $mutations = [];
            foreach ($details as $detail) {
                $mutation = null;
                if (! $deferAuthorization) {
                $mutation = $this->stockMutationService->mutate(
                    $detail['product'],
                    $fromLocationType,
                    $fromLocationId,
                    $request->to_location_type,
                    (int) $request->to_location_id,
                    $detail['quantity'],
                    $request->mutation_date,
                    $request->notes,
                    $user->id,
                    $detail['serial_numbers'],
                    $detail['biaya_distribusi'],
                    [],
                    $invoiceNumber,
                    $distribution->id,
                    (bool) $detail['affects_hpp']
                );
                $mutations[] = $mutation;
                }

                $finalHpp = $detail['hpp_per_unit'] !== null
                    ? $detail['hpp_per_unit']
                    : (float) ($mutation?->hpp_per_unit ?? 0);

                DistributionDetail::create([
                    'distribution_id' => $distribution->id,
                    'product_id' => $detail['product']->id,
                    'quantity' => $detail['quantity'],
                    'biaya_distribusi_per_unit' => $detail['biaya_distribusi'],
                    'affects_hpp' => (bool) $detail['affects_hpp'],
                    'hpp_per_unit' => $finalHpp,
                    'serial_numbers' => ! empty($detail['serial_numbers']) ? implode("\n", $detail['serial_numbers']) : null,
                ]);
            }

            if ($deferAuthorization) {
                foreach ($distributionPayments as $payment) {
                    DistributionPayment::create([
                        'distribution_id' => $distribution->id,
                        'payment_method_id' => (int) $payment['payment_method_id'],
                        'amount' => round((float) $payment['amount'], 2),
                    ]);
                }
                $this->stockMutationService->reserveDistributionUnits($distribution);
            } elseif ($totalBiaya > 0 && ! empty($distributionPayments)) {
                $incomeCategory = IncomeCategory::firstOrCreate(
                    ['code' => 'DIST-BRG'],
                    [
                        'name' => 'Distribusi Barang',
                        'description' => 'Pemasukan dari biaya distribusi barang antar lokasi',
                        'is_active' => true,
                    ]
                );

                $fromBranchId = $fromLocationType === Stock::LOCATION_BRANCH ? $fromLocationId : null;
                $fromWarehouseId = $fromLocationType === Stock::LOCATION_WAREHOUSE ? $fromLocationId : null;

                foreach ($distributionPayments as $payment) {
                    $pmId = (int) ($payment['payment_method_id'] ?? 0);
                    $amount = round((float) ($payment['amount'] ?? 0), 2);
                    if ($pmId <= 0 || $amount <= 0) {
                        continue;
                    }

                    $pm = PaymentMethod::find($pmId);
                    if ($pm) {
                        $pmBranch = (int) ($pm->branch_id ?? 0);
                        $pmWarehouse = (int) ($pm->warehouse_id ?? 0);
                        $validPm = ($fromLocationType === Stock::LOCATION_BRANCH && $pmBranch === $fromLocationId)
                            || ($fromLocationType === Stock::LOCATION_WAREHOUSE && $pmWarehouse === $fromLocationId);
                        if (! $validPm) {
                            throw new \InvalidArgumentException(__('Metode pembayaran harus dari lokasi asal.'));
                        }
                    }

                    DistributionPayment::create([
                        'distribution_id' => $distribution->id,
                        'payment_method_id' => $pmId,
                        'amount' => $amount,
                    ]);

                    CashFlow::create([
                        'branch_id' => $fromBranchId,
                        'warehouse_id' => $fromWarehouseId,
                        'type' => CashFlow::TYPE_IN,
                        'amount' => $amount,
                        'description' => __('Distribusi Barang') . ' ' . $invoiceNumber . ' - ' . ($pm?->display_label ?? __('Pembayaran')),
                        'reference_type' => CashFlow::REFERENCE_DISTRIBUTION,
                        'reference_id' => $distribution->id,
                        'income_category_id' => $incomeCategory->id,
                        'payment_method_id' => $pmId,
                        'transaction_date' => $request->mutation_date,
                        'user_id' => $user->id,
                    ]);
                }

                $this->autoFillDestinationPurchasePayments(
                    $mutations,
                    $distributionPayments,
                    $request->to_location_type,
                    (int) $request->to_location_id,
                    $request->mutation_date,
                    $user->id,
                    $invoiceNumber
                );
            }

            app(TransactionProofService::class)->storeUploaded($request, $distribution, $user->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-mutations.index')->with('success', __('Distribusi barang berhasil dibuat.'));
    }

    /**
     * @return array<int, string>
     */
    private function parseSerialNumbers(?string $input): array
    {
        if (! $input) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = $p;
        }

        return $this->ensureUniqueSerials($out);
    }

    /**
     * @param  mixed  $input
     * @return array<int, string>
     */
    private function normalizeSerialNumbersInput(mixed $input): array
    {
        if (is_array($input)) {
            $out = [];
            foreach ($input as $sn) {
                $sn = trim((string) $sn);
                if ($sn === '') {
                    continue;
                }
                $out[] = $sn;
            }

            return $this->ensureUniqueSerials($out);
        }

        if (is_string($input)) {
            return $this->parseSerialNumbers($input);
        }

        return [];
    }

    /**
     * @param  array<int, string>  $serials
     * @return array<int, string>
     */
    private function ensureUniqueSerials(array $serials): array
    {
        $serials = array_values($serials);
        $counts = array_count_values($serials);
        $duplicates = array_keys(array_filter($counts, fn ($count) => $count > 1));
        if (! empty($duplicates)) {
            throw new InvalidArgumentException(
                __('Nomor serial tidak boleh duplikat: :serials', ['serials' => implode(', ', $duplicates)])
            );
        }

        return $serials;
    }

    private function assertCanAccessStockLocation(User $user, string $locationType, int $locationId): void
    {
        if ($locationType === Stock::LOCATION_WAREHOUSE) {
            UserLocationScope::assertCanAccessLocation($user, null, $locationId);

            return;
        }

        UserLocationScope::assertCanAccessLocation($user, $locationId, null);
    }
}
