<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        $items = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.expense_category_id' => ['required', 'exists:expense_categories,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];

        $base = [
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_date' => ['required', 'date'],
        ];

        if ($user && $user->isSuperAdminOrAdminPusat()) {
            return array_merge($base, [
                'location_type' => ['required', 'in:branch,warehouse'],
                'branch_id' => ['required_if:location_type,branch', 'nullable', 'exists:branches,id'],
                'warehouse_id' => ['required_if:location_type,warehouse', 'nullable', 'exists:warehouses,id'],
            ], $items);
        }

        return array_merge($base, $items);
    }

    public function messages(): array
    {
        return [
            'location_type.required' => __('Tipe lokasi wajib dipilih.'),
            'branch_id.required_if' => __('Cabang wajib dipilih.'),
            'warehouse_id.required_if' => __('Gudang wajib dipilih.'),
            'items.required' => 'Minimal harus ada 1 item pengeluaran.',
            'items.*.expense_category_id.required' => 'Jenis pengeluaran wajib dipilih.',
            'items.*.name.required' => 'Nama pengeluaran wajib diisi.',
            'items.*.amount.required' => 'Nominal wajib diisi.',
            'items.*.amount.min' => 'Nominal harus lebih dari 0.',
        ];
    }
}

