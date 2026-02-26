<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi format Rupiah: "8.200.000" atau "8,200,000" -> 8200000.
     * Mencegah subtotal salah karena PHP (float) "8.200.000" = 8.2.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_amount' => $this->parseRupiah($this->input('discount_amount')),
            'tax_amount' => $this->parseRupiah($this->input('tax_amount')),
        ]);

        $items = $this->input('items', []);
        foreach ($items as $i => $item) {
            if (isset($item['price'])) {
                $items[$i]['price'] = $this->parseRupiah($item['price']);
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

        $tradeIns = $this->input('trade_ins', []);
        foreach ($tradeIns as $i => $t) {
            if (isset($t['trade_in_value'])) {
                $tradeIns[$i]['trade_in_value'] = $this->parseRupiah($t['trade_in_value']);
            }
        }
        $this->merge(['trade_ins' => $tradeIns]);
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
            'branch_id' => ['required', 'exists:branches,id'],
            'sale_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['open', 'released'])],

            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_new_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_new_phone' => ['nullable', 'string', 'max:30'],
            'customer_new_address' => ['nullable', 'string'],

            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.serial_numbers' => ['nullable', 'array'],
            'items.*.serial_numbers.*' => ['string', 'distinct'],

            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],

            'trade_ins' => ['nullable', 'array'],
            'trade_ins.*.sku' => ['nullable', 'string', 'max:100'],
            'trade_ins.*.serial_number' => ['nullable', 'string', 'max:100'],
            'trade_ins.*.brand' => ['nullable', 'string', 'max:255'],
            'trade_ins.*.series' => ['nullable', 'string', 'max:255'],
            'trade_ins.*.specs' => ['nullable', 'string'],
            'trade_ins.*.category_id' => ['nullable', 'exists:categories,id'],
            'trade_ins.*.trade_in_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
