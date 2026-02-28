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
            'branch_id' => ['nullable', 'exists:branches,id', 'required_without:warehouse_id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id', 'required_without:branch_id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

