<?php

namespace App\Http\Requests;

use App\Models\Purchase;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Indonesian thousands format: 2.500.000 or 2.500.000,00
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $str) === 1) {
            $head = explode(',', $str)[0] ?? '';
            $digits = str_replace('.', '', $head);

            return $digits !== '' ? (float) $digits : null;
        }

        // Comma decimal: 2500000,00
        if (preg_match('/^\d+,\d{1,2}$/', $str) === 1) {
            return (float) round((float) str_replace(',', '.', $str));
        }

        // Plain decimal: 2500000.00
        if (preg_match('/^\d+\.\d{1,2}$/', $str) === 1) {
            return (float) round((float) $str);
        }

        // Fallback: keep digits only (safe for Rp/space formatting)
        $digits = preg_replace('/[^\d]/', '', $str);

        return $digits !== '' ? (float) $digits : null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $jenis = (string) $this->input('jenis_pembelian', Purchase::JENIS_PEMBELIAN_UNIT);
            if (in_array($jenis, [
                Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE,
                Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO,
            ], true) && $this->input('location_type') !== 'branch') {
                $validator->errors()->add(
                    'jenis_pembelian',
                    __('Jenis pembelian sparepart service hanya untuk lokasi Cabang.')
                );
            }

            if ($jenis !== Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE) {
                return;
            }
            $branchId = (int) ($this->input('branch_id') ?? 0);
            $serviceId = (int) ($this->input('service_id') ?? 0);
            if ($serviceId <= 0) {
                return;
            }
            $svc = Service::find($serviceId);
            if (! $svc) {
                $validator->errors()->add('service_id', __('Invoice service tidak ditemukan.'));

                return;
            }
            if ($svc->status !== Service::STATUS_OPEN) {
                $validator->errors()->add('service_id', __('Invoice service harus berstatus open.'));
            }
            if ($branchId > 0 && (int) $svc->branch_id !== $branchId) {
                $validator->errors()->add('service_id', __('Invoice service harus dari cabang yang sama dengan lokasi pembelian.'));
            }
        });
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:purchases,invoice_number'],
            'jenis_pembelian' => [
                'required',
                'string',
                'in:'.Purchase::JENIS_PEMBELIAN_UNIT.','.Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE.','.Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE_LAPTOP_TOKO,
            ],
            'service_id' => [
                'nullable',
                'integer',
                'exists:services,id',
                'required_if:jenis_pembelian,'.Purchase::JENIS_PEMBELIAN_SPAREPART_SERVICE,
            ],
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
