<x-app-layout>
    <x-slot name="title">{{ __('Laporan Log Pengguna') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Log Pengguna (Aktifitas)') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('daily-reports.user-activity') }}" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal') }}</label>
                        <input type="date" name="date" value="{{ $date }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('User') }}</label>
                        <select name="user_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" {{ (string) ($filterUserId ?? '') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[220px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis Transaksi') }}</label>
                        <select name="jenis_transaksi" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($transactionTypes as $type)
                                <option value="{{ $type }}" {{ ($filterJenis ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            {{ __('Tampilkan') }}
                        </button>
                        <a href="{{ route('daily-reports.user-activity') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        @php
                            $uaQuery = array_filter([
                                'date' => $date,
                                'user_id' => $filterUserId,
                                'jenis_transaksi' => $filterJenis,
                            ], fn ($v) => $v !== null && $v !== '');
                        @endphp
                        <a href="{{ route('daily-reports.user-activity.export', $uaQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            {{ __('Download Excel') }}
                        </a>
                        <a href="{{ route('daily-reports.user-activity.export-pdf', $uaQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                            {{ __('Download PDF') }}
                        </a>
                        <a href="{{ route('daily-reports.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50">
                            {{ __('Kembali') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if (($totalPembelian ?? null) !== null || ($totalPenjualan ?? null) !== null)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @if (($totalPembelian ?? null) !== null)
                    <div class="card-modern p-6">
                        <p class="text-sm text-slate-600">{{ __('Total Pembelian') }}</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ number_format($totalPembelian, 0, ',', '.') }}</p>
                    </div>
                @endif
                @if (($totalPenjualan ?? null) !== null)
                    <div class="card-modern p-6">
                        <p class="text-sm text-slate-600">{{ __('Total Penjualan') }}</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ number_format($totalPenjualan, 0, ',', '.') }}</p>
                    </div>
                @endif
            </div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <p class="text-sm text-slate-600">
                    {{ __('Tanggal') }}: <span class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span>
                    — {{ $activities->count() }} {{ __('aktifitas') }}
                </p>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Referensi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Deskripsi') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($activities as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $row['waktu']?->format('d/m/Y H:i:s') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 font-medium">{{ $row['user'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['lokasi'] ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-md text-xs font-medium bg-violet-50 text-violet-800">{{ $row['jenis'] }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $row['referensi'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $row['deskripsi'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @include('daily-reports.partials.detail-action', ['url' => $row['detail_url'] ?? null])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada aktifitas pada tanggal / filter ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
