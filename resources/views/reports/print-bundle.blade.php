<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Cetak Laporan') }}</title>
    @include('reports.partials.page-styles')
</head>
<body>
    <div class="report-toolbar no-print">
        <a href="{{ $backUrl }}">{{ __('Kembali') }}</a>
        <button type="button" onclick="window.print()">{{ __('Unduh / Cetak Laporan') }}</button>
    </div>

    @foreach ($groups as $group)
        <h2 class="group-title no-print">{{ $group['label'] }} ({{ count($group['documents']) }})</h2>
        @forelse ($group['documents'] as $document)
            @include('reports.partials.document', $document)
        @empty
            <p class="empty-note no-print">{{ __('Tidak ada transaksi pada filter ini.') }}</p>
        @endforelse
    @endforeach
</body>
</html>
