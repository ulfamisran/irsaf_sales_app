@props(['model', 'module' => null])
@php
    $auth = app(\App\Services\AuthorizationService::class);
    $phase = $auth->phase($model);
    $module = $module ?: $auth->moduleFor($model);
    $showSubmitBadge = $auth->needsSubmitHighlight($model);
@endphp
@if ($showSubmitBadge)
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-1 items-start']) }}>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white border border-red-700">
            <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
            {{ __('Belum ajukan otorisasi') }}
        </span>
        @if ($module)
            <form method="POST" action="{{ route('authorizations.submit') }}" class="inline" onsubmit="return confirm(@json(__('Ajukan otorisasi untuk dokumen ini?')));">
                @csrf
                <input type="hidden" name="module" value="{{ $module }}">
                <input type="hidden" name="document_id" value="{{ $model->getKey() }}">
                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Ajukan Otorisasi') }}
                </button>
            </form>
        @endif
    </div>
@elseif ($phase === 'pending')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-800 border border-amber-200']) }}>
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        {{ __('Menunggu otorisasi') }}
    </span>
@elseif ($phase === 'approved')
    @php
        $needsReleaseAuth = ($model instanceof \App\Models\Service && $auth->serviceNeedsReleaseAuthorization($model))
            || ($model instanceof \App\Models\Rental && $auth->rentalNeedsReleaseAuthorization($model));
        $rentalNeedsEffects = $model instanceof \App\Models\Rental
            && (bool) $model->effects_pending
            && $auth->approvedAuthorizationCount($model) >= 1;
    @endphp
    @if ($needsReleaseAuth)
        <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide']) }}
              style="background:#ffedd5; color:#9a3412; border:1px solid #fdba74;">
            <span class="inline-block rounded-full" style="width:6px; height:6px; background:#f97316;"></span>
            {{ __('Lengkapi & ajukan release') }}
        </span>
    @elseif ($rentalNeedsEffects)
        <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-800 border border-emerald-200']) }}>
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            {{ __('Aktifkan Penyewaan') }}
        </span>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-800 border border-emerald-200']) }}>
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            {{ __('Siap release') }}
        </span>
    @endif
@endif
