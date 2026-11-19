<x-app-layout>
    <x-slot name="title">{{ __('Cetak Laporan') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Cetak Laporan') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('print-reports.index') }}" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        @if ($canFilterLocation)
                            <select name="location" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua lokasi') }}</option>
                                @foreach ($locations as $option)
                                    <option value="{{ $option['value'] }}" {{ $filters['location'] === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" value="{{ $locationLabel }}" readonly class="w-full rounded-lg border border-slate-300 bg-slate-50 text-slate-700">
                        @endif
                    </div>
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('User Input') }}</label>
                        <select name="user_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua user') }}</option>
                            @foreach ($users as $userOption)
                                <option value="{{ $userOption->id }}" {{ (string) $filters['user_id'] === (string) $userOption->id ? 'selected' : '' }}>{{ $userOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Transaksi Dari') }}</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Transaksi Sampai') }}</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Dari') }}</label>
                        <input type="date" name="catat_from" value="{{ $filters['catat_from'] }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Sampai') }}</label>
                        <input type="date" name="catat_to" value="{{ $filters['catat_to'] }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">{{ __('Filter') }}</button>
                        <a href="{{ route('print-reports.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">{{ __('Bulan berjalan') }}</a>
                    </div>
                </form>
                @if ($datesDefaulted)
                    <p class="mt-3 text-sm text-slate-500">{{ __('Range tanggal belum dipilih. Laporan memakai tanggal transaksi bulan berjalan, tanggal 1 sampai hari ini.') }}</p>
                @endif
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Transaksi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Range Tanggal Transaksi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah Transaksi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $rangeLabel }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('print-reports.show', array_filter(['type' => $row['key'], 'location' => $filters['location'], 'user_id' => $filters['user_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'catat_from' => $filters['catat_from'], 'catat_to' => $filters['catat_to']], fn ($value) => $value !== null && $value !== '')) }}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;background:#1d4ed8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;">{{ __('Cetak Laporan') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
