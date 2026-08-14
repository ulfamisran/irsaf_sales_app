<x-app-layout>
    <x-slot name="title">{{ __('Laporan Barang Masuk') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Barang Masuk') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @include('daily-reports.partials.date-filter', ['action' => route('daily-reports.barang-masuk'), 'date' => $date])

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <p class="text-sm text-slate-600">
                    {{ __('Tanggal') }}: <span class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span>
                    — {{ $rows->count() }} {{ __('data') }}
                </p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Referensi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit / Serial') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium bg-cyan-50 text-cyan-800">{{ $row['jenis'] }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $row['referensi'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['lokasi'] ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['produk'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['qty'] }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $row['serial'] }}</td>
                                <td class="px-4 py-3">{{ $row['user'] }}</td>
                                <td class="px-4 py-3">{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['waktu']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $row['keterangan'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => $row['detail_url'] ?? null])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data barang masuk pada tanggal ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
