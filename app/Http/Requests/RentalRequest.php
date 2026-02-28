<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_new_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_new_phone' => ['nullable', 'string', 'max:30'],
            'customer_new_address' => ['nullable', 'string'],

            'pickup_date' => ['required', 'date'],
            'return_date' => ['required', 'date', 'after_or_equal:pickup_date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.serial_number' => ['required', 'string', 'max:255'],
            'items.*.rental_price' => ['required', 'numeric', 'min:0.01'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.notes' => ['nullable', 'string'],
        ];
    }
}
