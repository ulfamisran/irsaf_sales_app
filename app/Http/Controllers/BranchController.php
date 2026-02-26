<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Repositories\BranchRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(
        protected BranchRepository $branchRepository
    ) {}

    /**
     * Display a listing of branches.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Branch::class);
        $branches = $this->branchRepository->paginate(15, [
            'search' => $request->get('search'),
        ]);
        return view('branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(): View
    {
        $this->authorize('create', \App\Models\Branch::class);
        return view('branches.create');
    }

    /**
     * Store a newly created branch.
     */
    public function store(BranchRequest $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\Branch::class);
        $this->branchRepository->create($request->validated());
        return redirect()->route('branches.index')->with('success', __('Branch created successfully.'));
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);
        return view('branches.edit', compact('branch'));
    }

    /**
     * Update the specified branch.
     */
    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);
        $this->branchRepository->update($branch, $request->validated());
        return redirect()->route('branches.index')->with('success', __('Branch updated successfully.'));
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);
        $this->branchRepository->delete($branch);
        return redirect()->route('branches.index')->with('success', __('Branch deleted successfully.'));
    }

    /**
     * Display unit/serial products available in a branch.
     */
    public function units(Request $request, Branch $branch): View
    {
        $this->authorize('view', $branch);

        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && $user->branch_id && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, __('Unauthorized.'));
        }

        $query = ProductUnit::with(['product.category'])
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branch->id)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->product_id);
        }
        if ($request->filled('serial')) {
            $query->where('serial_number', 'like', '%'.$request->serial.'%');
        }

        $units = $query->paginate(25)->withQueryString();

        $productIds = ProductUnit::query()
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branch->id)
            ->distinct()
            ->pluck('product_id')
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('sku')
            ->get(['id', 'sku', 'brand', 'series']);

        $summary = ProductUnit::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where('location_type', Stock::LOCATION_BRANCH)
            ->where('location_id', $branch->id)
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('branches.units', compact('branch', 'units', 'products', 'summary'));
    }
}
