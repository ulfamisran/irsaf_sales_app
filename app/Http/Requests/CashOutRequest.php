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
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.expense_category_id' => ['required', 'exists:expense_categories,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal harus ada 1 item pengeluaran.',
            'items.*.expense_category_id.required' => 'Jenis pengeluaran wajib dipilih.',
            'items.*.name.required' => 'Nama pengeluaran wajib diisi.',
            'items.*.amount.required' => 'Nominal wajib diisi.',
            'items.*.amount.min' => 'Nominal harus lebih dari 0.',
        ];
    }
}

