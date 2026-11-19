<x-app-layout>
    <x-slot name="title">{{ __('Pergerakan Barang') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Pergerakan Barang') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern mb-6">
            <form method="GET" action="{{ route('stock-units.movement') }}" class="p-4 flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis pencarian') }}</label>
                    <select name="mode" class="rounded-lg border border-slate-300 shadow-sm">
                        <option value="serial" @selected($mode === 'serial')>{{ __('Nomor serial') }}</option>
                        <option value="sku" @selected($mode === 'sku')>{{ __('SKU') }}</option>
                    </select>
                </div>
                <div class="min-w-[240px] flex-1">
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Kata kunci') }}</label>
                    <input type="text" name="q" value="{{ $q }}" class="w-full rounded-lg border border-slate-300 shadow-sm" placeholder="{{ __('Nomor serial atau SKU') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Urutkan') }}</label>
                    <select name="sort" class="rounded-lg border border-slate-300 shadow-sm">
                        <option value="transaction" @selected($sort === 'transaction')>{{ __('Tanggal transaksi') }}</option>
                        <option value="created_at" @selected($sort === 'created_at')>{{ __('Tanggal catat') }}</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">{{ __('Tampilkan') }}</button>
            </form>
        </div>

        @if ($result)
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-800">{{ $result['title'] }}</h3>
                @if ($result['subtitle'])
                    <p class="text-sm text-slate-600">{{ $result['subtitle'] }}</p>
                @endif
            </div>

            <div class="card-modern overflow-hidden">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Catat') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Arus') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Nomor Dokumen') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Transaksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($result['events'] as $event)
                                <tr>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ $event['date'] ? $event['date']->timezone(config('app.timezone'))->format('d/m/Y') : '-' }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ $event['recorded_at'] ? $event['recorded_at']->timezone(\App\Support\CatatRange::TZ)->format('d/m/Y H:i') : '-' }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap font-medium text-slate-800">{{ $event['type'] }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ ['in' => __('Masuk'), 'out' => __('Keluar'), 'transfer' => __('Pindah')][$event['direction'] ?? ''] ?? '-' }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ $event['document'] }}</td>
                                    <td class="px-3 py-3">{{ $event['detail'] }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ $event['actor'] ?: '-' }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap">{{ $event['status'] }}</td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        @if ($event['url'])
                                            <a href="{{ $event['url'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ __('Buka') }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-8 text-center text-slate-500">{{ $result['found'] ? __('Belum ada transaksi untuk data ini.') : __('Data tidak ditemukan.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
