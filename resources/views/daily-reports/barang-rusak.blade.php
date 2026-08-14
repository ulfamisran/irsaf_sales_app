<x-app-layout>
    <x-slot name="title">{{ __('Laporan Barang Rusak') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Barang Rusak') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @include('daily-reports.partials.date-filter', ['action' => route('daily-reports.barang-rusak'), 'date' => $date])

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <p class="text-sm text-slate-600">
                    {{ __('Tanggal') }}: <span class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span>
                    — {{ $rows->count() }} {{ __('data') }}
                </p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu Input') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('HPP') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $dg)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">{{ $dg->recorded_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $dg->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    {{ ($dg->productUnit?->product?->sku ?? '') . ' - ' . ($dg->productUnit?->product?->brand ?? '') . ' ' . ($dg->productUnit?->product?->series ?? '') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $dg->serial_number }}</td>
                                <td class="px-4 py-3">
                                    @if ($dg->productUnit?->location_type === 'warehouse')
                                        {{ __('Gudang') }}: {{ $dg->productUnit?->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $dg->productUnit?->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm max-w-xs" title="{{ $dg->damage_description }}">{{ \Illuminate\Support\Str::limit($dg->damage_description, 60) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600">{{ number_format((float) $dg->harga_hpp, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $dg->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($dg->isReactivated())
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-700">{{ __('Diaktifkan Kembali') }}</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-rose-100 text-rose-800">{{ __('Dicadangkan') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => route('damaged-goods.show', $dg)])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada pencatatan barang rusak pada tanggal ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
