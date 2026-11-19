@props(['model', 'module' => null])
@php
    $auth = app(\App\Services\AuthorizationService::class);
    $module = $module ?: $auth->moduleFor($model);
@endphp
@if ($module && $auth->needsSubmitHighlight($model))
    <form method="POST" action="{{ route('authorizations.submit') }}" onsubmit="return confirm(@json(__('Ajukan otorisasi untuk dokumen ini?')));">
        @csrf
        <input type="hidden" name="module" value="{{ $module }}">
        <input type="hidden" name="document_id" value="{{ $model->getKey() }}">
        <button type="submit" style="color:#4f46e5; width:100%; text-align:left; background:transparent; border:0; cursor:pointer; display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; font-size:14px; font-weight:600;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;flex:0 0 auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('Ajukan Otorisasi') }}
        </button>
    </form>
@endif
