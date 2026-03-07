<x-app-layout>
    <x-slot name="title">{{ __('Tambah Dana Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Tambah Pemasukan Lainnya') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6" x-data="cashInForm">
                <form method="POST" action="{{ route('cash-flows.in.store') }}" class="space-y-4">
                    @csrf

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
                        <x-input-label for="income_category_id" :value="__('Kategori Pemasukan')" />
                        <select id="income_category_id" name="income_category_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Pilih Kategori Pemasukan') }}</option>
                            @foreach ($incomeCategories as $cat)
                                <option value="{{ $cat->id }}" {{ old('income_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('income_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="payment_method_id" :value="__('Pilih Kas')" />
                        <select id="payment_method_id" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">{{ __('Pilih Kas') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Metode pembayaran/kas berdasarkan cabang yang dipilih') }}</p>
                        <x-input-error :messages="$errors->get('payment_method_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="transaction_date" :value="__('Tanggal')" />
                        <x-text-input id="transaction_date" class="block mt-1 w-full" type="date" name="transaction_date" :value="old('transaction_date', date('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" :value="__('Jumlah')" />
                        <x-text-input id="amount" class="block mt-1 w-full" type="text" name="amount" data-rupiah="true" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Keterangan / Sumber Pemasukan')" />
                        <textarea id="description" name="description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex gap-3">
                        <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                        <a href="{{ route('cash-flows.in.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
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
            Alpine.data('cashInForm', () => ({
                branchId: '{{ old('branch_id', auth()->user()->isSuperAdmin() ? '' : auth()->user()->branch_id) }}',
                paymentMethodId: '{{ old('payment_method_id', '') }}',
                formDataUrl: @json($formDataUrl),
                appBase: @json($appBase),
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
                        const oldVal = pmSelect.value;
                        pmSelect.innerHTML = '<option value="">' + @json(__('Pilih Kas')) + '</option>' +
                            methods.map(m => '<option value="' + m.id + '">' + (m.label || '') + '</option>').join('');
                        if (oldVal && methods.some(m => m.id == oldVal)) pmSelect.value = oldVal;
                        this.paymentMethodId = pmSelect.value || '';
                    } catch (e) { console.error('loadFormDataForBranch failed', e); }
                },
                init() {
                    if (this.branchId) this.$nextTick(() => this.loadFormDataForBranch());
                }
            }));
        });
    </script>
</x-app-layout>

