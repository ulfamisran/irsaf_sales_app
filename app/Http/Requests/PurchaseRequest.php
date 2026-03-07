<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        foreach ($items as $i => $item) {
            if (isset($item['unit_price'])) {
                $items[$i]['unit_price'] = $this->parseRupiah($item['unit_price']);
            }
        }
        $this->merge(['items' => $items]);

        $payments = $this->input('payments', []);
        foreach ($payments as $i => $p) {
            if (isset($p['amount'])) {
                $payments[$i]['amount'] = $this->parseRupiah($p['amount']);
            }
        }
        $this->merge(['payments' => $payments]);
    }

    private function parseRupiah(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = preg_replace('/[^\d]/', '', (string) $value);

        return $str !== '' ? (float) $str : null;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:purchases,invoice_number'],
            'location_type' => ['required', 'in:warehouse,branch'],
            'warehouse_id' => ['required_if:location_type,warehouse', 'nullable', 'exists:warehouses,id'],
            'branch_id' => ['required_if:location_type,branch', 'nullable', 'exists:branches,id'],
            'distributor_id' => ['required', 'exists:distributors,id'],
            'purchase_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'termin' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'array'],
            'items.*.serial_numbers.*' => ['string', 'distinct'],

            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.notes' => ['nullable', 'string'],
        ];
    }
}
