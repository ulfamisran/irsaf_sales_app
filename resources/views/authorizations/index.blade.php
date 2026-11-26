<x-app-layout>
    <x-slot name="title">{{ __('Otorisasi') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Otorisasi') }}</h2>
    </x-slot>

    @php
        $tabQuery = request()->except(['tab', 'page']);
    @endphp

    <div
        class="max-w-7xl mx-auto"
        @if ($tab === 'siap')
            x-data="authDecisionModal({
                detailUrlTemplate: @js(url('/authorizations/__ID__')),
                csrf: @js(csrf_token()),
                labels: {
                    loading: @js(__('Memuat detail transaksi...')),
                    loadError: @js(__('Gagal memuat detail transaksi.')),
                    notesRequired: @js(__('Keterangan wajib diisi saat menolak.')),
                    approveTitle: @js(__('Setujui otorisasi')),
                    rejectTitle: @js(__('Tolak otorisasi')),
                    approveHelp: @js(__('Periksa detail transaksi sebelum menyetujui.')),
                    rejectHelp: @js(__('Periksa detail transaksi. Keterangan wajib diisi untuk penolakan.')),
                    notesOptional: @js(__('Opsional')),
                    notesRequiredHint: @js(__('Wajib')),
                }
            })"
        @endif
    >
        @if (session('success'))
            <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="mb-4 flex gap-2 border-b border-slate-200">
            <a href="{{ route('authorizations.index', array_merge($tabQuery, ['tab' => 'siap'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'siap' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}">
                {{ __('Data Siap Otorisasi') }}
                @if ($pendingTotal > 0)
                    <span style="min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#ef4444; color:#fff; font-size:11px; font-weight:700; line-height:20px; text-align:center;">{{ $pendingTotal > 99 ? '99+' : $pendingTotal }}</span>
                @endif
            </a>
            <a href="{{ route('authorizations.index', array_merge($tabQuery, ['tab' => 'riwayat'])) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === 'riwayat' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}">
                {{ __('Riwayat Otorisasi') }}
            </a>
        </div>

        <div class="card-modern mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('authorizations.index') }}" class="flex flex-wrap gap-3 items-end">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="module" value="{{ $module }}">
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
                'purchases' => ['bg' => '#eef2ff', 'fg' => '#4338ca', 'line' => '#a5b4fc'],
                'stock-mutations' => ['bg' => '#ecfeff', 'fg' => '#0e7490', 'line' => '#67e8f9'],
                'sales' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'line' => '#6ee7b7'],
                'unit-exchanges' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'line' => '#c4b5fd'],
                'rentals' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'line' => '#fcd34d'],
                'services' => ['bg' => '#fff1f2', 'fg' => '#be123c', 'line' => '#fda4af'],
            ];
        @endphp
        <div style="display:flex; flex-wrap:nowrap; gap:8px; margin-bottom:1rem;">
            @foreach ($modules as $slug => $meta)
                @php
                    $cardQuery = array_merge(request()->except(['page']), ['tab' => $tab, 'module' => $slug]);
                    $activeCard = $module === $slug;
                    $color = $cardColors[$slug] ?? ['bg' => '#f8fafc', 'fg' => '#334155', 'line' => '#cbd5e1'];
                    $hasAccess = in_array($slug, $allowedModules ?? [], true);
                    $item = $tab === 'siap' ? null : ($summary[$slug] ?? ['total' => 0, 'accepted' => 0, 'rejected' => 0]);
                @endphp
                @if ($hasAccess)
                    <a href="{{ route('authorizations.index', $cardQuery) }}"
                       style="flex:1 1 0; min-width:0; display:flex; flex-direction:column; gap:4px; border-radius:10px; padding:10px 12px; background-color: {{ $color['bg'] }}; color: {{ $color['fg'] }}; box-shadow: inset 0 0 0 {{ $activeCard ? '2px '.$color['fg'] : '1px '.$color['line'] }};">
                        <span style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                            <span style="font-size:15px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $meta['label'] }}</span>
                            @if ($tab === 'siap')
                                <span style="font-size:22px; font-weight:700; white-space:nowrap;">{{ number_format($summary[$slug] ?? 0, 0, ',', '.') }}</span>
                            @else
                                <span style="font-size:22px; font-weight:700; white-space:nowrap;">{{ number_format($item['total'], 0, ',', '.') }}</span>
                            @endif
                        </span>
                        @if ($tab !== 'siap')
                            <span style="display:flex; gap:12px; font-size:13px; font-weight:600; white-space:nowrap;">
                                <span>{{ __('Setuju') }} {{ number_format($item['accepted'], 0, ',', '.') }}</span>
                                <span>{{ __('Tolak') }} {{ number_format($item['rejected'], 0, ',', '.') }}</span>
                            </span>
                        @endif
                    </a>
                @else
                    <div title="{{ __('Anda tidak ditunjuk sebagai otorisator modul ini') }}"
                         aria-disabled="true"
                         style="flex:1 1 0; min-width:0; display:flex; flex-direction:column; gap:4px; border-radius:10px; padding:10px 12px; background-color:#f1f5f9; color:#94a3b8; box-shadow: inset 0 0 0 1px #e2e8f0; opacity:0.65; cursor:not-allowed; pointer-events:none;">
                        <span style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                            <span style="font-size:15px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $meta['label'] }}</span>
                            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.02em;">{{ __('Tidak akses') }}</span>
                        </span>
                        <span style="font-size:12px; font-weight:500;">{{ __('Modul ini tidak termasuk wewenang Anda') }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($tab === 'siap')
            <form id="approve-form" method="POST" action="{{ route('authorizations.approve-many') }}" class="hidden" data-auth-confirm="many">
                @csrf
            </form>
            <div class="card-modern overflow-hidden">
                <div class="p-4 flex justify-between items-center border-b border-gray-100">
                    <p class="text-sm text-slate-600">{{ __('Centang dokumen yang disetujui, lalu otorisasi sekaligus. Atau gunakan Setujui / Tolak pada tiap baris.') }}</p>
                    <button type="submit" form="approve-form" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">{{ __('Setujui yang dicentang') }}</button>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-3"></th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jenis Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Dokumen') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Cabang/Gudang') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Transaksi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Catat') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('IP Perangkat') }}</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Otorisasi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($rows as $row)
                                @php
                                    $doc = $row->document_number ?: ('#'.$row->id);
                                    $detailUrl = app(\App\Services\AuthorizationService::class)->documentUrl($row);
                                    $txDate = $row->transactionDate();
                                @endphp
                                <tr>
                                    <td class="px-3 py-3">
                                        <input form="approve-form" type="checkbox" name="ids[]" value="{{ $row->id }}" class="rounded border-slate-300 text-indigo-600">
                                    </td>
                                    <td class="px-3 py-3">{{ $moduleLabel }}</td>
                                    <td class="px-3 py-3">
                                        @if ($detailUrl)
                                            <a href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $doc }}</a>
                                        @else
                                            {{ $doc }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $row->locationLabel() }}</td>
                                    <td class="px-3 py-3">{{ $row->requester?->name ?? '-' }}</td>
                                    <td class="px-3 py-3">{{ $txDate ? \Illuminate\Support\Carbon::parse($txDate)->format('d/m/Y') : '-' }}</td>
                                    <td class="px-3 py-3"><x-wita-datetime :value="$row->requested_at" /></td>
                                    <td class="px-3 py-3">{{ $row->clientIp() }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-col items-end gap-2">
                                            <button
                                                type="button"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium"
                                                @click="openDecision('approve', {{ (int) $row->id }})"
                                            >{{ __('Setujui') }}</button>
                                            <button
                                                type="button"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-medium"
                                                @click="openDecision('reject', {{ (int) $row->id }})"
                                            >{{ __('Tolak') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-10 text-center text-slate-500">{{ __('Tidak ada dokumen yang menunggu otorisasi.') }}</td>
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
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Cabang/Gudang') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('User Otorisasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal Otorisasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Hasil Otorisasi') }}</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Keterangan') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($history as $log)
                                @php $detailUrl = app(\App\Services\AuthorizationService::class)->documentUrl($log); @endphp
                                <tr>
                                    <td class="px-3 py-3">
                                        {{ $modules[$log->module]['label'] ?? $log->module }}
                                        ·
                                        @if ($detailUrl)
                                            <a href="{{ $detailUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $log->document_number }}</a>
                                        @else
                                            {{ $log->document_number }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $log->locationLabel() }}</td>
                                    <td class="px-3 py-3">{{ $log->decider?->name ?? '-' }}</td>
                                    <td class="px-3 py-3"><x-wita-datetime :value="$log->decided_at" /></td>
                                    <td class="px-3 py-3">{{ $log->status === 'approved' ? __('Setuju') : __('Tolak') }}</td>
                                    <td class="px-3 py-3">{{ $log->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-slate-500">{{ __('Belum ada riwayat.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">{{ $history->links() }}</div>
                </div>
            </div>
        @endif

        @if ($tab === 'siap')
        {{-- Modal detail + keputusan otorisasi --}}
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-modal="true"
            role="dialog"
        >
            <div class="fixed inset-0 bg-slate-900/50" @click="close()"></div>
            <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-xl overflow-hidden" @click.stop>
                    <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900" x-text="mode === 'reject' ? labels.rejectTitle : labels.approveTitle"></h3>
                            <p class="text-sm text-slate-500 mt-0.5" x-text="mode === 'reject' ? labels.rejectHelp : labels.approveHelp"></p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600" @click="close()" aria-label="{{ __('Tutup') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="px-5 py-4 max-h-[70vh] overflow-y-auto space-y-4">
                        <template x-if="loading">
                            <p class="text-sm text-slate-500 py-8 text-center" x-text="labels.loading"></p>
                        </template>
                        <template x-if="!loading && error">
                            <p class="text-sm text-red-600 py-8 text-center" x-text="error"></p>
                        </template>
                        <template x-if="!loading && !error && detail">
                            <div class="space-y-4">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-500" x-text="detail.module_label"></p>
                                            <p class="text-lg font-semibold text-slate-900" x-text="detail.document_number"></p>
                                        </div>
                                        <a
                                            x-show="detail.detail_url"
                                            :href="detail.detail_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-sm text-indigo-600 hover:underline"
                                        >{{ __('Buka halaman detail') }}</a>
                                    </div>
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                        <div><span class="text-slate-500">{{ __('Cabang/Gudang') }}:</span> <span class="font-medium text-slate-800" x-text="detail.location || '-'"></span></div>
                                        <div><span class="text-slate-500">{{ __('User') }}:</span> <span class="font-medium text-slate-800" x-text="detail.requester || '-'"></span></div>
                                        <div><span class="text-slate-500">{{ __('Tanggal Transaksi') }}:</span> <span class="font-medium text-slate-800" x-text="detail.transaction_date || '-'"></span></div>
                                        <div><span class="text-slate-500">{{ __('Tanggal Catat') }}:</span> <span class="font-medium text-slate-800" x-text="detail.requested_at || '-'"></span></div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" x-show="detail.fields?.length">
                                    <template x-for="(field, idx) in detail.fields" :key="'f'+idx">
                                        <div class="rounded-lg border border-slate-100 px-3 py-2 text-sm">
                                            <p class="text-xs text-slate-500" x-text="field.label"></p>
                                            <p class="font-medium text-slate-800" x-text="field.value"></p>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="detail.lines?.length">
                                    <h4 class="text-sm font-semibold text-slate-800 mb-2">{{ __('Item transaksi') }}</h4>
                                    <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                                        <template x-for="(line, idx) in detail.lines" :key="'l'+idx">
                                            <div class="px-3 py-2 flex items-start justify-between gap-3 text-sm">
                                                <div class="min-w-0">
                                                    <p class="font-medium text-slate-800" x-text="line.title"></p>
                                                    <p class="text-xs text-slate-500 whitespace-pre-line" x-text="line.meta"></p>
                                                </div>
                                                <p class="font-semibold text-slate-800 whitespace-nowrap" x-text="line.amount"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="detail.payments?.length">
                                    <h4 class="text-sm font-semibold text-slate-800 mb-2">{{ __('Pembayaran') }}</h4>
                                    <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                                        <template x-for="(pay, idx) in detail.payments" :key="'p'+idx">
                                            <div class="px-3 py-2 flex items-center justify-between gap-3 text-sm">
                                                <p class="text-slate-700" x-text="pay.method"></p>
                                                <p class="font-semibold text-slate-800" x-text="pay.amount"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">
                                        {{ __('Keterangan') }}
                                        <span class="font-normal text-slate-500" x-text="mode === 'reject' ? '(' + labels.notesRequiredHint + ')' : '(' + labels.notesOptional + ')'"></span>
                                    </label>
                                    <textarea
                                        x-model="notes"
                                        rows="3"
                                        maxlength="500"
                                        class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        :placeholder="mode === 'reject' ? @js(__('Tulis alasan penolakan...')) : @js(__('Catatan opsional...'))"
                                    ></textarea>
                                    <p class="mt-1 text-xs text-red-600" x-show="notesError" x-text="notesError"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-white" @click="close()" :disabled="submitting">
                            {{ __('Batal') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-white text-sm font-medium disabled:opacity-60"
                            :class="mode === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                            @click="submitDecision()"
                            :disabled="loading || !!error || submitting || !detail"
                            x-text="submitting ? @js(__('Memproses...')) : (mode === 'reject' ? @js(__('Tolak')) : @js(__('Setujui')))"
                        ></button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if ($tab === 'siap')
        @push('scripts')
            <script>
                function authDecisionModal(config) {
                    return {
                        open: false,
                        loading: false,
                        submitting: false,
                        error: null,
                        notesError: null,
                        mode: 'approve',
                        notes: '',
                        detail: null,
                        labels: config.labels,
                        csrf: config.csrf,
                        detailUrlTemplate: config.detailUrlTemplate,

                        async openDecision(mode, id) {
                            this.mode = mode;
                            this.notes = '';
                            this.notesError = null;
                            this.error = null;
                            this.detail = null;
                            this.open = true;
                            this.loading = true;
                            try {
                                const url = this.detailUrlTemplate.replace('__ID__', String(id));
                                const res = await fetch(url, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                });
                                if (!res.ok) {
                                    throw new Error(this.labels.loadError);
                                }
                                this.detail = await res.json();
                            } catch (e) {
                                this.error = this.labels.loadError;
                            } finally {
                                this.loading = false;
                            }
                        },

                        close() {
                            if (this.submitting) return;
                            this.open = false;
                        },

                        submitDecision() {
                            if (!this.detail || this.submitting) return;
                            this.notesError = null;
                            const trimmed = String(this.notes || '').trim();
                            if (this.mode === 'reject' && !trimmed) {
                                this.notesError = this.labels.notesRequired;
                                return;
                            }

                            const action = this.mode === 'reject' ? this.detail.reject_url : this.detail.approve_url;
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = action;
                            form.style.display = 'none';

                            const csrf = document.createElement('input');
                            csrf.type = 'hidden';
                            csrf.name = '_token';
                            csrf.value = this.csrf;
                            form.appendChild(csrf);

                            const notes = document.createElement('input');
                            notes.type = 'hidden';
                            notes.name = 'notes';
                            notes.value = trimmed;
                            form.appendChild(notes);

                            document.body.appendChild(form);
                            this.submitting = true;
                            form.submit();
                        },
                    };
                }

                document.addEventListener('submit', async function (event) {
                    const form = event.target;
                    const mode = form?.dataset?.authConfirm;
                    if (!mode || form.dataset.submitting === '1') {
                        return;
                    }
                    event.preventDefault();

                    if (mode === 'many') {
                        const count = document.querySelectorAll('input[name="ids[]"][form="approve-form"]:checked').length;
                        if (count < 1) {
                            await Swal.fire({
                                icon: 'warning',
                                title: @json(__('Belum ada dokumen dipilih')),
                                text: @json(__('Centang dokumen yang akan disetujui.')),
                                confirmButtonColor: '#4f46e5',
                            });
                            return;
                        }
                        const result = await Swal.fire({
                            icon: 'question',
                            title: @json(__('Setujui dokumen yang dicentang?')),
                            text: count + ' ' + @json(__('dokumen akan disetujui.')),
                            showCancelButton: true,
                            confirmButtonText: @json(__('Ya, setujui')),
                            cancelButtonText: @json(__('Batal')),
                            confirmButtonColor: '#059669',
                            cancelButtonColor: '#64748b',
                        });
                        if (!result.isConfirmed) {
                            return;
                        }
                        form.dataset.submitting = '1';
                        form.submit();
                    }
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
