@if (!empty($url))
    <x-icon-btn-view :href="$url" :label="__('Detail')" target="_blank" rel="noopener noreferrer" />
@else
    <span class="text-slate-400 text-sm">-</span>
@endif
