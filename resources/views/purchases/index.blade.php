<x-app-layout>
    <x-slot name="title">{{ __('Riwayat Pembelian') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Riwayat Pembelian') }}</h2>
            <x-icon-btn-add :href="route('purchases.create')" :label="__('Catat Pembelian')" />
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        @php $activeTab = $tab ?? request('tab', 'daftar'); @endphp
        <div class="mb-6 flex flex-wrap gap-1 border-b border-slate-200">
            <a href="{{ route('purchases.index', array_merge(request()->except('tab', 'page'), ['tab' => 'daftar'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'daftar' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Daftar Pembelian') }}
            </a>
            <a href="{{ route('purchases.index', array_merge(request()->except('tab', 'page'), ['tab' => 'nilai'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'nilai' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Nilai Pembelian') }}
            </a>
            <a href="{{ route('purchases.index', array_merge(request()->except('tab', 'page'), ['tab' => 'utang'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'utang' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Utang Pembelian') }}
            </a>
            <a href="{{ route('purchases.index', array_merge(request()->except('tab', 'page'), ['tab' => 'penangguhan'])) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px {{ $activeTab === 'penangguhan' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ __('Penangguhan Pembelian') }}
            </a>
        </div>

        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                @php
                    $selectedLocationType = request('location_type', 'warehouse');
                    $selectedLocationId = request('location_id');
                    if (! $selectedLocationId) {
                        $selectedLocationId = $selectedLocationType === 'branch' ? request('branch_id') : request('warehouse_id');
                    }
                @endphp
                <form method="GET" action="{{ route('purchases.index') }}" class="space-y-4">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="{{ __('No. invoice, distributor, user...') }}">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @if ($canFilterLocation)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" id="po_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="warehouse" {{ request('location_type') == 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                                <option value="branch" {{ request('location_type') == 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                            <select name="location_id" id="po_location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                    @else
                        @if ($filterLocked)
                            <div>
                                <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="str_contains((string) $locationLabel, __('Cabang')) ? __('Cabang') : __('Gudang')" />
                            </div>
                            <div>
                                <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel" />
                            </div>
                        @endif
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari Tanggal') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai Tanggal') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Distributor') }}</label>
                        <select name="distributor_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($distributors as $d)
                                <option value="{{ $d->id }}" {{ request('distributor_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($activeTab === 'nilai')
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Cek') }}</label>
                        <select name="check_status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="unchecked" {{ request('check_status') === 'unchecked' ? 'selected' : '' }}>{{ __('Belum dicek') }}</option>
                            <option value="partial" {{ request('check_status') === 'partial' ? 'selected' : '' }}>{{ __('Sebagian dicek') }}</option>
                            <option value="checked" {{ request('check_status') === 'checked' ? 'selected' : '' }}>{{ __('Selesai dicek') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Bayar') }}</label>
                        <select name="payment_status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="no_recognized" {{ request('payment_status') === 'no_recognized' ? 'selected' : '' }}>{{ __('Belum ada utang terakui') }}</option>
                            <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>{{ __('Belum Lunas') }}</option>
                            <option value="partial" {{ request('payment_status') === 'partial' ? 'selected' : '' }}>{{ __('Sebagian') }}</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>{{ __('Lunas') }}</option>
                        </select>
                    </div>
                    @elseif ($activeTab === 'daftar')
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Release') }}</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Dibatalkan') }}</option>
                            <option value="paid_off" {{ request('status') === 'paid_off' ? 'selected' : '' }}>{{ __('Lunas') }}</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>{{ __('Belum Lunas') }}</option>
                        </select>
                    </div>
                    @endif
                    <div class="md:col-span-2 flex flex-wrap gap-2 md:justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('purchases.index', ['tab' => $activeTab]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                        @if ($activeTab === 'daftar')
                        <a href="{{ route('purchases.export', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                            {{ __('Download Excel') }}
                        </a>
                        <a href="{{ route('purchases.export-pdf', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                            {{ __('Download PDF') }}
                        </a>
                        @endif
                    </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($activeTab === 'utang')
        <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-rose-800">{{ __('Total Utang Terakui Belum Dibayar') }}</p>
                <p class="text-2xl font-bold text-rose-700">Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</p>
            </div>
        </div>
        @elseif ($activeTab === 'penangguhan')
        <div class="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-amber-800">{{ __('Total Nilai Penangguhan') }}</p>
                <p class="text-2xl font-bold text-amber-700">Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</p>
            </div>
        </div>
        @elseif ($activeTab === 'daftar')
        <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-emerald-800">{{ __('Total Utang Terakui (halaman ini)') }}</p>
                <p class="text-2xl font-bold text-emerald-700">Rp {{ number_format($totalRecognized ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        @endif

        @if ($activeTab === 'nilai')
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
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status Cek') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Utang Terakui') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Retur') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Ditangguhkan') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Belum Dicek') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Terbayar') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status Bayar') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $row)
                            @php
                                $p = $row->purchase ?? $row;
                                $s = $row->summary ?? null;
                            @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium">{{ $p->invoice_number }}</td>
                                <td class="px-4 py-3">{{ $p->purchase_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($s['invoice_total'] ?? $p->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($s['check_status'] ?? '') === 'checked' ? 'bg-emerald-100 text-emerald-700' : (($s['check_status'] ?? '') === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-orange-100 text-orange-800') }}">
                                        {{ $s['check_status_label'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-emerald-700 font-medium">Rp {{ number_format($s['recognized_debt'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-rose-700">Rp {{ number_format($s['return_value'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-700">Rp {{ number_format($s['deferred_value'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-orange-700">Rp {{ number_format($s['unchecked_value'] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($s['paid'] ?? $p->total_paid, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($s['payment_status'] ?? '') === 'paid' ? 'bg-emerald-100 text-emerald-700' : (($s['payment_status'] ?? '') === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700') }}">
                                        {{ $s['payment_status_label'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap gap-2 justify-center items-center">
                                        <x-icon-btn-view :href="route('purchases.show', $p)" :label="__('Detail')" />
                                        @if (!empty($s['needs_check']))
                                            <a href="{{ route('purchase-receiving.show', $p) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                                </svg>
                                                {{ __('Cek') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pembelian.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>

        @elseif ($activeTab === 'utang')
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit Belum Dibayar') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Utang Terakui') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Pembayaran') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Utang yang Harus Dibayar') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $row)
                            @php
                                $p = $row->purchase ?? $row;
                                $recognized = (float) ($row->recognized ?? $row->summary['recognized_debt'] ?? 0);
                                $paid = (float) ($row->paid ?? $row->summary['paid'] ?? $p->total_paid ?? 0);
                                $remaining = (float) ($row->remaining ?? $row->summary['remaining_recognized'] ?? max(0, $recognized - $paid));
                                $units = $row->units ?? [];
                            @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('purchases.show', ['purchase' => $p, 'view' => 'detail']) }}" class="text-indigo-600 hover:underline">{{ $p->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->purchase_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if (count($units) === 0)
                                        <span class="text-slate-400">-</span>
                                    @else
                                        <ul class="space-y-1 max-w-xs">
                                            @foreach ($units as $u)
                                                <li>
                                                    <span class="text-slate-700">{{ $u['label'] }}</span>
                                                    @if (!empty($u['serial']))
                                                        <span class="font-mono text-xs text-slate-600">— {{ $u['serial'] }}</span>
                                                    @else
                                                        <span class="text-xs text-slate-500">× {{ $u['qty'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-emerald-700 font-medium">Rp {{ number_format($recognized, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format($paid, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600">Rp {{ number_format($remaining, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('purchases.show', ['purchase' => $p, 'view' => 'detail']) }}#payment-form-section"
                                       class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        {{ __('Bayar') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada utang pembelian yang belum dibayar.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>

        @elseif ($activeTab === 'penangguhan')
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Unit Ditangguhkan') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $row)
                            @php
                                $p = $row->purchase;
                                $items = $row->deferred_items ?? [];
                            @endphp
                            <tr class="hover:bg-slate-50/50 align-top">
                                <td class="px-4 py-3 font-medium">{{ $p->invoice_number }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->purchase_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="space-y-3">
                                        @foreach ($items as $item)
                                            <div class="rounded-lg border border-amber-100 bg-amber-50/50 p-3">
                                                <p class="text-sm font-medium text-slate-800">{{ $item['label'] }}</p>
                                                @if (!empty($item['serial']))
                                                    <p class="font-mono text-xs text-slate-600 mt-0.5">{{ $item['serial'] }}</p>
                                                @else
                                                    <p class="text-xs text-slate-600 mt-0.5">{{ __('Qty') }}: {{ $item['qty'] }}</p>
                                                @endif
                                                <p class="text-xs text-amber-700 mt-1">Rp {{ number_format($item['value'], 0, ',', '.') }}</p>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <form method="POST" action="{{ route('purchases.resolve-deferred', $p) }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="diterima">
                                                        @if (!empty($item['unit_id']))
                                                            <input type="hidden" name="unit_id" value="{{ $item['unit_id'] }}">
                                                        @else
                                                            <input type="hidden" name="purchase_detail_id" value="{{ $item['purchase_detail_id'] }}">
                                                            <input type="hidden" name="qty" value="{{ $item['qty'] }}">
                                                        @endif
                                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700"
                                                                onclick="return confirm(@json(__('Tandai unit sebagai diterima (masuk stok)?')))">
                                                            {{ __('Unit diterima') }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('purchases.resolve-deferred', $p) }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="diretur">
                                                        @if (!empty($item['unit_id']))
                                                            <input type="hidden" name="unit_id" value="{{ $item['unit_id'] }}">
                                                        @else
                                                            <input type="hidden" name="purchase_detail_id" value="{{ $item['purchase_detail_id'] }}">
                                                            <input type="hidden" name="qty" value="{{ $item['qty'] }}">
                                                        @endif
                                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-rose-600 text-white text-xs font-medium hover:bg-rose-700"
                                                                onclick="return confirm(@json(__('Tandai unit sebagai diretur?')))">
                                                            {{ __('Unit diretur') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-icon-btn-view :href="route('purchases.show', $p)" :label="__('Detail')" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada unit yang ditangguhkan.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>

        @else
        {{-- Daftar Pembelian --}}
        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Utang Terakui') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Terbayar') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status Bayar') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($purchases as $row)
                            @php
                                $p = is_object($row) && isset($row->purchase) ? $row->purchase : $row;
                                $s = is_object($row) && isset($row->summary) ? $row->summary : null;
                                $recognized = (float) ($s['recognized_debt'] ?? 0);
                            @endphp
                            <tr class="hover:bg-slate-50/50 {{ $p->isCancelled() ? 'bg-slate-50' : '' }}">
                                <td class="px-4 py-3 font-medium">
                                    {{ $p->invoice_number }}
                                    @if ($p->isCancelled())
                                        <span class="ml-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">{{ __('Dibatalkan') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $p->jenis_pembelian ?? __('Pembelian Unit') }}</td>
                                <td class="px-4 py-3">{{ $p->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $p->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->warehouse_id)
                                        {{ __('Gudang') }}: {{ $p->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $p->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($p->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-700 font-medium">{{ number_format($recognized, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="{{ ($s['payment_status'] ?? '') === 'paid' ? 'text-emerald-600 font-medium' : 'text-amber-600' }}">
                                        {{ number_format($s['paid'] ?? $p->total_paid, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($p->isCancelled())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ __('Dibatalkan') }}</span>
                                    @elseif ($s)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ ($s['payment_status'] ?? '') === 'paid' ? 'bg-emerald-100 text-emerald-700' : (($s['payment_status'] ?? '') === 'partial' ? 'bg-amber-100 text-amber-800' : (($s['payment_status'] ?? '') === 'no_recognized' ? 'bg-slate-100 text-slate-500' : 'bg-red-100 text-red-700')) }}">
                                            {{ $s['payment_status_label'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $p->user?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2 justify-center items-center">
                                        <x-icon-btn-view :href="route('purchases.show', ['purchase' => $p, 'view' => 'detail'])" :label="__('Detail')" />
                                        @if ($p->canBeEdited())
                                            <x-icon-btn-edit :href="route('purchases.edit', $p)" :label="__('Ubah')" />
                                        @endif
                                        @if (!$p->isCancelled() && (auth()->user()->isSuperAdminOrAdminPusat() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_GUDANG])))
                                            <a href="{{ route('purchases.show', ['purchase' => $p, 'view' => 'cancel']) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                {{ __('Cancel') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data pembelian.') }}</td>
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
            const locType = document.getElementById('po_location_type');
            const locId = document.getElementById('po_location_id');
            const warehouses = @json($warehouses);
            const branches = @json($branches);

            function renderLocationOptions(type, selected = '') {
                if (!locId) return;
                locId.innerHTML = '';
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = @json(__('Semua'));
                locId.appendChild(defaultOpt);

                const list = type === 'branch' ? branches : warehouses;
                list.forEach(function(item) {
                    const opt = document.createElement('option');
                    opt.value = String(item.id);
                    opt.textContent = item.name;
                    if (String(selected) === String(item.id)) {
                        opt.selected = true;
                    }
                    locId.appendChild(opt);
                });
            }

            if (locType && locId) {
                renderLocationOptions(locType.value, locId.value);
                locType.addEventListener('change', function() {
                    renderLocationOptions(this.value, '');
                });
            }
        });
    </script>
    @endpush
    @endif
</x-app-layout>
