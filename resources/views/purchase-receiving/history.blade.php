<x-app-layout>
    <x-slot name="title">{{ __('Riwayat Pengecekan') }} — {{ $purchase->invoice_number }}</x-slot>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Riwayat Pengecekan') }}</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchase-receiving.show', $purchase) }}" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Cek Barang') }}</a>
                <a href="{{ route('purchase-receiving.index', ['tab' => 'riwayat']) }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Kembali') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-slate-500">{{ __('No. Invoice') }}</p>
                    <p class="font-semibold text-slate-800">{{ $purchase->invoice_number }}</p>
                </div>
                <div>
                    <p class="text-slate-500">{{ __('Distributor') }}</p>
                    <p class="font-semibold text-slate-800">{{ $purchase->distributor?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-slate-500">{{ __('Lokasi') }}</p>
                    <p class="font-semibold text-slate-800">
                        @if ($purchase->warehouse_id)
                            {{ __('Gudang') }}: {{ $purchase->warehouse?->name ?? '-' }}
                        @else
                            {{ __('Cabang') }}: {{ $purchase->branch?->name ?? '-' }}
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-slate-500">{{ __('Tanggal Pembelian') }}</p>
                    <p class="font-semibold text-slate-800">{{ $purchase->purchase_date?->format('d/m/Y') ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">{{ __('Detail Riwayat Cek per Unit / Qty') }}</h3>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu Cek') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Barang') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial / Qty') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Hasil') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dicek Oleh') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($logs as $log)
                            @php
                                $productLabel = $log->purchaseDetail?->product
                                    ? trim(($log->purchaseDetail->product->sku ?? '').' '.($log->purchaseDetail->product->brand ?? '').' '.($log->purchaseDetail->product->series ?? ''))
                                    : '-';
                            @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 text-sm">
                                    {{ $log->checked_at?->format('d/m/Y H:i') ?? '-' }}
                                    @if ($log->previous_result)
                                        <div class="text-xs text-slate-500">{{ __('Diubah dari') }}: {{ $log->previous_result }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $productLabel }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($log->productUnit)
                                        <span class="font-mono text-xs">{{ $log->productUnit->serial_number }}</span>
                                    @else
                                        {{ __('Qty') }}: {{ $log->qty }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $log->result === 'baik' ? 'bg-emerald-100 text-emerald-700' : ($log->result === 'reture' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $log->resultLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $log->checker?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $log->notes ?: '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('purchase-receiving.logs.edit', $log) }}" class="inline-flex px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 text-xs font-medium hover:bg-slate-200">
                                        {{ __('Update') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada riwayat pengecekan untuk invoice ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
