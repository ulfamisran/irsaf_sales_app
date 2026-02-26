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
                @if ($sale->status === \App\Models\Sale::STATUS_OPEN)
                    <a href="{{ route('sales.edit', $sale) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        {{ __('Edit') }}
                    </a>
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('{{ __('Batalkan penjualan ini? Unit akan kembali IN STOCK. Data tetap tersimpan dengan status Dibatalkan.') }}')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                            {{ __('Batalkan') }}
                        </button>
                    </form>
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
                                @if ($sale->status === 'released')
                                    <span class="ml-1 px-2 py-1 rounded-lg text-xs font-medium {{ $sale->isPaidOff() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $sale->isPaidOff() ? __('Lunas') : __('Belum Lunas') }}
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
                        <div class="mt-6">
                            <p class="text-sm font-semibold text-gray-800">{{ __('Pembayaran') }}</p>
                            @if ($sale->status === 'released' && !$sale->isPaidOff())
                                <p class="text-xs text-amber-700 mt-1">{{ __('Belum lunas') }} - {{ __('Sisa') }}: {{ number_format((float)$sale->total - (float)$sale->total_paid, 0, ',', '.') }}</p>
                            @endif
                            <div class="mt-2 space-y-1 text-sm text-gray-700">
                                @forelse ($sale->payments as $p)
                                    <div class="flex justify-between">
                                        <span>
                                            {{ $p->paymentMethod?->display_label }}
                                        </span>
                                        <span class="font-medium">{{ number_format($p->amount, 0, ',', '.') }}</span>
                                    </div>
                                @empty
                                    @if (!$sale->tradeIns || $sale->tradeIns->isEmpty())
                                        <div class="text-gray-500">{{ __('-') }}</div>
                                    @endif
                                @endforelse
                                @foreach ($sale->tradeIns ?? [] as $ti)
                                    <div class="flex justify-between text-amber-800">
                                        <span>{{ __('Tukar Tambah') }}: {{ $ti->brand ?? '-' }} {{ $ti->series ?? '' }} ({{ $ti->serial_number }})</span>
                                        <span class="font-medium">{{ number_format($ti->trade_in_value, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
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
                                        <input type="number" name="payments[${i}][amount]" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nominal">
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
