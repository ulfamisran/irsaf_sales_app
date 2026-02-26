<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_new_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_new_phone' => ['nullable', 'string', 'max:30'],
            'customer_new_address' => ['nullable', 'string'],

            'laptop_type' => ['required', 'string', 'max:100'],
            'laptop_detail' => ['nullable', 'string'],
            'damage_description' => ['nullable', 'string'],
            'service_cost' => ['required', 'numeric', 'min:0'],
            'service_price' => ['required', 'numeric', 'min:0'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ];
    }
}
