<x-app-layout>
    <x-slot name="title">{{ __('Mutasi Stok') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Distribusi Barang') }}</h2>
            <x-icon-btn-add :href="route('stock-mutations.create')" :label="__('Distribusi Baru')" />
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
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif
        @if (session('info'))
            <div class="mb-6 rounded-xl bg-sky-50 border border-sky-200 p-4 text-sky-900 flex items-center gap-3">
                <svg class="w-5 h-5 text-sky-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                {{ session('info') }}
            </div>
        @endif

        {{-- Filter --}}
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('stock-mutations.index') }}" class="space-y-4">
                    <input type="hidden" name="tab" id="filter-tab" value="{{ $activeTab }}">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cari') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('No. invoice, SKU, brand, serial...') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @if($canFilterLocation ?? false)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi Tipe') }}</label>
                        <select name="location_type" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Lokasi') }}</label>
                        <select name="location_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($warehouses ?? [] as $w)
                                <option value="{{ $w->id }}" {{ request('location_id') == $w->id ? 'selected' : '' }}>{{ __('Gudang') }}: {{ $w->name }}</option>
                            @endforeach
                            @foreach ($branches ?? [] as $b)
                                <option value="{{ $b->id }}" {{ request('location_id') == $b->id ? 'selected' : '' }}>{{ __('Cabang') }}: {{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @elseif($filterLocked ?? false)
                    <div>
                        <x-locked-location label="{{ __('Tipe Lokasi') }}" :value="str_contains((string) ($locationLabel ?? ''), __('Cabang')) ? __('Cabang') : __('Gudang')" />
                        <input type="hidden" name="location_type" value="{{ $locationType }}">
                    </div>
                    <div>
                        <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                        <input type="hidden" name="location_id" value="{{ $locationId }}">
                    </div>
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Produk') }}</label>
                        <select name="product_id" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->sku }} - {{ $p->brand }} ({{ $p->in_stock_count ?? 0 }} unit)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">{{ __('Semua') }}</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Release') }}</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Dibatalkan') }}</option>
                            <option value="paid_off" {{ request('status') === 'paid_off' ? 'selected' : '' }}>{{ __('Lunas') }}</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>{{ __('Belum Lunas') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 flex gap-2 md:justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('stock-mutations.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-xs text-slate-500 mb-2 px-1">{{ __('Baris dengan latar abu-abu dan label "Dibatalkan" adalah distribusi yang sudah dibatalkan (arsip).') }}</p>

        {{-- Tab Navigation --}}
        <div class="flex border-b border-gray-200 mb-0">
            <button type="button" data-tab-target="invoices" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'invoices' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('Invoice Distribusi') }}
            </button>
            <button type="button" data-tab-target="riwayat" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'riwayat' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                {{ __('Riwayat Distribusi') }}
            </button>
        </div>

        {{-- ==================== INVOICE TAB ==================== --}}
        <div id="tab-invoices" class="tab-content {{ $activeTab !== 'invoices' ? 'hidden' : '' }}">
            <div class="card-modern overflow-hidden" style="border-top-left-radius:0;border-top-right-radius:0">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dari') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Ke') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Trx') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total Bayar') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($distributions as $dist)
                                @php
                                    $invoiceCancelled = $dist->isCancelled();
                                    $totalBiaya = (float) $dist->total;
                                    $totalPaid = (float) $dist->total_paid;
                                    $sisa = max(0, $totalBiaya - $totalPaid);
                                    $isLunas = $totalBiaya > 0 && ($totalPaid + 0.02 >= $totalBiaya);
                                    $noBiaya = $totalBiaya <= 0;

                                    $fromIsWh = $dist->from_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
                                    $fromName = $fromIsWh ? ($warehousesById[$dist->from_location_id] ?? '#'.$dist->from_location_id) : ($branchesById[$dist->from_location_id] ?? '#'.$dist->from_location_id);
                                    $fromLabel = $fromIsWh ? __('Gudang') : __('Cabang');

                                    $toIsWh = $dist->to_location_type === \App\Models\Stock::LOCATION_WAREHOUSE;
                                    $toName = $toIsWh ? ($warehousesById[$dist->to_location_id] ?? '#'.$dist->to_location_id) : ($branchesById[$dist->to_location_id] ?? '#'.$dist->to_location_id);
                                    $toLabel = $toIsWh ? __('Gudang') : __('Cabang');
                                @endphp
                                <tr @class([
                                    'hover:bg-slate-50/50' => ! $invoiceCancelled,
                                    'bg-slate-100/90 border-l-4 border-l-slate-500' => $invoiceCancelled,
                                ])>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span @class(['font-mono text-sm', 'text-indigo-700' => ! $invoiceCancelled, 'text-slate-600 line-through decoration-slate-400' => $invoiceCancelled])>{{ $dist->invoice_number }}</span>
                                            @if ($invoiceCancelled)
                                                <span class="inline-flex items-center w-fit px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-300 text-slate-800">{{ __('Dibatalkan') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm @if($invoiceCancelled) text-slate-500 @endif">{{ $dist->distribution_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm @if($invoiceCancelled) text-slate-500 @endif">{{ $fromLabel . ': ' . $fromName }}</td>
                                    <td class="px-4 py-3 text-sm @if($invoiceCancelled) text-slate-500 @endif">{{ $toLabel . ': ' . $toName }}</td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5 @if($invoiceCancelled) opacity-80 @endif">
                                            @foreach ($dist->details as $detail)
                                                <div class="text-sm">
                                                    <span @class(['text-slate-800' => ! $invoiceCancelled, 'text-slate-600' => $invoiceCancelled])>{{ $detail->product?->sku }}</span>
                                                    <span class="text-slate-500">- {{ $detail->product?->brand }}</span>
                                                    <span @class(['font-medium', 'text-indigo-600' => ! $invoiceCancelled, 'text-slate-500' => $invoiceCancelled])>({{ $detail->quantity }} unit)</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium @if($invoiceCancelled) text-slate-500 @endif">
                                        @if ($totalBiaya > 0)
                                            Rp {{ number_format($totalBiaya, 0, ',', '.') }}
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium @if($invoiceCancelled) text-slate-500 @endif">
                                        @if ($totalBiaya > 0)
                                            Rp {{ number_format($totalPaid, 0, ',', '.') }}
                                            @if ($invoiceCancelled)
                                                <span class="block text-[10px] font-normal text-slate-400 normal-case">{{ __('nilai saat aktif') }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($invoiceCancelled)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-300 text-slate-800">{{ __('Dibatalkan') }}</span>
                                            @if ($dist->cancel_date)
                                                <div class="text-xs text-slate-500 mt-1">{{ $dist->cancel_date->format('d/m/Y') }}</div>
                                            @endif
                                            @if ($dist->cancelUser)
                                                <div class="text-xs text-slate-500">{{ __('Oleh') }}: {{ $dist->cancelUser->name }}</div>
                                            @endif
                                        @elseif ($noBiaya)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ __('Gratis') }}</span>
                                        @elseif ($isLunas)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">{{ __('Lunas') }}</span>
                                        @else
                                            <div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('Belum Lunas') }}</span>
                                                <div class="text-xs text-slate-500 mt-0.5">{{ __('Sisa') }}: Rp {{ number_format($sisa, 0, ',', '.') }}</div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-wrap items-center justify-center gap-1">
                                            @if (! $invoiceCancelled && ! $noBiaya && ! $isLunas)
                                            <a href="{{ route('stock-mutations.add-payment', $dist) }}" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-colors" title="{{ __('Tambah Pembayaran') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                {{ __('Bayar') }}
                                            </a>
                                            @endif
                                            <a href="{{ route('stock-mutations.invoice', $dist) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="{{ __('Invoice') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                {{ __('Invoice') }}
                                            </a>
                                            @if (($canCancelDistribution ?? false) && ! $invoiceCancelled)
                                            <a href="{{ route('stock-mutations.cancel.show', $dist) }}" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors" title="{{ __('Batalkan distribusi') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                {{ __('Batal') }}
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data invoice distribusi.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">{{ $distributions->links() }}</div>
                </div>
            </div>
        </div>

        {{-- ==================== RIWAYAT TAB ==================== --}}
        <div id="tab-riwayat" class="tab-content {{ $activeTab !== 'riwayat' ? 'hidden' : '' }}">
            <div class="card-modern overflow-hidden" style="border-top-left-radius:0;border-top-right-radius:0">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Invoice') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Produk') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dari') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Ke') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Serial') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Biaya/Unit') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Status Bayar') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($riwayatDetails as $detail)
                                @php
                                    $dist = $detail->distribution;
                                    $rowCancelled = $dist?->isCancelled() ?? false;
                                    $totalBiaya = (float) ($dist->total ?? 0);
                                    $totalPaid = (float) ($dist->total_paid ?? 0);
                                    $statusBayar = $totalBiaya <= 0 ? '-' : ($totalPaid + 0.02 >= $totalBiaya ? 'Lunas' : 'Belum Lunas');
                                    $statusClass = $totalBiaya <= 0 ? 'text-slate-500' : ($totalPaid + 0.02 >= $totalBiaya ? 'text-emerald-600 font-medium' : 'text-red-600 font-medium');

                                    $fromIsWh = ($dist->from_location_type ?? '') === \App\Models\Stock::LOCATION_WAREHOUSE;
                                    $fromName = $fromIsWh ? ($warehousesById[$dist->from_location_id ?? 0] ?? '#'.($dist->from_location_id ?? 0)) : ($branchesById[$dist->from_location_id ?? 0] ?? '#'.($dist->from_location_id ?? 0));
                                    $fromLabel = $fromIsWh ? __('Gudang') : __('Cabang');
                                    $toIsWh = ($dist->to_location_type ?? '') === \App\Models\Stock::LOCATION_WAREHOUSE;
                                    $toName = $toIsWh ? ($warehousesById[$dist->to_location_id ?? 0] ?? '#'.($dist->to_location_id ?? 0)) : ($branchesById[$dist->to_location_id ?? 0] ?? '#'.($dist->to_location_id ?? 0));
                                    $toLabel = $toIsWh ? __('Gudang') : __('Cabang');
                                @endphp
                                <tr @class([
                                    'hover:bg-slate-50/50' => ! $rowCancelled,
                                    'bg-slate-100/90 border-l-4 border-l-slate-500' => $rowCancelled,
                                ])>
                                    <td class="px-4 py-3 @if($rowCancelled) text-slate-500 @endif">{{ $dist->distribution_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span @class(['font-mono text-xs', 'text-indigo-600' => ! $rowCancelled, 'text-slate-600 line-through decoration-slate-400' => $rowCancelled])>{{ $dist->invoice_number ?? '-' }}</span>
                                            @if ($rowCancelled)
                                                <span class="inline-flex items-center w-fit px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-300 text-slate-800">{{ __('Batal') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ $detail->product?->sku }} - {{ $detail->product?->brand }}</td>
                                    <td class="px-4 py-3">{{ $fromLabel }}: {{ $fromName }}</td>
                                    <td class="px-4 py-3">{{ $toLabel }}: {{ $toName }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ $detail->serial_numbers ? \Illuminate\Support\Str::limit(str_replace("\n", ', ', $detail->serial_numbers), 40) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $detail->quantity }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if (($detail->biaya_distribusi_per_unit ?? 0) > 0)
                                            {{ number_format($detail->biaya_distribusi_per_unit, 0, ',', '.') }}/unit
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($rowCancelled)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-300 text-slate-800">{{ __('Dibatalkan') }}</span>
                                            @if ($dist->cancel_date)
                                                <div class="text-[11px] text-slate-500 mt-1">{{ __('Tgl batal') }}: {{ $dist->cancel_date->format('d/m/Y') }}</div>
                                            @endif
                                        @else
                                            <span class="{{ $statusClass }}">{{ $statusBayar }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 @if($rowCancelled) text-slate-500 @endif">{{ $dist->user?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex flex-wrap items-center justify-center gap-1">
                                            @if ($dist && ! $rowCancelled && $totalBiaya > 0 && $totalPaid + 0.02 < $totalBiaya)
                                            <a href="{{ route('stock-mutations.add-payment', $dist) }}" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition-colors" title="{{ __('Tambah Pembayaran') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                {{ __('Bayar') }}
                                            </a>
                                            @endif
                                            @if ($dist)
                                            <a href="{{ route('stock-mutations.invoice', $dist) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition-colors" title="{{ __('Invoice') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                {{ __('Invoice') }}
                                            </a>
                                            @if (($canCancelDistribution ?? false) && ! $rowCancelled)
                                            <a href="{{ route('stock-mutations.cancel.show', $dist) }}" class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors" title="{{ __('Batalkan distribusi') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                {{ __('Batal') }}
                                            </a>
                                            @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data riwayat distribusi.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">{{ $riwayatDetails->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('[data-tab-target]');
            const tabContents = document.querySelectorAll('.tab-content');
            const filterTabInput = document.getElementById('filter-tab');

            tabBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const target = this.dataset.tabTarget;

                    tabBtns.forEach(function(b) {
                        b.classList.remove('border-indigo-600', 'text-indigo-600');
                        b.classList.add('border-transparent', 'text-slate-500');
                    });
                    this.classList.remove('border-transparent', 'text-slate-500');
                    this.classList.add('border-indigo-600', 'text-indigo-600');

                    tabContents.forEach(function(c) { c.classList.add('hidden'); });
                    document.getElementById('tab-' + target).classList.remove('hidden');

                    filterTabInput.value = target;

                    const url = new URL(window.location);
                    url.searchParams.set('tab', target);
                    window.history.replaceState({}, '', url);
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
