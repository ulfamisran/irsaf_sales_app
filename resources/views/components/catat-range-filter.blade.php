<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-3 items-end']) }}>
    <div class="min-w-[160px]">
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Dari') }}</label>
        <input type="date" name="catat_from" value="{{ request('catat_from') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
    </div>
    <div class="min-w-[160px]">
        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal Catat Sampai') }}</label>
        <input type="date" name="catat_to" value="{{ request('catat_to') }}" class="w-full rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
    </div>
</div>
