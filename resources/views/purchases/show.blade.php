<x-app-layout>
    <x-slot name="title">{{ __('Detail Pembelian') }} - {{ $purchase->invoice_number }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-3">
                {{ __('Pembelian') }}: {{ $purchase->invoice_number }}
                @if ($purchase->isCancelled())
                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">{{ __('Dibatalkan') }}</span>
                @endif
            </h2>
            <x-icon-btn-back :href="route('purchases.index')" :label="__('Kembali ke Riwayat')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            @endif
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Lokasi') }}</p>
                            <p class="font-medium">
                                @if ($purchase->warehouse_id)
                                    {{ __('Gudang') }}: {{ $purchase->warehouse?->name ?? '-' }}
                                @else
                                    {{ __('Cabang') }}: {{ $purchase->branch?->name ?? '-' }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Jenis Pembelian') }}</p>
                            <p class="font-medium">{{ $purchase->jenis_pembelian ?? __('Pembelian Unit') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Distributor') }}</p>
                            <p class="font-medium">{{ $purchase->distributor?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Tanggal') }}</p>
                            <p class="font-medium">{{ $purchase->purchase_date->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Termin') }}</p>
                            <p class="font-medium">{{ $purchase->termin ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Jatuh Tempo') }}</p>
                            <p class="font-medium">{{ $purchase->due_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Dibuat Oleh') }}</p>
                            <p class="font-medium">{{ $purchase->user?->name ?? '-' }}</p>
                        </div>
                        @if ($purchase->description)
                            <div class="md:col-span-2">
                                <p class="text-sm text-gray-500">{{ __('Deskripsi') }}</p>
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $purchase->description }}</p>
                            </div>
                        @endif
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Produk') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Serial') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $purchase->isDistribusiUnit() ? __('Biaya Distribusi') : __('Harga Beli') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($purchase->details as $d)
                                <tr>
                                    <td class="px-4 py-2">{{ $d->product?->sku }} - {{ $d->product?->brand }} {{ $d->product?->series }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $d->serial_numbers ? Str::limit(str_replace("\n", ', ', $d->serial_numbers), 50) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $d->quantity }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($d->unit_price, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="4" class="px-4 py-2 text-right">{{ __('Total') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($purchase->total, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    @php $sisaBayar = max(0, (float)$purchase->total - (float)($purchase->total_paid ?? 0)); @endphp
                    <div class="mt-6 mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                        <p class="text-sm font-semibold text-gray-800 mb-3">{{ __('Riwayat Pembayaran') }}</p>
                        @if ($purchase->isPaidOff())
                            <p class="text-xs text-emerald-700 font-medium mb-2">{{ __('Lunas') }}</p>
                        @else
                            <div class="mb-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                <p class="text-sm font-semibold text-amber-800">{{ __('Sisa yang harus dibayar') }}: Rp {{ number_format($sisaBayar, 0, ',', '.') }}</p>
                            </div>
                        @endif
                        <div class="space-y-2 text-sm text-gray-700">
                            @forelse ($purchase->payments as $p)
                                <div class="flex justify-between items-center py-1.5 border-b border-slate-100 last:border-0">
                                    <span>
                                        {{ $p->paymentMethod?->display_label ?? '-' }}
                                        <span class="text-slate-500 text-xs">({{ $p->payment_date->format('d/m/Y') }})</span>
                                        @if ($p->user)
                                            <span class="text-slate-400 text-xs">oleh {{ $p->user->name }}</span>
                                        @endif
                                    </span>
                                    <span class="font-medium">Rp {{ number_format($p->amount, 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <p class="text-gray-500 py-2">{{ __('Belum ada pembayaran.') }}</p>
                            @endforelse
                        </div>
                        @if ($purchase->payments->isNotEmpty())
                            <div class="mt-3 pt-3 border-t border-slate-200 flex justify-between text-sm font-semibold">
                                <span>{{ __('Total Dibayar') }}</span>
                                <span>Rp {{ number_format($purchase->payments->sum('amount'), 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>

                    @php $showPaymentForm = !$purchase->isPaidOff() && !$purchase->isCancelled() && request('view') !== 'cancel'; @endphp
                    @if ($showPaymentForm)
                        <div id="payment-form-section" class="mt-6 border rounded-lg p-4 bg-slate-50" x-data="purchasePaymentForm" x-init="init()">
                            <div class="mb-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                <p class="text-sm font-semibold text-amber-800">{{ __('Sisa yang harus dibayar') }}: Rp {{ number_format($sisaBayar, 0, ',', '.') }}</p>
                            </div>
                            <p class="font-semibold text-slate-800 text-sm">{{ __('Tambah Pembayaran') }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ __('Klik tombol di bawah jika ingin menggunakan lebih dari satu metode pembayaran.') }}</p>
                            <form method="POST" action="{{ route('purchases.add-payment', $purchase) }}" class="mt-3" @submit="normalizeAmountsBeforeSubmit()">
                                @csrf
                                <div id="payment-rows" class="space-y-3"></div>
                                <div class="flex flex-wrap items-center gap-2 mt-3">
                                    <button type="button" @click="addPaymentRow()" class="inline-flex items-center gap-1 px-2 py-1.5 rounded border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-100 bg-white">
                                        + {{ __('Tambah Metode Pembayaran') }}
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700">
                                        {{ __('Simpan Pembayaran') }}
                                    </button>
                                    <span class="text-xs text-slate-500" x-show="paymentSum > 0" x-cloak>
                                        <span x-text="'Total: Rp ' + new Intl.NumberFormat('id-ID').format(paymentSum)"></span>
                                        <span class="text-amber-600" x-show="paymentSum > remaining"> (melebihi sisa)</span>
                                    </span>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if (!$purchase->isCancelled() && request('view') === 'cancel' && (auth()->user()->isSuperAdminOrAdminPusat() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_GUDANG])))
                        <div id="cancel-section" class="mt-6 border rounded-lg p-4 bg-red-50 border-red-200">
                            <p class="font-semibold text-slate-800">{{ __('Batalkan / Retur Pembelian') }}</p>
                            <p class="text-xs text-slate-600 mt-1">{{ __('Pembatalan akan mengubah status unit menjadi cancel, produk menjadi nonaktif, dan mengembalikan dana pembayaran sebagai pemasukan (Retur Pembelian).') }}</p>
                            <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" class="mt-4" onsubmit="return confirm('{{ __('Yakin ingin membatalkan pembelian ini?') }}');">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ __('Batalkan Pembelian') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    @php
        $locationType = $purchase->warehouse_id ? 'warehouse' : 'branch';
        $locationId = (int) ($purchase->warehouse_id ?? $purchase->branch_id);
        $remaining = max(0, (float) $purchase->total - (float) ($purchase->total_paid ?? 0));
    @endphp
    <script>
        function parseRupiahId(str) {
            if (!str || typeof str !== 'string') return 0;
            const cleaned = String(str).replace(/\./g, '').replace(',', '.');
            return parseFloat(cleaned) || 0;
        }
        document.addEventListener('alpine:init', () => {
            Alpine.data('purchasePaymentForm', () => ({
                locationType: @json($locationType),
                locationId: @json($locationId),
                remaining: {{ $remaining }},
                paymentMethods: [],
                saldoPerPm: {},
                paymentIdx: 0,
                updateTrigger: 0,
                paymentRowsInitialized: false,
                formDataUrl: @json(route('data-by-location.form-data', [], false)),
                appBase: @json(request()->getBaseUrl()),
                today: @json(now()->toDateString()),
                get paymentSum() {
                    this.updateTrigger;
                    let sum = 0;
                    document.querySelectorAll('.payment-amount-input').forEach(inp => {
                        sum += parseRupiahId(inp.value);
                    });
                    return Math.round(sum * 100) / 100;
                },
                async init() {
                    if (!this.locationId) return;
                    try {
                        const url = new URL(this.appBase + this.formDataUrl, window.location.origin);
                        url.searchParams.set('location_type', this.locationType);
                        url.searchParams.set('location_id', this.locationId);
                        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Fetch failed');
                        const data = await res.json();
                        this.paymentMethods = data.payment_methods || [];
                        this.saldoPerPm = data.saldo_per_pm || {};
                        if (this.paymentRowsInitialized) return;
                        this.paymentRowsInitialized = true;
                        const oldPayments = @json(old('payments', []));
                        const hasValidOld = Array.isArray(oldPayments) && oldPayments.length > 0 && oldPayments.some(p => p && (p.payment_method_id || (p.amount && parseRupiahId(String(p.amount)) > 0)));
                        if (hasValidOld) {
                            oldPayments.forEach(p => this.addPaymentRow(p));
                        } else {
                            this.addPaymentRow();
                        }
                        this.initRupiahInputs();
                    } catch (e) { console.error('loadPaymentMethods failed', e); }
                },
                addPaymentRow(prefill = {}) {
                    const idx = this.paymentIdx++;
                    const saldoMap = this.saldoPerPm;
                    const optionsHtml = '<option value="">' + @json(__('Pilih Sumber Dana')) + '</option>' +
                        this.paymentMethods.map(m => {
                            const saldo = saldoMap[m.id] !== undefined ? Number(saldoMap[m.id]) : 0;
                            const disabled = saldo <= 0 ? ' disabled' : '';
                            const sel = String(prefill.payment_method_id) === String(m.id) ? ' selected' : '';
                            return '<option value="' + m.id + '"' + disabled + sel + '>' + (m.label || '') + ' (Saldo: ' + Number(saldo).toLocaleString('id-ID') + ')</option>';
                        }).join('');
                    let amountVal = prefill.amount || '';
                    if (amountVal) {
                        const num = typeof amountVal === 'number' ? amountVal : parseRupiahId(String(amountVal));
                        amountVal = num > 0 ? new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(num) : '';
                    }
                    const row = document.createElement('div');
                    row.className = 'payment-row flex flex-wrap gap-2 items-end';
                    row.innerHTML = `
                        <div class="min-w-[180px]">
                            <label class="block text-xs font-medium text-slate-600 mb-0.5">${@json(__('Sumber Dana'))}</label>
                            <select name="payments[${idx}][payment_method_id]" class="block w-full rounded border-gray-300 shadow-sm text-sm py-1.5">
                                ${optionsHtml}
                            </select>
                        </div>
                        <div class="w-28">
                            <label class="block text-xs font-medium text-slate-600 mb-0.5">${@json(__('Nominal'))}</label>
                            <input type="text" name="payments[${idx}][amount]" class="payment-amount-input block w-full rounded border-gray-300 shadow-sm text-sm py-1.5" placeholder="0" value="${amountVal}" data-rupiah="true">
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-slate-600 mb-0.5">${@json(__('Tanggal'))}</label>
                            <input type="date" name="payments[${idx}][payment_date]" class="block w-full rounded border-gray-300 shadow-sm text-sm py-1.5" value="${prefill.payment_date || this.today}">
                        </div>
                        <div class="min-w-[120px] flex-1">
                            <label class="block text-xs font-medium text-slate-600 mb-0.5">${@json(__('Catatan'))}</label>
                            <input type="text" name="payments[${idx}][notes]" class="block w-full rounded border-gray-300 shadow-sm text-sm py-1.5" placeholder="Opsional" value="${prefill.notes || ''}">
                        </div>
                        <button type="button" class="remove-payment-row px-2 py-1.5 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs">−</button>
                    `;
                    const container = document.getElementById('payment-rows');
                    container.appendChild(row);
                    const updateRemoveBtns = () => {
                        const rows = container.querySelectorAll('.payment-row');
                        rows.forEach(r => {
                            const btn = r.querySelector('.remove-payment-row');
                            if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
                        });
                    };
                    row.querySelector('.remove-payment-row')?.addEventListener('click', () => {
                        if (container.querySelectorAll('.payment-row').length > 1) {
                            row.remove();
                            updateRemoveBtns();
                        }
                    });
                    updateRemoveBtns();
                    document.querySelectorAll('#payment-rows [data-rupiah="true"]').forEach(inp => {
                        if (inp.dataset.rupiahInit) return;
                        inp.dataset.rupiahInit = '1';
                        const self = this;
                        inp.addEventListener('blur', function() {
                            const num = parseRupiahId(this.value);
                            if (num > 0) this.value = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(num);
                        });
                        inp.addEventListener('focus', function() {
                            const num = parseRupiahId(this.value);
                            this.value = num > 0 ? num.toFixed(2) : '';
                        });
                        inp.addEventListener('input', () => { self.updateTrigger++; });
                    });
                },
                initRupiahInputs() {
                    document.querySelectorAll('#payment-form-section [data-rupiah="true"]').forEach(inp => {
                        if (inp.dataset.rupiahInit) return;
                        inp.dataset.rupiahInit = '1';
                        inp.addEventListener('blur', function() {
                            const num = parseRupiahId(this.value);
                            if (num > 0) this.value = new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(num);
                        });
                        inp.addEventListener('focus', function() {
                            const num = parseRupiahId(this.value);
                            this.value = num > 0 ? num.toFixed(2) : '';
                        });
                    });
                },
                normalizeAmountsBeforeSubmit() {
                    document.querySelectorAll('#payment-rows .payment-amount-input').forEach(inp => {
                        const num = parseRupiahId(inp.value);
                        inp.value = num > 0 ? num.toFixed(2) : '';
                    });
                }
            }));
        });
        document.addEventListener('DOMContentLoaded', () => {
            if (window.location.search.includes('view=cancel')) {
                const el = document.getElementById('cancel-section');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            document.querySelectorAll('[data-rupiah="true"]').forEach(inp => {
                if (inp.dataset.rupiahInit) return;
                inp.dataset.rupiahInit = '1';
                inp.addEventListener('blur', function() {
                    const v = this.value.replace(/\D/g, '');
                    if (v) this.value = new Intl.NumberFormat('id-ID').format(v);
                });
                inp.addEventListener('focus', function() {
                    this.value = this.value.replace(/\D/g, '');
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
