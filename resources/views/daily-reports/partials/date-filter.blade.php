@php
    $downloadQuery = array_filter(array_merge(
        ['date' => $date],
        $extraQuery ?? []
    ), fn ($v) => $v !== null && $v !== '');
@endphp
<div class="card-modern overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100">
        <form method="GET" action="{{ $action }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal') }}</label>
                <input type="date" name="date" value="{{ $date }}" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Tampilkan') }}
                </button>
                <a href="{{ $action }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                    {{ __('Reset') }}
                </a>
                @if (!empty($exportExcel))
                    <a href="{{ route($exportExcel, $downloadQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                        {{ __('Download Excel') }}
                    </a>
                @endif
                @if (!empty($exportPdf))
                    <a href="{{ route($exportPdf, $downloadQuery) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                        {{ __('Download PDF') }}
                    </a>
                @endif
                <a href="{{ route('daily-reports.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50">
                    {{ __('Kembali') }}
                </a>
            </div>
        </form>
    </div>
</div>
