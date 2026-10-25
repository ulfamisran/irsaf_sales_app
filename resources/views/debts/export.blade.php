<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Data Utang') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('DATA UTANG') }}</strong></p>
    <p>{{ __('Lokasi') }}: {{ $filterMeta['locationLine'] ?? '-' }}</p>
    <p>{{ __('Periode') }}: {{ $filterMeta['periodLine'] ?? '-' }} | {{ __('Status') }}: {{ $filterMeta['statusLine'] ?? '-' }} | {{ __('Lingkup') }}: {{ $filterMeta['lingkupLine'] ?? __('Semua Lingkup') }} | {{ __('Cari') }}: {{ $filterMeta['searchLine'] ?? '-' }}</p>
    <p>
        {{ __('Jumlah Data') }}: {{ $count ?? 0 }} |
        {{ __('Nilai Invoice') }}: {{ number_format($totalInvoice ?? 0, 0, ',', '.') }} |
        {{ __('Utang Terakui') }}: {{ number_format($totalRecognized ?? 0, 0, ',', '.') }} |
        {{ __('Terbayar') }}: {{ number_format($totalPaid ?? 0, 0, ',', '.') }} |
        {{ __('Sisa Utang') }}: {{ number_format($totalUnpaid ?? 0, 0, ',', '.') }} |
        {{ __('Utang Eksternal') }}: {{ number_format($totalUnpaidEksternal ?? 0, 0, ',', '.') }} |
        {{ __('Utang Internal') }}: {{ number_format($totalUnpaidInternal ?? 0, 0, ',', '.') }}
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
                <th class="num">{{ __('Nilai Invoice') }}</th>
                <th class="num">{{ __('Utang Terakui') }}</th>
                <th class="num">{{ __('Terbayar') }}</th>
                <th class="num">{{ __('Sisa Utang Terakui') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->purchase->invoice_number }}</td>
                    <td>
                        @if ($row->purchase->warehouse_id)
                            {{ __('Gudang') }}: {{ $row->purchase->warehouse?->name ?? '-' }}
                        @else
                            {{ __('Cabang') }}: {{ $row->purchase->branch?->name ?? '-' }}
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
                    <td colspan="10">{{ __('Tidak ada data utang.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
