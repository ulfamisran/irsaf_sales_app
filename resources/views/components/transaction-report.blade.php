@props(['number', 'backUrl', 'proofs', 'proofRoute', 'owner', 'invoiceView' => null, 'invoiceData' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Laporan') }} {{ $number }}</title>
    @include('reports.partials.page-styles')
</head>
<body>
    <div class="report-toolbar">
        <a href="{{ $backUrl }}">{{ __('Kembali') }}</a>
        <button type="button" onclick="window.print()">{{ __('Cetak Laporan') }}</button>
    </div>
    @include('reports.partials.document', [
        'number' => $number,
        'proofs' => $proofs,
        'proofRoute' => $proofRoute,
        'owner' => $owner,
        'invoiceView' => $invoiceView,
        'invoiceData' => $invoiceData,
    ])
</body>
</html>
