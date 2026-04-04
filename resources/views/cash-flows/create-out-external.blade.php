<x-app-layout>
    <x-slot name="title">{{ __('Tambah Dana Keluar Eksternal') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Catat Dana Keluar Eksternal') }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6" x-data="cashOutForm">
                <form method="POST" action="{{ route('cash-flows.out.external.store') }}" class="space-y-5">
                    @csrf

                    @php
                        $canChooseLocation = auth()->user()->isSuperAdminOrAdminPusat();
                        $lockedToBranch = auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]) && auth()->user()->branch_id;
                        $lockedToWarehouse = auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_GUDANG]) && auth()->user()->warehouse_id;
                        $defaultLocationType = old('location_type', $lockedToWarehouse ? 'warehouse' : 'branch');
                    @endphp

                    @if ($canChooseLocation)
                        <div class="space-y-4">
                            <div>
                                <x-input-label :value="__('Tipe Lokasi')" />
                                <div class="mt-2 flex flex-wrap gap-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="location_type" value="branch" x-model="locationType" x-on:change="onLocationTypeChange()"
                                            {{ $defaultLocationType === 'branch' ? 'checked' : '' }}>
                                        <span class="ml-2">{{ __('Cabang') }}</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="location_type" value="warehouse" x-model="locationType" x-on:change="onLocationTypeChange()"
                                            {{ $defaultLocationType === 'warehouse' ? 'checked' : '' }}>
                                        <span class="ml-2">{{ __('Gudang') }}</span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('location_type')" class="mt-2" />
                            </div>
                            <div x-show="locationType === 'branch'" x-transition>
                                <x-input-label for="branch_id" :value="__('Cabang')" />
                                <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="branchId" x-on:change="loadFormData()"
                                    :disabled="locationType !== 'branch'">
                                    <option value="">{{ __('Pilih Cabang') }}</option>
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                            </div>
                            <div x-show="locationType === 'warehouse'" x-transition>
                                <x-input-label for="warehouse_id" :value="__('Gudang')" />
                                <select id="warehouse_id" name="warehouse_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    x-model="warehouseId" x-on:change="loadFormData()"
                                    :disabled="locationType !== 'warehouse'">
                                    <option value="">{{ __('Pilih Gudang') }}</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                            </div>
                        </div>
                    @elseif ($lockedToBranch)
                        <input type="hidden" name="location_type" value="branch">
                        <input type="hidden" id="branch_id" name="branch_id" value="{{ auth()->user()->branch_id }}">
                        <input type="hidden" name="warehouse_id" value="">
                        <div class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                            {{ __('Lokasi') }}: {{ __('Cabang') }} — {{ \App\Models\Branch::find(auth()->user()->branch_id)?->name ?? '-' }}
                        </div>
                    @elseif ($lockedToWarehouse)
                        <input type="hidden" name="location_type" value="warehouse">
                        <input type="hidden" name="branch_id" value="">
                        <input type="hidden" id="warehouse_id" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                        <div class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
                            {{ __('Lokasi') }}: {{ __('Gudang') }} — {{ \App\Models\Warehouse::find(auth()->user()->warehouse_id)?->name ?? '-' }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="payment_method_id" :value="__('Sumber Dana')" />
                            <select id="payment_method_id" name="payment_method_id" x-model="paymentMethodId" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">{{ __('Pilih Sumber Dana') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Metode pembayaran/kas mengikuti lokasi cabang atau gudang yang dipilih.') }}</p>
                            <div x-show="saldo !== null" class="mt-2 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2">
                                <span class="text-sm font-medium text-emerald-800">{{ __('Saldo:') }}</span>
                                <span class="text-sm font-bold text-emerald-900" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(saldo ?? 0)"></span>
                            </div>
                            <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transaction_date" :value="__('Tanggal')" />
                            <x-text-input id="transaction_date" class="block mt-1 w-full" type="date" name="transaction_date" :value="old('transaction_date', date('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                        </div>
                    </div>

                    {{-- Error for items --}}
                    @if ($errors->has('items'))
                        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-red-800 text-sm">{{ $errors->first('items') }}</div>
                    @endif

                    {{-- Dynamic Items --}}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-slate-700">{{ __('Daftar Pengeluaran Eksternal') }}</h3>
                            <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                {{ __('Tambah Item') }}
                            </button>
                        </div>

                        <div class="divide-y divide-gray-100">
                            <template x-for="(item, index) in items" :key="item.id">
                                <div class="p-4 hover:bg-slate-50/50 transition-colors">
                                    <div class="flex items-start gap-3">
                                        <span class="flex-shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold mt-6" x-text="index + 1"></span>

                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-3">
                                            <div class="md:col-span-3">
                                                <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Jenis Pengeluaran') }}</label>
                                                <div class="w-full rounded-md border border-gray-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
                                                    {{ $externalExpenseCategory->name }}
                                                </div>
                                                <input type="hidden" :name="'items[' + index + '][expense_category_id]'" :value="item.expense_category_id">
                                            </div>

                                            <div class="md:col-span-5">
                                                <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Nama Pengeluaran') }}</label>
                                                <input type="text" :name="'items[' + index + '][name]'" x-model="item.name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="{{ __('Contoh: Beli ATK, Bayar Listrik, dll') }}" required>
                                            </div>

                                            <div class="md:col-span-3">
                                                <label class="block text-xs font-medium text-slate-600 mb-1">{{ __('Nominal') }}</label>
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-500 text-xs">Rp</span>
                                                    <input type="text" x-model="item.amountDisplay" @input="onAmountInput(index)" class="block w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0" inputmode="numeric" required>
                                                    <input type="hidden" :name="'items[' + index + '][amount]'" :value="item.amount">
                                                </div>
                                            </div>

                                            <div class="md:col-span-1 flex items-end justify-center pb-1">
                                                <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="w-8 h-8 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 flex items-center justify-center transition" title="{{ __('Hapus') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Grand Total --}}
                        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-700">{{ __('Total Pengeluaran') }}</span>
                            <span class="text-lg font-bold text-red-600" x-text="'Rp ' + formatRupiah(grandTotal)"></span>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition" :disabled="!canSubmit">
                            {{ __('Simpan') }}
                        </button>
                        <a href="{{ route('cash-flows.out.external.index') }}" class="inline-flex items-center px-4 py-2.5 bg-gray-200 border border-transparent rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-300">
                            {{ __('Batal') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $formDataUrl = route('data-by-location.form-data', [], false);
        $appBase = request()->getBaseUrl();
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cashOutForm', () => ({
                canChooseLocation: @json($canChooseLocation ?? false),
                locationType: '{{ old('location_type', $defaultLocationType ?? 'branch') }}',
                branchId: '{{ old('branch_id', ($lockedToBranch ?? false) ? auth()->user()->branch_id : '') }}',
                warehouseId: '{{ old('warehouse_id', ($lockedToWarehouse ?? false) ? auth()->user()->warehouse_id : '') }}',
                paymentMethodId: '{{ old('payment_method_id', '') }}',
                saldoMapBranch: @js($saldoMapBranch ?? []),
                saldoMapWarehouse: @js($saldoMapWarehouse ?? []),
                formDataUrl: @json($formDataUrl),
                appBase: @json($appBase),
                externalExpenseCategoryId: {{ (int) $externalExpenseCategory->id }},
                nextItemId: 2,
                items: [{
                    id: 1,
                    expense_category_id: {{ (int) $externalExpenseCategory->id }},
                    name: '',
                    amount: '',
                    amountDisplay: ''
                }],
                get saldo() {
                    if (!this.paymentMethodId) return null;
                    if (this.canChooseLocation) {
                        if (this.locationType === 'branch') {
                            if (!this.branchId) return null;
                            const b = this.saldoMapBranch[this.branchId];
                            return b ? (b[this.paymentMethodId] ?? 0) : null;
                        }
                        if (!this.warehouseId) return null;
                        const w = this.saldoMapWarehouse[this.warehouseId];
                        return w ? (w[this.paymentMethodId] ?? 0) : null;
                    }
                    if (this.branchId) {
                        const b = this.saldoMapBranch[this.branchId];
                        return b ? (b[this.paymentMethodId] ?? 0) : null;
                    }
                    if (this.warehouseId) {
                        const w = this.saldoMapWarehouse[this.warehouseId];
                        return w ? (w[this.paymentMethodId] ?? 0) : null;
                    }
                    return null;
                },
                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
                },
                get canSubmit() {
                    if (this.canChooseLocation) {
                        const locOk = this.locationType === 'branch' ? this.branchId : this.warehouseId;
                        if (!locOk) return false;
                    }
                    if (!this.paymentMethodId) return false;
                    if (this.items.length === 0) return false;
                    const allFilled = this.items.every(i => i.expense_category_id && i.name.trim() && parseFloat(i.amount) > 0);
                    if (!allFilled) return false;
                    return true;
                },
                formatRupiah(num) {
                    return Math.floor(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },
                parseRupiah(str) {
                    return parseInt(String(str).replace(/\D/g, '')) || 0;
                },
                addItem() {
                    this.items.push({
                        id: this.nextItemId++,
                        expense_category_id: this.externalExpenseCategoryId,
                        name: '',
                        amount: '',
                        amountDisplay: ''
                    });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                onAmountInput(index) {
                    const raw = this.parseRupiah(this.items[index].amountDisplay);
                    this.items[index].amountDisplay = raw > 0 ? this.formatRupiah(raw) : '';
                    this.items[index].amount = raw > 0 ? raw : '';
                },
                onLocationTypeChange() {
                    if (this.locationType === 'branch') this.warehouseId = '';
                    else this.branchId = '';
                    this.loadFormData();
                },
                async loadFormData() {
                    let locType = this.locationType;
                    let locId = locType === 'branch' ? this.branchId : this.warehouseId;
                    if (!this.canChooseLocation) {
                        locId = this.branchId || document.getElementById('branch_id')?.value || this.warehouseId || document.getElementById('warehouse_id')?.value;
                        locType = this.branchId || document.getElementById('branch_id')?.value ? 'branch' : 'warehouse';
                    }
                    const pmSelect = document.getElementById('payment_method_id');
                    if (!pmSelect) return;
                    if (!locId) {
                        pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>';
                        this.paymentMethodId = '';
                        return;
                    }
                    try {
                        const url = new URL(this.appBase + this.formDataUrl, window.location.origin);
                        url.searchParams.set('location_type', locType);
                        url.searchParams.set('location_id', locId);
                        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Fetch failed');
                        const data = await res.json();
                        const methods = data.payment_methods || [];
                        const saldoPerPm = data.saldo_per_pm || {};
                        if (locType === 'branch') {
                            this.saldoMapBranch = { ...this.saldoMapBranch, [locId]: saldoPerPm };
                        } else {
                            this.saldoMapWarehouse = { ...this.saldoMapWarehouse, [locId]: saldoPerPm };
                        }
                        const oldVal = pmSelect.value;
                        pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>' +
                            methods.map(m => {
                                const saldo = saldoPerPm[m.id] !== undefined ? Number(saldoPerPm[m.id]) : 0;
                                return '<option value="' + m.id + '">' + (m.label || '') + ' (Saldo: ' + Number(saldo).toLocaleString('id-ID') + ')</option>';
                            }).join('');
                        if (oldVal && methods.some(m => m.id == oldVal)) pmSelect.value = oldVal;
                        this.paymentMethodId = pmSelect.value || '';
                    } catch (e) { console.error('loadFormData failed', e); }
                },
                init() {
                    @if(old('items'))
                    const oldItems = @json(old('items'));
                    if (Array.isArray(oldItems) && oldItems.length > 0) {
                        this.items = oldItems.map((item, i) => ({
                            id: i + 1,
                            expense_category_id: this.externalExpenseCategoryId,
                            name: item.name || '',
                            amount: item.amount || '',
                            amountDisplay: item.amount ? this.formatRupiah(parseInt(item.amount)) : ''
                        }));
                        this.nextItemId = this.items.length + 1;
                    }
                    @endif
                    const hasLoc = this.canChooseLocation
                        ? (this.locationType === 'branch' ? this.branchId : this.warehouseId)
                        : (this.branchId || this.warehouseId);
                    if (hasLoc) this.$nextTick(() => this.loadFormData());
                }
            }));
        });
    </script>
</x-app-layout>

