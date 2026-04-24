<?php

namespace App\Http\Requests;

use App\Models\Purchase;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $this->merge(['payments' => []]);
        }
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

    public function messages(): array
    {
        return [
            'location_type.required' => __('Pilih jenis lokasi: Gudang atau Cabang.'),
            'warehouse_id.required_if' => __('Pilih gudang tujuan pembelian.'),
            'warehouse_id.exists' => __('Gudang yang dipilih tidak valid. Pilih gudang dari daftar yang tersedia.'),
            'branch_id.required_if' => __('Pilih cabang tujuan pembelian.'),
            'branch_id.exists' => __('Cabang yang dipilih tidak valid. Pilih cabang dari daftar yang tersedia.'),
            'distributor_id.required' => __('Pilih distributor.'),
            'distributor_id.exists' => __('Distributor yang dipilih tidak ditemukan — pastikan sudah pilih gudang/cabang, lalu pilih distributor yang sesuai.'),
            'items.required' => __('Tambah minimal satu baris barang.'),
            'items.min' => __('Tambah minimal satu baris barang.'),
            'items.*.product_id.required' => __('Pilih produk di setiap baris.'),
            'items.*.product_id.exists' => __('Produk di salah satu baris tidak ditemukan. Pilih gudang/cabang, klik "Muat Ulang Produk", lalu pilih produk ulang agar sesuai lokasi.'),
            'items.*.quantity.required' => __('Isi jumlah (qty) untuk setiap baris barang.'),
            'items.*.unit_price.required' => __('Isi harga beli per unit untuk setiap baris barang.'),
            'invoice_number.unique' => __('Nomor invoice sudah terpakai. Gunakan nomor lain atau biarkan untuk nomor otomatis.'),
        ];
    }

    private function addDuplicateSerialMessages(Validator $validator): void
    {
        $items = $this->input('items', []);
        if (! is_array($items) || $items === []) {
            return;
        }

        $inRowMessages = [];
        $serialToLineNos = [];

        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $raw = $item['serial_numbers'] ?? null;
            if (! is_array($raw)) {
                $raw = [];
            }
            $serials = [];
            foreach ($raw as $s) {
                $t = trim((string) $s);
                if ($t === '') {
                    continue;
                }
                $serials[] = $t;
            }
            if ($serials === []) {
                continue;
            }

            $lineNo = (int) $idx + 1;
            $counts = array_count_values($serials);
            foreach ($counts as $sn => $count) {
                if ($count > 1) {
                    $inRowMessages[] = __(
                        'Serial ":sn" tercatat sebanyak :jumlah kali di baris ke-:n (hapus keterangan ganda; tiap serial hanya boleh satu kali).',
                        ['sn' => $sn, 'jumlah' => $count, 'n' => $lineNo]
                    );
                }
                if (! isset($serialToLineNos[$sn])) {
                    $serialToLineNos[$sn] = [];
                }
                if (! in_array($lineNo, $serialToLineNos[$sn], true)) {
                    $serialToLineNos[$sn][] = $lineNo;
                }
            }
        }

        $crossLineMessages = [];
        foreach ($serialToLineNos as $sn => $lineNos) {
            if (count($lineNos) <= 1) {
                continue;
            }
            sort($lineNos);
            $lineList = array_map('strval', $lineNos);
            $crossLineMessages[] = __(
                'Serial ":sn" muncul di baris: :list (satu serial tidak boleh dipakai di beberapa baris; pakai hanya di satu baris).',
                ['sn' => $sn, 'list' => implode(', ', $lineList)]
            );
        }

        $all = array_values(array_unique([...$inRowMessages, ...$crossLineMessages]));
        if ($all === []) {
            return;
        }

        $validator->errors()->add('items', implode(' ', $all));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->addDuplicateSerialMessages($validator);

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
        $purchase = $this->route('purchase');
        $purchaseId = $purchase instanceof Purchase ? $purchase->id : null;

        return [
            'invoice_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('purchases', 'invoice_number')->ignore($purchaseId),
            ],
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
            'items.*.serial_numbers.*' => ['string'],

            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.notes' => ['nullable', 'string'],
        ];
    }
}
