<x-app-layout>
    <x-slot name="title">{{ __('Laporan Service') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Service (Barang Masuk & Keluar)') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @include('daily-reports.partials.date-filter', ['action' => route('daily-reports.service'), 'date' => $date])

        @php
            $svcLokasi = function ($svc) {
                if ($svc->warehouse_id || $svc->warehouse) {
                    return __('Gudang').': '.($svc->warehouse?->name ?? '#'.$svc->warehouse_id);
                }
                if ($svc->branch_id || $svc->branch) {
                    return __('Cabang').': '.($svc->branch?->name ?? '#'.$svc->branch_id);
                }
                return '-';
            };
        @endphp

        {{-- Barang Masuk Service --}}
        <div class="card-modern overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 bg-sky-50/60">
                <h3 class="font-semibold text-slate-800">{{ __('Barang Masuk Service') }}</h3>
                <p class="text-sm text-slate-600 mt-0.5">{{ __('Transaksi service yang masuk pada tanggal') }} {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ({{ $masuk->count() }})</p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pelanggan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Laptop') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Masuk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu Input') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($masuk as $svc)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $svc->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $svcLokasi($svc) }}</td>
                                <td class="px-4 py-3">{{ $svc->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->status }}</td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => route('services.show', $svc)])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-slate-500">{{ __('Tidak ada service masuk pada tanggal ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Barang Keluar (Selesai) --}}
        <div class="card-modern overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 bg-emerald-50/60">
                <h3 class="font-semibold text-slate-800">{{ __('Barang Keluar (Selesai Service)') }}</h3>
                <p class="text-sm text-slate-600 mt-0.5">{{ __('Service selesai / keluar pada tanggal') }} {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} ({{ $keluar->count() }})</p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pelanggan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Laptop') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Masuk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Keluar') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pickup') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($keluar as $svc)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $svc->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $svcLokasi($svc) }}</td>
                                <td class="px-4 py-3">{{ $svc->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->exit_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($svc->pickup_status === \App\Models\Service::PICKUP_SUDAH)
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-emerald-100 text-emerald-800">{{ __('Sudah Diambil') }}</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-100 text-amber-800">{{ __('Belum Diambil') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => route('services.show', $svc)])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-slate-500">{{ __('Tidak ada service selesai pada tanggal ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Menunggu Diambil --}}
        <div class="card-modern overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 bg-amber-50/60">
                <h3 class="font-semibold text-slate-800">{{ __('Barang Menunggu Diambil') }}</h3>
                <p class="text-sm text-slate-600 mt-0.5">{{ __('Service selesai namun belum diambil (per tanggal') }} {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}) ({{ $menunggu->count() }})</p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Pelanggan') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Laptop') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Masuk') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Selesai') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu Input') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($menunggu as $svc)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono text-sm">{{ $svc->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $svcLokasi($svc) }}</td>
                                <td class="px-4 py-3">{{ $svc->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ trim(($svc->laptop_type ?? '').' '.($svc->laptop_detail ?? '')) ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->entry_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->exit_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $svc->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => route('services.show', $svc)])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-slate-500">{{ __('Tidak ada barang menunggu diambil.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
