<x-app-layout>
    <x-slot name="title">{{ __('Tambah Dana Keluar') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Catat Dana Keluar') }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6" x-data="cashOutForm">
                <form method="POST" action="{{ route('cash-flows.out.store') }}" class="space-y-5">
                    @csrf

                    {{-- Shared: Cabang / Sumber Dana / Tanggal --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if (auth()->user()->isSuperAdmin() || !auth()->user()->branch_id)
                        <div>
                            <x-input-label for="branch_id" :value="__('Cabang')" />
                            <select id="branch_id" name="branch_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="branchId" x-on:change="loadFormDataForBranch()" required>
                                <option value="">{{ __('Pilih Cabang') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                        @else
                            <input type="hidden" id="branch_id" name="branch_id" value="{{ auth()->user()->branch_id }}">
                        @endif

                        <div>
                            <x-input-label for="payment_method_id" :value="__('Sumber Dana')" />
                            <select id="payment_method_id" name="payment_method_id" x-model="paymentMethodId" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">{{ __('Pilih Sumber Dana') }}</option>
                            </select>
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
                            <h3 class="text-sm font-semibold text-slate-700">{{ __('Daftar Pengeluaran') }}</h3>
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
                                                <select :name="'items[' + index + '][expense_category_id]'" x-model="item.expense_category_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                                    <option value="">{{ __('Pilih') }}</option>
                                                    @foreach ($expenseCategories as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
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
                        <a href="{{ route('cash-flows.out.index') }}" class="inline-flex items-center px-4 py-2.5 bg-gray-200 border border-transparent rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-300">
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
                branchId: '{{ old('branch_id', auth()->user()->isSuperAdmin() ? '' : auth()->user()->branch_id) }}',
                paymentMethodId: '{{ old('payment_method_id', '') }}',
                saldoMapBranch: @js($saldoMapBranch ?? []),
                formDataUrl: @json($formDataUrl),
                appBase: @json($appBase),
                nextItemId: 2,
                items: [{
                    id: 1,
                    expense_category_id: '',
                    name: '',
                    amount: '',
                    amountDisplay: ''
                }],
                get saldo() {
                    if (!this.branchId || !this.paymentMethodId) return null;
                    const branch = this.saldoMapBranch[this.branchId];
                    if (!branch) return null;
                    return branch[this.paymentMethodId] ?? 0;
                },
                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
                },
                get canSubmit() {
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
                        expense_category_id: '',
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
                async loadFormDataForBranch() {
                    const branchId = this.branchId || document.getElementById('branch_id')?.value;
                    if (!branchId) return;
                    const pmSelect = document.getElementById('payment_method_id');
                    if (!pmSelect) return;
                    try {
                        const url = new URL(this.appBase + this.formDataUrl, window.location.origin);
                        url.searchParams.set('location_type', 'branch');
                        url.searchParams.set('location_id', branchId);
                        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Fetch failed');
                        const data = await res.json();
                        const methods = data.payment_methods || [];
                        const saldoPerPm = data.saldo_per_pm || {};
                        this.saldoMapBranch = { ...this.saldoMapBranch, [branchId]: saldoPerPm };
                        const oldVal = pmSelect.value;
                        pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>' +
                            methods.map(m => {
                                const saldo = saldoPerPm[m.id] !== undefined ? Number(saldoPerPm[m.id]) : 0;
                                return '<option value="' + m.id + '">' + (m.label || '') + ' (Saldo: ' + Number(saldo).toLocaleString('id-ID') + ')</option>';
                            }).join('');
                        if (oldVal && methods.some(m => m.id == oldVal)) pmSelect.value = oldVal;
                        this.paymentMethodId = pmSelect.value || '';
                    } catch (e) { console.error('loadFormDataForBranch failed', e); }
                },
                init() {
                    @if(old('items'))
                    const oldItems = @json(old('items'));
                    if (Array.isArray(oldItems) && oldItems.length > 0) {
                        this.items = oldItems.map((item, i) => ({
                            id: i + 1,
                            expense_category_id: item.expense_category_id || '',
                            name: item.name || '',
                            amount: item.amount || '',
                            amountDisplay: item.amount ? this.formatRupiah(parseInt(item.amount)) : ''
                        }));
                        this.nextItemId = this.items.length + 1;
                    }
                    @endif
                    if (this.branchId) this.$nextTick(() => this.loadFormDataForBranch());
                }
            }));
        });
    </script>
</x-app-layout>
