<x-app-layout>
    <x-slot name="title">{{ __('Cek Pembelian Barang') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Cek Pembelian Barang') }}</h2>
            <a href="{{ route('purchases.index', ['tab' => 'nilai']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                {{ __('Nilai Pembelian') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        @php $activeTab = $tab ?? 'pengecekan'; @endphp
        <div class="mb-6 flex gap-1 border-b border-slate-200">
            <a href="{{ route('purchase-receiving.index', array_merge(request()->except('tab', 'page'), ['tab' => 'pengecekan'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'pengecekan' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Pengecekan Barang') }}
            </a>
            <a href="{{ route('purchase-receiving.index', array_merge(request()->except('tab', 'page'), ['tab' => 'riwayat'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'riwayat' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Riwayat Pengecekan') }}
            </a>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4">
                @php
                    $selectedLocationType = request('location_type', 'warehouse');
                    $selectedLocationId = request('location_id');
                    if (! $selectedLocationId) {
                        $selectedLocationId = $selectedLocationType === 'branch' ? request('branch_id') : request('warehouse_id');
                    }
                @endphp
                <form method="GET" action="{{ route('purchase-receiving.index') }}" class="space-y-4">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @if ($canFilterLocation)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                                <select name="location_type" id="pr_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="warehouse" {{ $selectedLocationType == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                    <option value="branch" {{ $selectedLocationType == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                                <select name="location_id" id="pr_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Semua') }}</option>
                                    @if ($selectedLocationType === 'branch')
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->id }}" {{ (string) $selectedLocationId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    @else
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ (string) $selectedLocationId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @elseif ($filterLocked ?? false)
                            <div>
                                <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="str_contains((string) $locationLabel, __('Cabang')) ? __('Cabang') : __('Gudang')" />
                            </div>
                            <div>
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel" />
                            </div>
                        @endif

                        @if ($activeTab === 'riwayat')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                                <input type="text" name="search" value="{{ request('search') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Invoice / distributor / serial...') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari') }}</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai') }}</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2 {{ $activeTab === 'riwayat' ? 'md:col-span-4' : '' }}">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                {{ __('Filter') }}
                            </button>
                            <a href="{{ route('purchase-receiving.index', ['tab' => $activeTab]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                                {{ __('Reset') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($activeTab === 'riwayat')
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Beli') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Terakhir Dicek') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Jumlah Cek') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($historyPurchases as $p)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium">{{ $p->invoice_number }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->purchase_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    {{ $p->last_checked_at ? \Illuminate\Support\Carbon::parse($p->last_checked_at)->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">{{ $p->receiving_logs_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('purchase-receiving.history', $p) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-800 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        {{ __('Detail') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">{{ __('Belum ada riwayat pengecekan.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $historyPurchases->links() }}</div>
            </div>
        </div>
        @else
        <p class="mb-4 text-sm text-slate-600">{{ __('Daftar pembelian unit yang masih memiliki barang belum dicek.') }}</p>
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Nilai Invoice') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Belum Dicek') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status Cek') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $row)
                            @php $p = $row->purchase; $s = $row->summary; @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium">{{ $p->invoice_number }}</td>
                                <td class="px-4 py-3">{{ $p->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($s['invoice_total'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-700 font-medium">Rp {{ number_format($s['unchecked_value'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $s['check_status_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('purchase-receiving.show', $p) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                                        {{ __('Cek') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada pembelian yang perlu dicek.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>
        @endif
    </div>

    @if ($canFilterLocation)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locType = document.getElementById('pr_location_type');
            const locId = document.getElementById('pr_location_id');
            const warehouses = @json($warehouses);
            const branches = @json($branches);

            function renderLocationOptions(type, selected = '') {
                if (!locId) return;
                locId.innerHTML = '<option value="">{{ __('Semua') }}</option>';
                const list = type === 'branch' ? branches : warehouses;
                list.forEach(function(item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    if (String(selected) === String(item.id)) opt.selected = true;
                    locId.appendChild(opt);
                });
            }

            if (locType) {
                locType.addEventListener('change', function() {
                    renderLocationOptions(locType.value, '');
                });
            }
        });
    </script>
    @endpush
    @endif
</x-app-layout>
