<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Arus Kas') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 4px; font-size: 9px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @php
        $totalCashIn = (float) ($summary['IN'] ?? 0);
        $totalTradeInValue = (float) ($totalTradeIn ?? 0);
        $totalInCombined = $totalCashIn + $totalTradeInValue;
        $totalOut = (float) ($summary['OUT'] ?? 0);
    @endphp

    <p><strong>{{ __('LAPORAN ARUS KAS') }}</strong></p>
    <p><strong>{{ __('Lokasi') }}:</strong> {{ $filterMeta['location'] ?? '-' }}</p>
    <p><strong>{{ __('Tipe') }}:</strong> {{ $filterMeta['type'] ?? '-' }} | <strong>{{ __('Kas Pembayaran') }}:</strong> {{ $filterMeta['payment_method'] ?? '-' }}</p>
    <p><strong>{{ __('Kategori') }}:</strong> {{ $filterMeta['category'] ?? '-' }} | <strong>{{ __('Urutan') }}:</strong> {{ $filterMeta['order'] ?? '-' }}</p>
    <p><strong>{{ __('Periode') }}:</strong> {{ $filterMeta['date_from'] ?? '-' }} - {{ $filterMeta['date_to'] ?? '-' }}</p>
    <p><strong>{{ __('Total Dana Masuk (Gabungan)') }}:</strong> {{ number_format($totalInCombined, 0, ',', '.') }} | <strong>{{ __('Total Dana Keluar') }}:</strong> {{ number_format($totalOut, 0, ',', '.') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Tanggal') }}</th>
                <th>{{ __('Tipe') }}</th>
                <th>{{ __('Lokasi') }}</th>
                <th>{{ __('Kas Pembayaran') }}</th>
                <th>{{ __('Kategori') }}</th>
                <th>{{ __('Deskripsi') }}</th>
                <th>{{ __('Referensi') }}</th>
                <th class="num">{{ __('Pemasukan') }}</th>
                <th class="num">{{ __('Pengeluaran') }}</th>
                <th class="num">{{ __('Saldo') }}</th>
                <th>{{ __('User') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cashFlows as $cf)
                <tr>
                    <td>{{ optional($cf->transaction_date)->format('d/m/Y') }}</td>
                    <td>{{ $cf->type }}</td>
                    <td>{{ $cf->warehouse_id ? __('Gudang').': '.($cf->warehouse?->name ?? '-') : __('Cabang').': '.($cf->branch?->name ?? '-') }}</td>
                    <td>{{ $cf->kas_pembayaran_label }}</td>
                    <td>{{ $cf->type === 'OUT' ? ($cf->expenseCategory?->name ?? '-') : ($cf->incomeCategory?->name ?? '-') }}</td>
                    <td>{{ $cf->description }}</td>
                    <td>{{ $cf->reference_type && $cf->reference_id ? ($cf->reference_type . ' #' . $cf->reference_id) : '-' }}</td>
                    <td class="num">{{ $cf->type === 'IN' ? number_format((float) $cf->amount, 0, ',', '.') : '-' }}</td>
                    <td class="num">{{ $cf->type === 'OUT' ? number_format((float) $cf->amount, 0, ',', '.') : '-' }}</td>
                    <td class="num">{{ number_format((float) ($cf->running_balance ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $cf->user?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
