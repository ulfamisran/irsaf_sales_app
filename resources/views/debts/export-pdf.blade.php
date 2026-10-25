<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Data Utang') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th, td { border: 1px solid #cccccc; padding: 4px; font-size: 8px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
        .meta { margin: 2px 0; }
    </style>
</head>
<body>
    <h3 style="margin:0 0 6px;">{{ __('Data Utang') }}</h3>
    <p class="meta"><strong>{{ __('Lokasi') }}:</strong> {{ $filterMeta['locationLine'] ?? '-' }}</p>
    <p class="meta"><strong>{{ __('Periode') }}:</strong> {{ $filterMeta['periodLine'] ?? '-' }} | <strong>{{ __('Status') }}:</strong> {{ $filterMeta['statusLine'] ?? '-' }} | <strong>{{ __('Lingkup') }}:</strong> {{ $filterMeta['lingkupLine'] ?? __('Semua Lingkup') }}</p>
    <p class="meta"><strong>{{ __('Cari') }}:</strong> {{ $filterMeta['searchLine'] ?? '-' }}</p>
    <p class="meta">
        <strong>{{ __('Jumlah') }}:</strong> {{ $count ?? 0 }} |
        <strong>{{ __('Invoice') }}:</strong> {{ number_format($totalInvoice ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Terakui') }}:</strong> {{ number_format($totalRecognized ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Terbayar') }}:</strong> {{ number_format($totalPaid ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Sisa') }}:</strong> {{ number_format($totalUnpaid ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Eksternal') }}:</strong> {{ number_format($totalUnpaidEksternal ?? 0, 0, ',', '.') }} |
        <strong>{{ __('Internal') }}:</strong> {{ number_format($totalUnpaidInternal ?? 0, 0, ',', '.') }}
    </p>
    <table>
        <thead>
            <tr>
                <th>{{ __('No. Invoice') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Distributor') }}</th>
                <th>{{ __('Lingkup') }}</th>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Jatuh Tempo') }}</th>
                <th class="num">{{ __('Invoice') }}</th>
                <th class="num">{{ __('Terakui') }}</th>
                <th class="num">{{ __('Terbayar') }}</th>
                <th class="num">{{ __('Sisa') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->purchase->invoice_number }}</td>
                    <td>
                        @if ($row->purchase->warehouse_id)
                            {{ $row->purchase->warehouse?->name ?? '-' }}
                        @else
                            {{ $row->purchase->branch?->name ?? '-' }}
                        @endif
                    </td>
                    <td>{{ $row->purchase->distributor?->name ?? '-' }}</td>
                    <td>{{ !empty($row->is_internal) ? __('Internal') : __('Eksternal') }}</td>
                    <td>{{ $row->purchase->purchase_date?->format('d/m/Y') }}</td>
                    <td>{{ $row->due_date?->format('d/m/Y') ?? '-' }}</td>
                    <td class="num">{{ number_format($row->invoice_total ?? 0, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row->recognized ?? 0, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row->purchase->total_paid ?? 0, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row->remaining ?? 0, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;">{{ __('Tidak ada data utang.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
