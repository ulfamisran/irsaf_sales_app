@forelse ($rentals as $rental)
    @php
        $paidFromField = (float) ($rental->total_paid ?? 0);
        $paidFromPayments = (float) ($rental->payments?->sum('amount') ?? 0);
        $totalPaid = max($paidFromField, $paidFromPayments);
        $totalInvoice = (float) ($rental->total ?? 0);
        $outstanding = max(0, $totalInvoice - $totalPaid);
        $paymentStatus = $outstanding <= 0.02 ? __('Lunas') : __('Belum Lunas');
    @endphp
    <tr>
        <td>{{ $rental->invoice_number }}</td>
        <td>{{ $rental->pickup_date?->format('d/m/Y') }}</td>
        <td class="num">{{ number_format($totalPaid, 0, ',', '.') }}</td>
        <td class="num">{{ number_format($outstanding, 0, ',', '.') }}</td>
        <td>{{ $paymentStatus }}</td>
        <td>{{ $rental->customer?->name ?? '-' }}</td>
        <td>{{ $rental->user?->name ?? '-' }}</td>
        <td>{{ $rental->branch?->name ?? ($rental->warehouse?->name ?? '-') }}</td>
    </tr>
@empty
    <tr>
        <td colspan="8">{{ __('Tidak ada data penyewaan.') }}</td>
    </tr>
@endforelse
