<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerRepository $customerRepository
    ) {}

    public function index(Request $request): View
    {
        $customers = $this->customerRepository->paginate(15, [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
        ]);

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $this->customerRepository->create($data);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil dibuat.'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $this->customerRepository->update($customer, $data);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil diperbarui.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->customerRepository->delete($customer);

        return redirect()->route('customers.index')->with('success', __('Pelanggan berhasil dihapus.'));
    }
}

