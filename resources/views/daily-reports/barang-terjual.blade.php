<x-app-layout>
    <x-slot name="title">{{ __('Laporan Barang Terjual') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Barang Terjual') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @include('daily-reports.partials.date-filter', [
            'action' => route('daily-reports.barang-terjual'),
            'date' => $date,
            'exportExcel' => 'daily-reports.barang-terjual.export',
            'exportPdf' => 'daily-reports.barang-terjual.export-pdf',
        ])

        <div class="card-modern p-6 mb-6">
            <p class="text-sm text-slate-600">{{ __('Total Penjualan') }} ({{ \Carbon\Carbon::parse($date)->format('d/m/Y') }})</p>
            <p class="text-2xl font-bold text-emerald-600">{{ number_format($totalPenjualan ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <p class="text-sm text-slate-600">
                    {{ __('Tanggal') }}: <span class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span>
                    — {{ $rows->count() }} {{ __('item') }}
                </p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit / Serial') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status Lunas') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $row['invoice'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['lokasi'] ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['produk'] }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $row['unit'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['qty'] }}</td>
                                <td class="px-4 py-3">{{ $row['user'] }}</td>
                                <td class="px-4 py-3">{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['waktu']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['status_lunas'] === 'Lunas')
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-emerald-100 text-emerald-800">{{ __('Lunas') }}</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-100 text-amber-800">{{ __('Belum Lunas') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => $row['detail_url'] ?? null])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada barang terjual pada tanggal ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
