<?php

namespace App\Http\Requests;

use App\Models\Stock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'from_location_type' => ['required', Rule::in([Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH])],
            'from_location_id' => ['required', 'integer', 'min:1'],
            'to_location_type' => ['required', Rule::in([Stock::LOCATION_WAREHOUSE, Stock::LOCATION_BRANCH])],
            'to_location_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'required_without:serial_numbers'],
            'serial_numbers' => [
                'nullable',
                'required_without:quantity',
                Rule::when(is_array($this->input('serial_numbers')), ['array', 'min:1'], ['string']),
            ],
            'serial_numbers.*' => ['string'],
            'mutation_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
