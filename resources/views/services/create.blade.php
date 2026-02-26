<x-app-layout>
    <x-slot name="title">{{ __('Tambah Servis') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Service Laptop Baru') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('services.store') }}" id="service-form">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="branch_id" :value="__('Cabang')" />
                                    <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">{{ __('Pilih Cabang') }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id || (!old('branch_id') && $branches->count() === 1) ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="entry_date" :value="__('Tanggal Masuk')" />
                                    <x-text-input id="entry_date" class="block mt-1 w-full" type="date" name="entry_date" :value="old('entry_date', date('Y-m-d'))" required />
                                    <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="customer_id" :value="__('Pelanggan')" />
                                <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Pilih Pelanggan (atau isi pelanggan baru)') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}{{ $c->phone ? ' - '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                            </div>

                            <div id="new-customer-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="customer_new_name" :value="__('Nama Pelanggan Baru')" />
                                    <x-text-input id="customer_new_name" class="block mt-1 w-full" type="text" name="customer_new_name" :value="old('customer_new_name')" placeholder="Nama pelanggan" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_phone" :value="__('No. HP')" />
                                    <x-text-input id="customer_new_phone" class="block mt-1 w-full" type="text" name="customer_new_phone" :value="old('customer_new_phone')" placeholder="08xxxxxxxxxx" />
                                </div>
                                <div>
                                    <x-input-label for="customer_new_address" :value="__('Alamat')" />
                                    <x-text-input id="customer_new_address" class="block mt-1 w-full" type="text" name="customer_new_address" :value="old('customer_new_address')" placeholder="Alamat" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="laptop_type" :value="__('Jenis Laptop')" />
                                    <x-text-input id="laptop_type" class="block mt-1 w-full" type="text" name="laptop_type" :value="old('laptop_type')" placeholder="Contoh: ASUS ROG, Lenovo ThinkPad" required />
                                    <x-input-error :messages="$errors->get('laptop_type')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="laptop_detail" :value="__('Detail Laptop')" />
                                    <x-text-input id="laptop_detail" class="block mt-1 w-full" type="text" name="laptop_detail" :value="old('laptop_detail')" placeholder="Spesifikasi, serial number, dll" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="damage_description" :value="__('Kerusakan')" />
                                <textarea id="damage_description" name="damage_description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="3" placeholder="Deskripsi kerusakan">{{ old('damage_description') }}</textarea>
                                <x-input-error :messages="$errors->get('damage_description')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="service_cost" :value="__('Biaya Service (HPP)')" />
                                    <x-text-input id="service_cost" class="block mt-1 w-full" type="text" name="service_cost" data-rupiah="true" :value="old('service_cost', 0)" required />
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Biaya/biaya parts untuk perhitungan laba') }}</p>
                                    <x-input-error :messages="$errors->get('service_cost')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="service_price" :value="__('Harga Service')" />
                                    <x-text-input id="service_price" class="block mt-1 w-full" type="text" name="service_price" data-rupiah="true" :value="old('service_price', 0)" required />
                                    <x-input-error :messages="$errors->get('service_price')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="description" :value="__('Keterangan (Opsional)')" />
                                <textarea id="description" name="description" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2">{{ old('description') }}</textarea>
                            </div>

                            <div id="payments-section" class="border rounded-lg p-4 bg-slate-50">
                                <p class="font-semibold text-slate-800">{{ __('Pembayaran DP (Wajib)') }}</p>
                                <p class="text-xs text-amber-700 mt-1">{{ __('Service wajib membayar DP minimal. Boleh kurang dari harga service.') }}</p>
                                <div class="mt-3 flex justify-between items-center">
                                    <button type="button" id="add-payment" class="inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                </div>
                                <div id="payment-rows" class="mt-3 space-y-2"></div>
                                <div class="mt-3 text-sm text-slate-700">
                                    <span>{{ __('Total pembayaran') }}: </span><span id="paymentSumText" class="font-semibold">0</span>
                                    <span class="ml-2 text-slate-500">({{ __('selisih') }} <span id="paymentDiffText">0</span>)</span>
                                </div>
                                <x-input-error :messages="$errors->get('payments')" class="mt-2" />
                            </div>

                            <div class="flex gap-4">
                                <x-primary-button>{{ __('Simpan Service') }}</x-primary-button>
                                <a href="{{ route('services.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">{{ __('Batal') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $createPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
    @endphp
    <script>
        const paymentMethods = @json($createPaymentMethods);
        const paymentRows = document.getElementById('payment-rows');
        let paymentIndex = 0;

        function paymentOptionsHtml() {
            return '<option value="">Pilih metode</option>' + paymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }

        function addPaymentRow(pref = {}) {
            if (!paymentRows) return;
            const idx = paymentIndex++;
            const div = document.createElement('div');
            div.className = 'flex flex-col md:flex-row gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1">
                    <select name="payments[${idx}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        ${paymentOptionsHtml()}
                    </select>
                </div>
                <div class="w-full md:w-48">
                    <input type="text" name="payments[${idx}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal" required>
                </div>
                <div class="w-full md:w-64">
                    <input type="text" name="payments[${idx}][notes]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Catatan (opsional)">
                </div>
                <button type="button" class="remove-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
            `;
            paymentRows.appendChild(div);
            if (pref.payment_method_id) div.querySelector('select').value = String(pref.payment_method_id);
            if (pref.amount) div.querySelector('input[name*="[amount]"]').value = String(pref.amount);
            if (pref.notes) div.querySelector('input[name*="[notes]"]').value = String(pref.notes || '');
            div.querySelectorAll('select,input').forEach(el => el.addEventListener('input', refreshPaymentSum));
            if (window.attachRupiahFormatter) window.attachRupiahFormatter();
            div.querySelector('.remove-payment')?.addEventListener('click', () => { div.remove(); refreshPaymentSum(); });
        }

        function toNumber(val) {
            if (typeof window.parseRupiahToNumber === 'function') {
                return window.parseRupiahToNumber(val);
            }
            const raw = String(val ?? '').replace(/[^\d]/g, '');
            return raw ? parseFloat(raw) : 0;
        }

        function refreshPaymentSum() {
            const price = toNumber(document.getElementById('service_price')?.value || '0') || 0;
            let sum = 0;
            document.querySelectorAll('#payment-rows input[name*="[amount]"]').forEach(inp => {
                const v = toNumber(inp.value || '0');
                if (v > 0) sum += v;
            });
            const sumEl = document.getElementById('paymentSumText');
            const diffEl = document.getElementById('paymentDiffText');
            if (sumEl) sumEl.textContent = Number(sum).toLocaleString('id-ID');
            if (diffEl) diffEl.textContent = Number(price - sum).toLocaleString('id-ID');
        }

        document.getElementById('add-payment')?.addEventListener('click', () => addPaymentRow());
        document.getElementById('service_price')?.addEventListener('input', refreshPaymentSum);

        const oldPayments = @json(old('payments', []));
        if (Array.isArray(oldPayments) && oldPayments.length > 0) {
            oldPayments.forEach(p => addPaymentRow(p));
        } else {
            addPaymentRow();
        }
        refreshPaymentSum();

        const customerSelect = document.getElementById('customer_id');
        const newCustomerFields = document.getElementById('new-customer-fields');
        const toggleCustomerFields = () => {
            const hasCustomer = !!(customerSelect && customerSelect.value);
            if (newCustomerFields) newCustomerFields.style.display = hasCustomer ? 'none' : '';
        };
        customerSelect?.addEventListener('change', toggleCustomerFields);
        toggleCustomerFields();
    </script>
</x-app-layout>
