<x-app-layout>
    <x-slot name="title">{{ __('Detail Monitor Kas') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Detail Arus Kas') }} - {{ $kasLabel }}
            </h2>
            <a href="{{ route('finance.cash-monitoring', array_filter(['branch_id' => $locationType === 'branch' ? $location->id : null, 'warehouse_id' => $locationType === 'warehouse' ? $location->id : null, 'date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null])) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('Kembali ke Monitoring Kas') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100">
                <dl class="flex flex-wrap gap-6">
                    @if ($locationType === 'overall')
                        <div>
                            <dt class="text-sm text-slate-500">{{ __('Lokasi') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ __('Gabungan Cabang + Gudang') }}</dd>
                        </div>
                    @else
                        <div>
                            <dt class="text-sm text-slate-500">{{ $locationType === 'warehouse' ? __('Gudang') : __('Cabang') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ $location->name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-slate-500">{{ __('Kas / Rekening') }}</dt>
                        <dd class="font-semibold text-slate-800">{{ $kasLabel }}</dd>
                    </div>
                    @if (($dateFrom ?? null) && ($dateTo ?? null))
                        <div>
                            <dt class="text-sm text-slate-500">{{ __('Periode') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm text-slate-500">{{ __('Total Pemasukan') }}</dt>
                        <dd class="font-semibold text-emerald-600">{{ number_format($totalPemasukan, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">{{ __('Total Pengeluaran') }}</dt>
                        <dd class="font-semibold text-red-600">-{{ number_format($totalPengeluaran, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">{{ __('Saldo') }}</dt>
                        <dd class="font-semibold {{ $saldo >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($saldo, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card-modern overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800">{{ __('Daftar Transaksi') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Sumber') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($transactions as $tx)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $tx->source }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $tx->description }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold {{ $tx->type === 'IN' ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $tx->type === 'IN' ? '+' : '-' }}{{ number_format(abs($tx->amount), 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ __('Belum ada transaksi pada kas/rekening ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
