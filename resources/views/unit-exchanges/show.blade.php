<x-app-layout>
    <x-slot name="title">{{ $exchange->exchange_number }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $exchange->exchange_number }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ __('Bukti tukar unit') }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                <x-action-btn variant="report" :href="route('sales.unit-exchanges.report', $exchange)" target="_blank" />
                @if ($exchange->status === \App\Models\UnitExchange::STATUS_OPEN && ! app(\App\Services\AuthorizationService::class)->blocksMutation($exchange))
                    <x-action-btn variant="edit" :href="route('sales.unit-exchanges.edit', $exchange)" />
                @endif
                <x-icon-btn-back :href="route('sales.unit-exchanges.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <x-authorization-actions :model="$exchange" module="unit-exchanges" :release-route="route('sales.unit-exchanges.release', $exchange)" />
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <x-action-btn variant="report" :href="route('sales.unit-exchanges.report', $exchange)" target="_blank" />
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('No. Tukar Unit') }}</p>
                    <p class="font-medium text-slate-800">{{ $exchange->exchange_number }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Status') }}</p>
                    <p class="font-medium {{ $exchange->status === \App\Models\UnitExchange::STATUS_CANCEL ? 'text-red-700' : ($exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? 'text-amber-700' : 'text-emerald-700') }}">{{ $exchange->status === \App\Models\UnitExchange::STATUS_CANCEL ? __('Dibatalkan') : ($exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? __('Open') : __('Release')) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tanggal Tukar') }}</p>
                    <p class="font-medium text-slate-800">{{ $exchange->exchange_date?->timezone(\App\Support\CatatRange::TZ)->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Tanggal Catat') }}</p>
                    <p class="font-medium text-slate-800"><x-wita-datetime :value="$exchange->created_at" /></p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('User') }}</p>
                    <p class="font-medium text-slate-800">{{ $exchange->user?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Invoice Penjualan') }}</p>
                    <p class="font-medium text-slate-800">
                        @if ($exchange->sale)
                            <a href="{{ route('sales.show', $exchange->sale) }}" class="text-indigo-600 hover:text-indigo-800">{{ $exchange->sale->invoice_number }}</a>
                        @else
                            -
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('Pelanggan') }}</p>
                    <p class="font-medium text-slate-800">{{ $exchange->sale?->customer?->name ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('SKU') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Lama') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial Pengganti') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga Unit') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Selisih') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Kas') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($exchange->items as $item)
                            @php
                                $difference = (float) $item->price_difference;
                                $productName = trim((string) ($item->product?->brand.' '.($item->product?->series ?? '')));
                            @endphp
                            <tr>
                                <td class="px-4 py-3">{{ $item->product?->sku ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $productName !== '' ? $productName : '-' }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $item->old_serial }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $item->new_serial }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $difference < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format($difference, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($difference > 0)
                                        {{ __('Pemasukan') }} · {{ $item->paymentMethod?->display_label ?? '-' }}
                                    @elseif ($difference < 0)
                                        {{ __('Pengembalian dana') }} · {{ $item->paymentMethod?->display_label ?? '-' }}
                                    @else
                                        {{ __('Tidak ada selisih') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            <x-transaction-proof-fields
                :require-one="true"
                :slots="$exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? max(0, 2 - $exchange->proofs->count()) : 0"
                :existing="$exchange->proofs"
                :owner="$exchange"
                :upload-url="$exchange->status === \App\Models\UnitExchange::STATUS_OPEN ? route('sales.unit-exchanges.proofs.store', $exchange) : null"
                :can-delete="$exchange->status === \App\Models\UnitExchange::STATUS_OPEN"
                :hint="__('Wajib 1 file gambar atau PDF saat Release. File kedua opsional.')"
            />
        </div>
    </div>
</x-app-layout>
