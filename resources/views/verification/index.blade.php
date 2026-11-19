<x-app-layout>
    <x-slot name="title">{{ __('Verifikasi Transaksi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Verifikasi Transaksi') }}</h2>
    </x-slot>

    @php
        $tabQuery = request()->except(['tab', 'page']);
        $typeLabel = $types[$type] ?? $type;
    @endphp

    <div class="max-w-7xl mx-auto">
        @if (session('success'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        @if ($cutoffDate)
            <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-900 text-sm">
                {{ __('Transaksi dengan tanggal transaksi pada atau sebelum :date tidak ditampilkan dan tidak perlu diverifikasi.', ['date' => \Illuminate\Support\Carbon::parse($cutoffDate)->format('d/m/Y')]) }}
            </div>
        @endif

        <div class="mb-4 flex gap-2 border-b border-slate-200">
            <a href="{{ route('verification.index', array_merge($tabQuery, ['tab' => 'siap'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'siap' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}">
                {{ __('Data Siap Verifikasi') }}
                @php
                    $verificationPending = auth()->user()
                        ? app(\App\Services\TransactionVerificationService::class)->pendingTotal(auth()->user())
                        : 0;
                @endphp
                @if ($verificationPending > 0)
                    <span style="min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; line-height:20px; text-align:center;">{{ $verificationPending > 99 ? '99+' : $verificationPending }}</span>
                @endif
            </a>
            <a href="{{ route('verification.index', array_merge($tabQuery, ['tab' => 'riwayat'])) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'riwayat' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}">
                {{ __('Riwayat Verifikasi') }}
            </a>
        </div>

        <div class="card-modern mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('verification.index') }}" class="flex flex-wrap gap-3 items-end">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis') }}</label>
                        <select name="type" class="rounded-lg border border-slate-300 shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($canFilterLocation)
                        <div class="min-w-[140px]">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Jenis Lokasi') }}</label>
                            <select name="location_type" id="filter_location_type" class="w-full rounded-lg border border-slate-300 shadow-sm">
                                <option value="">{{ __('Semua') }}</option>
                                <option value="branch" @selected(request('location_type') === 'branch')>{{ __('Cabang') }}</option>
                                <option value="warehouse" @selected(request('location_type') === 'warehouse')>{{ __('Gudang') }}</option>
                            </select>
                        </div>
                        <div class="min-w-[180px] filter-branch" style="{{ request('location_type') !== 'branch' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="w-full rounded-lg border border-slate-300 shadow-sm">
                                <option value="">{{ __('Semua Cabang') }}</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-[180px] filter-warehouse" style="{{ request('location_type') !== 'warehouse' ? 'display:none' : '' }}">
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="warehouse_id" class="w-full rounded-lg border border-slate-300 shadow-sm">
                                <option value="">{{ __('Semua Gudang') }}</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dasar Tanggal') }}</label>
                        <select name="date_basis" class="rounded-lg border border-slate-300 shadow-sm">
                            <option value="catat" @selected(request('date_basis', 'catat') === 'catat')>{{ __('Tanggal Catat') }}</option>
                            <option value="transaksi" @selected(request('date_basis') === 'transaksi')>{{ __('Tanggal Transaksi') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Dari') }}</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Sampai') }}</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-300 shadow-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">{{ __('Filter') }}</button>
                </form>
            </div>
        </div>

        @php
            $cardColors = [
                'sale' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'line' => '#6ee7b7'],
                'purchase' => ['bg' => '#eef2ff', 'fg' => '#4338ca', 'line' => '#a5b4fc'],
                'service' => ['bg' => '#ecfeff', 'fg' => '#0e7490', 'line' => '#67e8f9'],
                'rental' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'line' => '#c4b5fd'],
                'cash_in' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'line' => '#fcd34d'],
                'cash_out' => ['bg' => '#fff1f2', 'fg' => '#be123c', 'line' => '#fda4af'],
                'cash_out_external' => ['bg' => '#fff7ed', 'fg' => '#c2410c', 'line' => '#fdba74'],
            ];
        @endphp
        <div style="display:flex; flex-wrap:nowrap; gap:8px; margin-bottom:1rem;">
            @foreach ($types as $value => $label)
                @php
                    $cardQuery = array_merge(request()->except(['page']), ['tab' => $tab, 'type' => $value]);
                    $activeCard = $type === $value;
                    $color = $cardColors[$value] ?? ['bg' => '#f8fafc', 'fg' => '#334155', 'line' => '#cbd5e1'];
                @endphp
                <a href="{{ route('verification.index', $cardQuery) }}"
                   style="flex:1 1 0; min-width:0; display:flex; flex-direction:column; gap:4px; border-radius:10px; padding:10px 12px; background-color: {{ $color['bg'] }}; color: {{ $color['fg'] }}; box-shadow: inset 0 0 0 {{ $activeCard ? '2px '.$color['fg'] : '1px '.$color['line'] }};">
                    <span style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                        <span style="font-size:15px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $label }}</span>
                        @if ($tab === 'siap')
                            <span style="font-size:22px; font-weight:700; white-space:nowrap;">{{ number_format($summary[$value] ?? 0, 0, ',', '.') }}</span>
                        @else
                            @php $item = $summary[$value] ?? ['total' => 0, 'accepted' => 0, 'rejected' => 0]; @endphp
                            <span style="font-size:22px; font-weight:700; white-space:nowrap;">{{ number_format($item['total'], 0, ',', '.') }}</span>
                        @endif
                    </span>
                    @if ($tab !== 'siap')
                        <span style="display:flex; gap:12px; font-size:13px; font-weight:600; white-space:nowrap;">
                            <span>{{ __('Terima') }} {{ number_format($item['accepted'], 0, ',', '.') }}</span>
                            <span>{{ __('Tolak') }} {{ number_format($item['rejected'], 0, ',', '.') }}</span>
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($tab === 'siap')
            <form id="accept-form" method="POST" action="{{ route('verification.accept') }}" class="hidden" data-verify-confirm="many">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
            </form>
            <div class="card-modern overflow-hidden">
                <div class="p-4 flex justify-between items-center border-b border-gray-100">
                    <p class="text-sm text-slate-600">{{ __('Centang transaksi yang diterima, lalu verifikasi sekaligus. Atau gunakan Terima / Tolak pada tiap baris.') }}</p>
                    <button type="submit" form="accept-form" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">{{ __('Terima yang dicentang') }}</button>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-3"></th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dokumen') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Catat') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('IP Perangkat') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Verifikasi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($rows as $row)
                                @php
                                    $txDate = $row->sale_date ?? $row->purchase_date ?? $row->entry_date ?? $row->pickup_date ?? $row->transaction_date ?? null;
                                    $doc = $row->invoice_number ?? ('KAS-'.$row->id);
                                    $detailUrl = \App\Models\TransactionVerification::urlFor($type, (int) $row->id);
                                @endphp
                                <tr>
                                    <td class="px-3 py-3">
                                        <input form="accept-form" type="checkbox" name="ids[]" value="{{ $row->id }}" class="rounded border-slate-300 text-indigo-600">
                                    </td>
                                    <td class="px-3 py-3">{{ $typeLabel }}</td>
                                    <td class="px-3 py-3">
                                        @if ($detailUrl)
                                            <a href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $doc }}</a>
                                        @else
                                            {{ $doc }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $row->user?->name ?? '-' }}</td>
                                    <td class="px-3 py-3">{{ $txDate ? \Illuminate\Support\Carbon::parse($txDate)->format('d/m/Y') : '-' }}</td>
                                    <td class="px-3 py-3"><x-wita-datetime :value="$row->created_at" /></td>
                                    <td class="px-3 py-3">{{ $row->client_ip ?: '-' }}</td>
                                    <td class="px-3 py-3">
                                        @if ($row->warehouse_id)
                                            {{ __('Gudang') }}: {{ $row->warehouse?->name ?? $row->warehouse_id }}
                                        @else
                                            {{ __('Cabang') }}: {{ $row->branch?->name ?? ($row->branch_id ?: '-') }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-col items-end gap-2">
                                            <form method="POST" action="{{ route('verification.accept') }}" data-verify-confirm="accept" data-document="{{ $doc }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $type }}">
                                                <input type="hidden" name="ids[]" value="{{ $row->id }}">
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium">{{ __('Terima') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('verification.reject') }}" class="flex items-center justify-end gap-2" data-verify-confirm="reject" data-document="{{ $doc }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $type }}">
                                                <input type="hidden" name="id" value="{{ $row->id }}">
                                                <input type="text" name="notes" placeholder="{{ __('Keterangan') }}" class="w-40 rounded-lg border border-slate-300 text-sm">
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-medium">{{ __('Tolak') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-10 text-center text-slate-500">{{ __('Tidak ada transaksi yang menunggu verifikasi.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">{{ $rows->links() }}</div>
                </div>
            </div>
        @else
            <div class="card-modern overflow-hidden">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User Verifikasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Verifikasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Hasil Verifikasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($history as $log)
                                @php $detailUrl = $log->detailUrl(); @endphp
                                <tr>
                                    <td class="px-3 py-3">
                                        {{ $types[$log->verification_type] ?? $log->verification_type }}
                                        ·
                                        @if ($detailUrl)
                                            <a href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $log->document_number }}</a>
                                        @else
                                            {{ $log->document_number }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $log->user?->name ?? '-' }}</td>
                                    <td class="px-3 py-3"><x-wita-datetime :value="$log->verified_at" /></td>
                                    <td class="px-3 py-3">{{ $log->flag_accepted ? __('Terima') : __('Tolak') }}</td>
                                    <td class="px-3 py-3">{{ $log->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-slate-500">{{ __('Belum ada riwayat.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">{{ $history->links() }}</div>
                </div>
            </div>
        @endif
    </div>

    @if ($tab === 'siap')
        @push('scripts')
            <script>
                document.addEventListener('submit', async function (event) {
                    const form = event.target;
                    const mode = form?.dataset?.verifyConfirm;
                    if (!mode || form.dataset.submitting === '1') {
                        return;
                    }
                    event.preventDefault();

                    let title = @json(__('Terima transaksi ini?'));
                    let text = form.dataset.document
                        ? @json(__('Transaksi')) + ' ' + form.dataset.document + ' ' + @json(__('akan ditandai diterima.'))
                        : @json(__('Transaksi akan ditandai diterima.'));
                    let confirmButtonColor = '#059669';
                    let confirmButtonText = @json(__('Ya, terima'));

                    if (mode === 'many') {
                        const count = document.querySelectorAll('input[name="ids[]"][form="accept-form"]:checked').length;
                        if (count < 1) {
                            await Swal.fire({
                                icon: 'warning',
                                title: @json(__('Belum ada transaksi dipilih')),
                                text: @json(__('Centang transaksi yang akan diterima.')),
                                confirmButtonColor: '#4f46e5',
                            });
                            return;
                        }
                        title = @json(__('Terima transaksi yang dicentang?'));
                        text = count + ' ' + @json(__('transaksi akan ditandai diterima.'));
                    }

                    if (mode === 'reject') {
                        const notes = (form.querySelector('[name="notes"]')?.value || '').trim();
                        if (notes === '') {
                            await Swal.fire({
                                icon: 'warning',
                                title: @json(__('Keterangan wajib diisi')),
                                text: @json(__('Isi keterangan sebelum menolak transaksi.')),
                                confirmButtonColor: '#dc2626',
                            });
                            return;
                        }
                        title = @json(__('Tolak transaksi ini?'));
                        text = (form.dataset.document ? form.dataset.document + '. ' : '') + @json(__('Transaksi akan dibatalkan.'));
                        confirmButtonColor = '#dc2626';
                        confirmButtonText = @json(__('Ya, tolak'));
                    }

                    const result = await Swal.fire({
                        icon: mode === 'reject' ? 'warning' : 'question',
                        title: title,
                        text: text,
                        showCancelButton: true,
                        confirmButtonText: confirmButtonText,
                        cancelButtonText: @json(__('Batal')),
                        confirmButtonColor: confirmButtonColor,
                        cancelButtonColor: '#64748b',
                    });
                    if (!result.isConfirmed) {
                        return;
                    }
                    form.dataset.submitting = '1';
                    form.submit();
                });
            </script>
        @endpush
    @endif

    @if ($canFilterLocation)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const locType = document.getElementById('filter_location_type');
                    const brBlock = document.querySelector('.filter-branch');
                    const whBlock = document.querySelector('.filter-warehouse');
                    function toggle() {
                        const value = locType ? locType.value : '';
                        if (brBlock) brBlock.style.display = value === 'branch' ? '' : 'none';
                        if (whBlock) whBlock.style.display = value === 'warehouse' ? '' : 'none';
                    }
                    locType?.addEventListener('change', toggle);
                    toggle();
                });
            </script>
        @endpush
    @endif
</x-app-layout>
