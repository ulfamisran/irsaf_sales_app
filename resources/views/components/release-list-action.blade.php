@props(['model'])
@php
    $auth = app(\App\Services\AuthorizationService::class);
    $show = $auth->showListRelease($model);
    $action = $show ? $auth->listReleaseRoute($model) : null;
@endphp
@if ($show && $action)
    <form method="POST" action="{{ $action }}" data-swal-confirm="{{ __('Release transaksi ini sekarang?') }}" data-swal-confirm-color="#059669">
        @csrf
        <input type="hidden" name="from_list" value="1">
        <button type="submit" style="color:#059669; width:100%; text-align:left; background:transparent; border:0; cursor:pointer; display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; font-size:14px; font-weight:600;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:16px;height:16px;flex:0 0 auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('Release') }}
        </button>
    </form>
@endif
