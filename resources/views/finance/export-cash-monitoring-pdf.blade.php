<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ __('Monitoring Kas') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cccccc; padding: 5px; font-size: 10px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <p><strong>{{ __('MONITORING KAS') }}</strong></p>
    <p><strong>{{ __('Periode') }}:</strong> {{ $dateFrom ?? '-' }} - {{ $dateTo ?? '-' }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Lokasi') }}</th>
                <th class="num">{{ __('Total Pemasukan') }}</th>
                <th class="num">{{ __('Total Pengeluaran') }}</th>
                <th class="num">{{ __('Saldo') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($branches ?? collect()) as $branch)
                @php
                    $income = (float) (($branchInTotals[$branch->id] ?? 0));
                    $expense = (float) (($branchExpense[$branch->id] ?? 0));
                    $saldo = $income - $expense;
                @endphp
                <tr>
                    <td>{{ __('Cabang') }}: {{ $branch->name }}</td>
                    <td class="num">{{ number_format($income, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($expense, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($saldo, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            @foreach(($warehouses ?? collect()) as $warehouse)
                @php
                    $income = (float) (($warehouseInTotals[$warehouse->id] ?? 0));
                    $expense = (float) (($warehouseExpense[$warehouse->id] ?? 0));
                    $saldo = $income - $expense;
                @endphp
                <tr>
                    <td>{{ __('Gudang') }}: {{ $warehouse->name }}</td>
                    <td class="num">{{ number_format($income, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($expense, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($saldo, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>{{ __('TOTAL') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($overallIn ?? 0), 0, ',', '.') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($overallOut ?? 0), 0, ',', '.') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($overallSaldo ?? 0), 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
