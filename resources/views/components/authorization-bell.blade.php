@php
    $user = auth()->user();
    $canNotify = $user
        && \Illuminate\Support\Facades\Route::has('authorizations.index')
        && \Illuminate\Support\Facades\Schema::hasTable('authorization_requests')
        && app(\App\Services\AuthorizationService::class)->canOpenQueue($user);
    $summary = $canNotify
        ? app(\App\Services\AuthorizationService::class)->notificationSummary($user)
        : ['count' => 0, 'items' => collect()];
    $count = (int) ($summary['count'] ?? 0);
    $items = $summary['items'] ?? collect();
    $modules = \App\Services\AuthorizationService::MODULES;
    $badgeLabel = $count > 99 ? '99+' : (string) $count;
@endphp
@if ($canNotify)
    <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" style="overflow: visible; margin-right: 4px;">
        <button
            type="button"
            @click="open = !open"
            class="relative inline-flex items-center justify-center rounded-xl text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200/60 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all duration-200"
            style="width: 40px; height: 40px; overflow: visible;"
            aria-label="{{ __('Notifikasi otorisasi') }}"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span
                aria-hidden="true"
                style="position:absolute; top:-6px; right:-6px; z-index:30; display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 6px; border-radius:999px; font-size:11px; font-weight:700; line-height:1; border:2px solid #fff; box-shadow:0 1px 2px rgba(15,23,42,0.18); background:{{ $count > 0 ? '#ef4444' : '#cbd5e1' }}; color:{{ $count > 0 ? '#fff' : '#334155' }};"
            >{{ $badgeLabel }}</span>
        </button>

        <div x-show="open"
             x-transition
             class="absolute right-0 z-50 mt-2 rounded-xl bg-white shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden"
             style="display: none; width: min(28rem, calc(100vw - 1.5rem)); min-width: 22rem;">
            <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800">{{ __('Notifikasi Otorisasi') }}</p>
                    <p class="text-xs text-slate-500 mt-1 whitespace-nowrap">
                        {{ $count > 0
                            ? __(':count dokumen menunggu otorisasi', ['count' => $count])
                            : __('Tidak ada dokumen siap otorisasi') }}
                    </p>
                </div>
                <a href="{{ route('authorizations.index', ['tab' => 'siap']) }}" class="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-800 whitespace-nowrap pt-0.5">{{ __('Lihat semua') }}</a>
            </div>
            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                @forelse ($items as $row)
                    <a href="{{ route('authorizations.index', ['tab' => 'siap', 'module' => $row->module]) }}" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate">{{ $row->document_number }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $modules[$row->module]['label'] ?? $row->module }} · {{ $row->requester?->name ?? '-' }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ $row->requested_at?->timezone(\App\Support\CatatRange::TZ)->format('d/m H:i') }}</span>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Belum ada notifikasi otorisasi.') }}</div>
                @endforelse
            </div>
            @if ($count > 0)
                <div class="px-4 py-3 bg-slate-50 border-t border-slate-100">
                    <a href="{{ route('authorizations.index', ['tab' => 'siap']) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ __('Buka antrian otorisasi') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif
