@php
    $summary = app(\App\Services\AuthorizationService::class)->notificationSummary(auth()->user(), 5);
    $count = (int) ($summary['count'] ?? 0);
    $items = $summary['items'] ?? collect();
    $modules = \App\Services\AuthorizationService::MODULES;
@endphp
@if ($count > 0 && \Illuminate\Support\Facades\Route::has('authorizations.index'))
    <div class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-500 text-white shadow-lg shadow-amber-500/30 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </span>
                <div>
                    <p class="text-base font-semibold text-amber-950">{{ __('Ada :count dokumen siap otorisasi', ['count' => $count]) }}</p>
                    <p class="text-sm text-amber-800/80 mt-1">{{ __('Periksa antrian otorisasi untuk menyetujui atau menolak pengajuan.') }}</p>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($items as $row)
                            <li class="text-sm text-amber-950/90">
                                <span class="font-medium">{{ $row->document_number }}</span>
                                <span class="text-amber-800/70">· {{ $modules[$row->module]['label'] ?? $row->module }} · {{ $row->requester?->name ?? '-' }}</span>
                            </li>
                        @endforeach
                        @if ($count > $items->count())
                            <li class="text-xs text-amber-800/70">{{ __('dan :n dokumen lainnya', ['n' => $count - $items->count()]) }}</li>
                        @endif
                    </ul>
                </div>
            </div>
            <a href="{{ route('authorizations.index', ['tab' => 'siap']) }}" class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 shrink-0">
                {{ __('Buka otorisasi') }}
            </a>
        </div>
    </div>
@endif
