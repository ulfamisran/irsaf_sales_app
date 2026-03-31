@forelse ($sales as $sale)
    @php
        $paidFromField = (float) ($sale->total_paid ?? 0);
        $paidFromPayments = (float) ($sale->payments?->sum('amount') ?? 0);
        $paidFromTradeIn = (float) ($sale->tradeIns?->sum('trade_in_value') ?? 0);
        $totalPaid = max($paidFromField, $paidFromPayments + $paidFromTradeIn);
        $totalInvoice = (float) ($sale->total ?? 0);
        $outstanding = max(0, $totalInvoice - $totalPaid);
        $paymentStatus = $outstanding <= 0.02 ? __('Lunas') : __('Belum Lunas');
    @endphp
    <tr>
        <td>{{ $sale->invoice_number }}</td>
        <td>{{ $sale->sale_date?->format('d/m/Y') }}</td>
        <td class="num">{{ number_format($totalPaid, 0, ',', '.') }}</td>
        <td class="num">{{ number_format($outstanding, 0, ',', '.') }}</td>
        <td>{{ $paymentStatus }}</td>
        <td>{{ $sale->customer?->name ?? '-' }}</td>
        <td>{{ $sale->user?->name ?? '-' }}</td>
        <td>{{ $sale->branch?->name ?? '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="8">{{ __('Tidak ada data penjualan.') }}</td>
    </tr>
@endforelse
