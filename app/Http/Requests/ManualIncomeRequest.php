<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_type' => ['required', 'in:branch,warehouse'],
            'branch_id' => ['required_if:location_type,branch', 'nullable', 'exists:branches,id'],
            'warehouse_id' => ['required_if:location_type,warehouse', 'nullable', 'exists:warehouses,id'],
            'income_category_id' => ['required', 'exists:income_categories,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

