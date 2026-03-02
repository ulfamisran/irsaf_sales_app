<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistributorRequest;
use App\Models\Distributor;
use App\Repositories\DistributorRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistributorController extends Controller
{
    public function __construct(
        protected DistributorRepository $distributorRepository
    ) {}

    /**
     * Display a listing of distributors.
     */
    public function index(Request $request): View
    {
        $distributors = $this->distributorRepository->paginate(15, [
            'search' => $request->get('search'),
        ]);

        return view('distributors.index', compact('distributors'));
    }

    /**
     * Show the form for creating a new distributor.
     */
    public function create(): View
    {
        return view('distributors.create');
    }

    /**
     * Store a newly created distributor.
     */
    public function store(DistributorRequest $request): RedirectResponse
    {
        $this->distributorRepository->create($request->validated());
        return redirect()->route('distributors.index')->with('success', __('Distributor created successfully.'));
    }

    /**
     * Show the form for editing the specified distributor.
     */
    public function edit(Distributor $distributor): View
    {
        return view('distributors.edit', compact('distributor'));
    }

    /**
     * Update the specified distributor.
     */
    public function update(DistributorRequest $request, Distributor $distributor): RedirectResponse
    {
        $this->distributorRepository->update($distributor, $request->validated());
        return redirect()->route('distributors.index')->with('success', __('Distributor updated successfully.'));
    }

    /**
     * Remove the specified distributor.
     */
    public function destroy(Distributor $distributor): RedirectResponse
    {
        $this->distributorRepository->delete($distributor);
        return redirect()->route('distributors.index')->with('success', __('Distributor deleted successfully.'));
    }
}
