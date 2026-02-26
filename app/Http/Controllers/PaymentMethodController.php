<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentMethodRepository $paymentMethodRepository
    ) {}

    public function index(Request $request): View
    {
        $methods = $this->paymentMethodRepository->paginate(15, [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
        ]);

        return view('payment-methods.index', compact('methods'));
    }

    public function create(): View
    {
        return view('payment-methods.create');
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Checkbox sends "1" when checked
        $data['is_active'] = (bool) $request->input('is_active', false);

        $this->paymentMethodRepository->create($data);

        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil dibuat.'));
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('payment-methods.edit', compact('paymentMethod'));
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) $request->input('is_active', false);

        $this->paymentMethodRepository->update($paymentMethod, $data);

        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil diperbarui.'));
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethodRepository->delete($paymentMethod);
        return redirect()->route('payment-methods.index')->with('success', __('Metode pembayaran berhasil dihapus.'));
    }
}

