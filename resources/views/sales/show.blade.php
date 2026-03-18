<x-app-layout>
    <x-slot name="title">{{ __('Detail Penjualan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Sale') }}: {{ $sale->invoice_number }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('sales.invoice', $sale) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Print Invoice') }}
                </a>
                @if (auth()->user()?->isSuperAdmin())
                        <a href="{{ route('sales.edit', $sale) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ __('Edit') }}
                        </a>
                @endif
                <x-icon-btn-back :href="route('sales.index')" :label="__('Kembali ke Penjualan')" />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 flex items-center gap-3 shadow-sm">
                    <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 shadow-sm">
                    <p class="font-semibold mb-2">{{ __('Terdapat kesalahan validasi:') }}</p>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('success'))
                <div id="sale-success-alert" class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 flex items-center gap-3 shadow-sm" data-success-message="{{ e(session('success')) }}">
                    <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var el = document.getElementById('sale-success-alert');
                        if (el && el.dataset.successMessage) {
                            alert(el.dataset.successMessage);
                        }
                    });
                </script>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($sale->status === \App\Models\Sale::STATUS_CANCEL)
                        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50/50 p-4">
                            <p class="text-sm font-semibold text-rose-700">{{ __('Informasi Pembatalan') }}</p>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-700">
                                <div>
                                    <p class="text-xs text-slate-500">{{ __('Tanggal Batal') }}</p>
                                    <p class="font-medium">{{ $sale->cancel_date?->format('d/m/Y') ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">{{ __('Dibatalkan Oleh') }}</p>
                                    <p class="font-medium">{{ $sale->cancelUser?->name ?? '-' }}</p>
                                </div>
                                <div class="md:col-span-1">
                                    <p class="text-xs text-slate-500">{{ __('Alasan Batal') }}</p>
                                    <p class="font-medium whitespace-pre-line">{{ $sale->cancel_reason ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Branch') }}</p>
                            <p class="font-medium">{{ $sale->branch?->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Status') }}</p>
                            <p class="font-medium">
                                <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $sale->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($sale->status === 'cancel' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $sale->status === 'cancel' ? __('Dibatalkan') : $sale->status }}
                                </span>
                                @if (in_array($sale->status, [\App\Models\Sale::STATUS_OPEN, 'released', 'cancel'], true))
                                    @php
                                        $isLunas = $sale->status !== \App\Models\Sale::STATUS_OPEN && $sale->isPaidOff();
                                    @endphp
                                    <span class="ml-1 px-2 py-1 rounded-lg text-xs font-medium {{ $isLunas ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $isLunas ? __('Lunas') : __('Belum Lunas') }}
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Date') }}</p>
                            <p class="font-medium">{{ $sale->sale_date->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">{{ __('Cashier') }}</p>
                            <p class="font-medium">{{ $sale->user?->name }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500">{{ __('Customer') }}</p>
                            <p class="font-medium">{{ $sale->customer?->name ?? '-' }}</p>
                            @if ($sale->customer?->phone)
                                <p class="text-sm text-gray-600">{{ $sale->customer->phone }}</p>
                            @endif
                        </div>
                        @if ($sale->description)
                            <div class="md:col-span-2">
                                <p class="text-sm text-gray-500">{{ __('Deskripsi') }}</p>
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sale->description }}</p>
                            </div>
                        @endif
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Serial') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Price') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($sale->saleDetails as $detail)
                                <tr>
                                    <td class="px-4 py-2">{{ $detail->product?->sku }} - {{ $detail->product?->brand }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $detail->serial_numbers ? \Illuminate\Support\Str::limit(str_replace("\n", ', ', $detail->serial_numbers), 40) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $detail->quantity }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($detail->price, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($detail->quantity * $detail->price, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $sub = $sale->saleDetails->sum(fn($d) => $d->quantity * $d->price);
                            @endphp
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-sm text-gray-600">{{ __('Subtotal') }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ number_format($sub, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-sm text-gray-600">{{ __('Diskon') }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ number_format($sale->discount_amount ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-sm text-gray-600">{{ __('Pajak') }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ number_format($sale->tax_amount ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td colspan="4" class="px-4 py-2 text-right">{{ __('Total') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($sale->total, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    @if ($sale->status === 'released' || $sale->status === 'open')
                        @php
                            $totalPaid = (float) $sale->total_paid;
                            $totalSale = (float) $sale->total;
                            $sisa = max(0, round($totalSale - $totalPaid, 2));
                            $isLunasNow = $sale->isPaidOff();
                        @endphp
                        <div class="mt-6 mb-8">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-gray-800">{{ __('Riwayat Pembayaran') }}</p>
                                @if ($sale->status === 'released')
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $isLunasNow ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $isLunasNow ? __('Lunas') : __('Belum Lunas') }}
                                    </span>
                                @endif
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Metode Pembayaran') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nominal') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php $paymentNo = 1; @endphp
                                        @forelse ($sale->payments as $p)
                                            <tr>
                                                <td class="px-4 py-2 text-slate-600">{{ $paymentNo++ }}</td>
                                                <td class="px-4 py-2">{{ $p->paymentMethod?->display_label ?? '-' }}</td>
                                                <td class="px-4 py-2 text-slate-600">{{ $p->created_at?->format('d/m/Y H:i') }}</td>
                                                <td class="px-4 py-2 text-slate-500 text-sm">{{ $p->notes ?? '-' }}</td>
                                                <td class="px-4 py-2 text-right font-medium">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            @if (!$sale->tradeIns || $sale->tradeIns->isEmpty())
                                                <tr>
                                                    <td colspan="5" class="px-4 py-3 text-center text-slate-500">{{ __('Belum ada pembayaran.') }}</td>
                                                </tr>
                                            @endif
                                        @endforelse
                                        @foreach ($sale->tradeIns ?? [] as $ti)
                                            <tr class="bg-amber-50/50">
                                                <td class="px-4 py-2 text-amber-700">{{ $paymentNo++ }}</td>
                                                <td class="px-4 py-2 text-amber-800">{{ __('Tukar Tambah') }}: {{ $ti->brand ?? '-' }} {{ $ti->series ?? '' }} ({{ $ti->serial_number }})</td>
                                                <td class="px-4 py-2 text-amber-700">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                                <td class="px-4 py-2 text-slate-500">-</td>
                                                <td class="px-4 py-2 text-right font-medium text-amber-800">Rp {{ number_format($ti->trade_in_value, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-slate-50">
                                        <tr class="font-semibold">
                                            <td colspan="3" class="px-4 py-2 text-right text-sm">{{ __('Total Dibayar') }}</td>
                                            <td class="px-4 py-2 text-right text-sm">Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="px-4 py-2 text-right text-sm text-slate-600">{{ __('Total Tagihan') }}</td>
                                            <td class="px-4 py-2 text-right text-sm text-slate-600">Rp {{ number_format($totalSale, 0, ',', '.') }}</td>
                                        </tr>
                                        @if (!$isLunasNow)
                                            <tr class="text-amber-700 font-semibold">
                                                <td colspan="3" class="px-4 py-2 text-right text-sm">{{ __('Sisa Tagihan') }}</td>
                                                <td class="px-4 py-2 text-right text-sm">Rp {{ number_format($sisa, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Tambah Pembayaran form --}}
                            @if ($sale->status === 'released' && !$isLunasNow)
                                <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4">
                                    <p class="font-semibold text-sm text-indigo-800 mb-3">{{ __('Tambah Pembayaran') }}</p>
                                    <form method="POST" action="{{ route('sales.store-payment', $sale) }}">
                                        @csrf
                                        <div class="flex flex-wrap items-end gap-3">
                                            <div class="flex-1 min-w-[150px]">
                                                <x-input-label for="add_payment_method" :value="__('Metode Pembayaran')" class="text-xs" />
                                                <select id="add_payment_method" name="payment_method_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                                    <option value="">{{ __('Pilih Metode') }}</option>
                                                    @foreach ($paymentMethods as $pm)
                                                        <option value="{{ $pm->id }}">{{ $pm->display_label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex-1 min-w-[130px]">
                                                <x-input-label for="add_payment_amount" :value="__('Nominal')" class="text-xs" />
                                                <div class="relative mt-1">
                                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 text-sm">Rp</span>
                                                    <input type="text" id="add_payment_amount_display" class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0" inputmode="numeric" value="{{ old('amount') ? number_format(old('amount'), 0, ',', '.') : number_format($sisa, 0, ',', '.') }}">
                                                    <input type="hidden" name="amount" id="add_payment_amount" value="{{ old('amount', $sisa) }}">
                                                </div>
                                            </div>
                                            <div class="w-36">
                                                <x-input-label for="add_payment_date" :value="__('Tanggal')" class="text-xs" />
                                                <input type="date" id="add_payment_date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </div>
                                            <div class="flex-1 min-w-[120px]">
                                                <x-input-label for="add_payment_notes" :value="__('Catatan')" class="text-xs" />
                                                <input type="text" id="add_payment_notes" name="notes" value="{{ old('notes') }}" placeholder="{{ __('Opsional') }}" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </div>
                                            <div>
                                                <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                    {{ __('Bayar') }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                @push('scripts')
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const maxAmount = {{ $sisa }};
                                    const display = document.getElementById('add_payment_amount_display');
                                    const hidden = document.getElementById('add_payment_amount');

                                    function formatRupiah(num) {
                                        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                    }

                                    display.addEventListener('input', function() {
                                        let raw = parseInt(this.value.replace(/\D/g, '')) || 0;
                                        this.value = raw > 0 ? formatRupiah(raw) : '';
                                        hidden.value = raw > 0 ? raw : '';
                                    });
                                });
                                </script>
                                @endpush
                            @endif
                        </div>
                    @endif

                    @if ($sale->tradeIns && $sale->tradeIns->isNotEmpty())
                        <div class="mt-6">
                            <p class="text-sm font-semibold text-gray-800">{{ __('Barang Tukar Tambah') }}</p>
                            <div class="mt-2 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Brand') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Series') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Specs') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nilai') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($sale->tradeIns as $ti)
                                            <tr>
                                                <td class="px-3 py-2">{{ $ti->sku }}</td>
                                                <td class="px-3 py-2">{{ $ti->brand ?? '-' }}</td>
                                                <td class="px-3 py-2">{{ $ti->series ?? '-' }}</td>
                                                <td class="px-3 py-2 font-mono text-xs">{{ $ti->serial_number ?? '-' }}</td>
                                                <td class="px-3 py-2 text-xs text-slate-600">{{ $ti->specs ?? '-' }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format($ti->trade_in_value, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($sale->status === 'open')
                        <div class="mt-8 border rounded-lg p-4 bg-slate-50">
                            <p class="font-semibold text-slate-800">{{ __('Release Penjualan') }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ __('Saat release, stok akan dikurangi dan penjualan tidak bisa diubah lagi. Pembayaran boleh lunas atau belum lunas (partial).') }}</p>
                            @if ($sale->tradeIns && $sale->tradeIns->isNotEmpty())
                                @php $ttTotal = $sale->tradeIns->sum('trade_in_value'); @endphp
                                <p class="text-xs text-amber-700 mt-1">{{ __('Tukar Tambah') }}: {{ number_format($ttTotal, 0, ',', '.') }} — {{ __('Yang dibayar tunai') }}: {{ number_format((float)$sale->total - $ttTotal, 0, ',', '.') }}</p>
                            @endif

                            <form method="POST" action="{{ route('sales.release', $sale) }}" class="mt-4">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="release_sale_date" :value="__('Tanggal')" />
                                        <x-text-input id="release_sale_date" class="block mt-1 w-full" type="date" name="sale_date" :value="old('sale_date', $sale->sale_date->toDateString())" required />
                                        <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <x-input-label :value="__('Metode Pembayaran')" />
                                    <div id="release-payment-rows" class="space-y-2 mt-2"></div>
                                    <button type="button" id="release-add-payment" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">
                                        + {{ __('Tambah Metode') }}
                                    </button>
                                    <x-input-error :messages="$errors->get('payments')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('payments.*.payment_method_id')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('payments.*.amount')" class="mt-2" />
                                </div>

                                <div class="mt-4 flex gap-3">
                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                        {{ __('Release') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <script>
                            const releasePaymentMethods = @json(($paymentMethods ?? collect())->map(fn($m) => [
                                'id' => $m->id,
                                'label' => $m->display_label,
                            ])->values());
                            @php
                                $ttTotal = $sale->tradeIns ? $sale->tradeIns->sum('trade_in_value') : 0;
                                $cashDue = max(0, (float)$sale->total - $ttTotal);
                            @endphp
                            const totalSale = @json((float) $sale->total);
                            const tradeInTotal = @json((float) $ttTotal);
                            const cashDue = @json((float) $cashDue);

                            function releasePaymentOptionsHtml() {
                                return '<option value="">Pilih metode</option>' + releasePaymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
                            }

                            const rowsEl = document.getElementById('release-payment-rows');
                            let idx = 0;
                            function addReleasePaymentRow(pref = {}) {
                                if (!rowsEl) return;
                                const i = idx++;
                                const div = document.createElement('div');
                                div.className = 'flex flex-col md:flex-row gap-2 items-end';
                                div.innerHTML = `
                                    <div class="flex-1">
                                        <select name="payments[${i}][payment_method_id]" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            ${releasePaymentOptionsHtml()}
                                        </select>
                                    </div>
                                    <div class="w-full md:w-48">
                                        <input type="text" name="payments[${i}][amount]" data-rupiah="true" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal">
                                    </div>
                                    <button type="button" class="remove-release-payment px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200">-</button>
                                `;
                                rowsEl.appendChild(div);
                                if (pref.payment_method_id) {
                                    const sel = div.querySelector('select');
                                    if (sel) sel.value = String(pref.payment_method_id);
                                }
                                if (pref.amount) {
                                    const inp = div.querySelector('input[name*="[amount]"]');
                                    if (inp) inp.value = String(pref.amount);
                                }
                                div.querySelector('.remove-release-payment')?.addEventListener('click', () => div.remove());
                                if (window.attachRupiahFormatter) window.attachRupiahFormatter(div);
                            }

                            document.getElementById('release-add-payment')?.addEventListener('click', () => addReleasePaymentRow());

                            const oldReleasePayments = @json(old('payments', $sale->payments->map(fn($p) => ['payment_method_id' => $p->payment_method_id, 'amount' => (float)$p->amount])->toArray()));
                            if (Array.isArray(oldReleasePayments) && oldReleasePayments.length > 0) {
                                oldReleasePayments.forEach(p => addReleasePaymentRow(p));
                            } else if (cashDue > 0) {
                                addReleasePaymentRow({ amount: cashDue });
                            } else if (tradeInTotal <= 0) {
                                addReleasePaymentRow({ amount: totalSale });
                            }
                        </script>
                    @endif

                    @if (auth()->user()?->isSuperAdmin() && in_array($sale->status, [\App\Models\Sale::STATUS_OPEN, \App\Models\Sale::STATUS_RELEASED], true))
                        <div class="mt-20 border border-red-200 rounded-lg p-4 bg-red-50/40">
                            <p class="font-semibold text-red-700 mb-2">{{ __('Batalkan Transaksi') }}</p>
                            <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('{{ $sale->status === \App\Models\Sale::STATUS_RELEASED ? __('Transaksi sudah RELEASED. Batalkan penjualan ini?') : __('Batalkan penjualan ini? Unit akan kembali IN STOCK. Data tetap tersimpan dengan status Dibatalkan.') }}')">
                                @csrf
                                <div class="flex flex-col gap-2 mb-3">
                                    <textarea name="cancel_reason" class="w-full rounded-md border-gray-300" rows="2" placeholder="{{ __('Alasan pembatalan') }}" required></textarea>
                                    @if ($sale->status === \App\Models\Sale::STATUS_RELEASED)
                                        <label class="flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" name="confirm_released" value="1" class="rounded">
                                            <span>{{ __('Saya yakin membatalkan transaksi released') }}</span>
                                        </label>
                                    @endif
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                                    {{ __('Batalkan') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
