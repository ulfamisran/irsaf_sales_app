@props(['backdate' => false])

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-3 items-end']) }}>
    <div class="min-w-[160px]">
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Dari') }}</label>
        <input type="date" name="catat_from" value="{{ request('catat_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
    </div>
    <div class="min-w-[160px]">
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Sampai') }}</label>
        <input type="date" name="catat_to" value="{{ request('catat_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
    </div>
    @if ($backdate && auth()->user()?->isSuperAdminOrAdminPusat())
        <label class="inline-flex items-center gap-2 pb-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="backdate" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(request()->boolean('backdate'))>
            {{ __('Lihat Transaksi Backdate') }}
        </label>
    @endif
</div>
