<x-app-layout>
    <x-slot name="title">{{ __('Edit Servis') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Service') }}: {{ $service->invoice_number }}</h2>
            <x-icon-btn-back :href="route('services.show', $service)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('services.update', $service) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="branch_id" value="{{ $service->branch_id }}">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="branch_display" :value="__('Cabang')" />
                                    <x-text-input id="branch_display" class="block mt-1 w-full bg-slate-100" type="text" :value="$service->branch?->name" disabled />
                                </div>
                                <div>
                                    <x-input-label for="entry_date" :value="__('Tanggal Masuk')" />
                                    <x-text-input id="entry_date" class="block mt-1 w-full" type="date" name="entry_date" :value="old('entry_date', $service->entry_date->toDateString())" required />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300">
                                    <option value="">{{ __('Pilih Pelanggan') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id', $service->customer_id) == $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}{{ $c->phone ? ' - '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="new-customer-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="customer_new_name" :value="__('Nama Pelanggan Baru')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_phone" :value="__('No. HP')" />
                                    <x-text-input id="customer_new_phone" class="block mt-1 w-full" type="text" name="customer_new_phone" :value="old('customer_new_phone')" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_address" :value="__('Alamat')" />
                                    <x-text-input id="customer_new_address" class="block mt-1 w-full" type="text" name="customer_new_address" :value="old('customer_new_address')" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                    <x-text-input id="laptop_type" class="block mt-1 w-full" type="text" name="laptop_type" :value="old('laptop_type', $service->laptop_type)" required />
                                </div>
                                <div>
                                    <x-input-label for="laptop_detail" :value="__('Detail Laptop')" />
                                    <x-text-input id="laptop_detail" class="block mt-1 w-full" type="text" name="laptop_detail" :value="old('laptop_detail', $service->laptop_detail)" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="damage_description" :value="__('Kerusakan')" />
                                <textarea id="damage_description" name="damage_description" class="block mt-1 w-full rounded-md border-gray-300" rows="3">{{ old('damage_description', $service->damage_description) }}</textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="service_cost" :value="__('Biaya Service (HPP)')" />
                                    <x-text-input id="service_cost" class="block mt-1 w-full" type="text" name="service_cost" data-rupiah="true" :value="old('service_cost', $service->service_cost)" required />
                                </div>
                                <div>
                                    <x-input-label for="service_price" :value="__('Harga Service')" />
                                    <x-text-input id="service_price" class="block mt-1 w-full" type="text" name="service_price" data-rupiah="true" :value="old('service_price', $service->service_price)" required />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Keterangan')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300" rows="2">{{ old('description', $service->description) }}</textarea>
                            </div>

                            <div class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold">{{ __('Pembayaran DP') }}</p>
                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <button type="button" id="add-payment" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                <div class="mt-3 text-sm">
                                    <span>{{ __('Total') }}: </span><span id="paymentSumText">0</span>
                                    <span class="text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Update Service') }}</x-primary-button>
                                <a href="{{ route('services.show', $service) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 rounded-md">Batal</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $editPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
        $editOldPayments = old('payments', ($service->payments ?? collect())->map(fn ($p) => ['payment_method_id' => $p->payment_method_id, 'amount' => (float)$p->amount, 'notes' => $p->notes])->toArray());
    @endphp
    <script>
        const editPaymentMethods = @json($editPaymentMethods);
        const editOldPayments = @json($editOldPayments);
        const paymentRows = document.getElementById('payment-rows');
        let paymentIdx = 0;
        function paymentOpts() {
            return '<option value="">Pilih</option>' + editPaymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }
        function addPayRow(p = {}) {
            const i = paymentIdx++;
            const div = document.createElement('div');
            div.className = 'flex gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1"><select name="payments[${i}][payment_method_id]" class="block w-full rounded-md border-gray-300" required>${paymentOpts()}</select></div>
                <div class="w-40"><input type="number" name="payments[${i}][amount]" step="0.01" min="0.01" class="block w-full rounded-md border-gray-300" placeholder="Nominal" required></div>
                <div class="flex-1"><input type="text" name="payments[${i}][notes]" class="block w-full rounded-md border-gray-300" placeholder="Catatan"></div>
                <button type="button" class="remove-pay px-3 py-2 bg-red-100 text-red-700 rounded">-</button>
            `;
            paymentRows.appendChild(div);
            if (p.payment_method_id) div.querySelector('select').value = p.payment_method_id;
            if (p.amount) div.querySelector('input[name*="[amount]"]').value = p.amount;
            if (p.notes) div.querySelector('input[name*="[notes]"]').value = p.notes || '';
            div.querySelector('.remove-pay')?.addEventListener('click', () => { div.remove(); refresh(); });
            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refresh));
        }
        function toNumber(val) {
            if (typeof window.parseRupiahToNumber === 'function') {
                return window.parseRupiahToNumber(val);
            }
            const raw = String(val ?? '').replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }

        function refresh() {
            const price = toNumber(document.getElementById('service_price')?.value || 0) || 0;
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => sum += toNumber(inp.value || 0));
            document.getElementById('paymentSumText').textContent = sum.toLocaleString('id-ID');
            document.getElementById('paymentDiffText').textContent = (price - sum).toLocaleString('id-ID');
        }
        document.getElementById('add-payment')?.addEventListener('click', () => addPayRow());
        document.getElementById('service_price')?.addEventListener('input', refresh);
        if (Array.isArray(editOldPayments) && editOldPayments.length > 0) {
            editOldPayments.forEach(p => addPayRow(p));
        } else {
            addPayRow();
        }
        refresh();
        document.getElementById('customer_id')?.addEventListener('change', function() {
            document.getElementById('new-customer-fields').style.display = this.value ? 'none' : '';
        });
        document.getElementById('new-customer-fields').style.display = document.getElementById('customer_id')?.value ? 'none' : '';
    </script>
</x-app-layout>
