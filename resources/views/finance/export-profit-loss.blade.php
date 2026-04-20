<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Laporan Laba Rugi') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 6px; font-size: 11px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('LAPORAN LABA RUGI') }}</strong></p>
    <p><strong>{{ __('Periode') }}:</strong> {{ $dateFrom ?? '-' }} - {{ $dateTo ?? '-' }}</p>
    <table>
        <thead>
            <tr>
                <th>{{ __('Keterangan') }}</th>
                <th class="num">{{ __('Nilai') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>{{ __('Laba Kotor Penjualan') }}</td><td class="num">{{ number_format((float) ($totalSalesProfit ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Laba Kotor Service') }}</td><td class="num">{{ number_format((float) ($totalServiceProfit ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Laba Bersih Penyewaan') }}</td><td class="num">{{ number_format((float) ($totalRentalIncome ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Pendapatan Distribusi') }}</td><td class="num">{{ number_format((float) ($totalDistributionIncome ?? 0), 0, ',', '.') }}</td></tr>
            @if(($totalDistributionHpp ?? 0) > 0)
            <tr><td>{{ __('HPP Distribusi') }}</td><td class="num">-{{ number_format((float) ($totalDistributionHpp ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Laba Distribusi') }}</td><td class="num">{{ number_format((float) ($totalDistributionProfit ?? 0), 0, ',', '.') }}</td></tr>
            @endif
            <tr><td>{{ __('Pemasukan Lainnya') }}</td><td class="num">{{ number_format((float) ($totalOtherIncomeOnly ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Pengeluaran (Non Eksternal)') }}</td><td class="num">-{{ number_format((float) ($totalExpense ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Pengeluaran Dana Eksternal') }}</td><td class="num">-{{ number_format((float) ($totalExternalExpenseForProfit ?? 0), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('Beban Barang Rusak Cadangan') }}</td><td class="num">-{{ number_format((float) ($totalDamagedGoodsExpense ?? 0), 0, ',', '.') }}</td></tr>
            <tr>
                <td><strong>{{ __('Laba Keseluruhan') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($netProfit ?? 0), 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
