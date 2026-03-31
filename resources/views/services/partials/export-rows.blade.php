@forelse ($services as $service)
    @php
        $paidFromField = (float) ($service->total_paid ?? 0);
        $paidFromPayments = (float) ($service->payments?->sum('amount') ?? 0);
        $totalPaid = max($paidFromField, $paidFromPayments);
        $totalInvoice = (float) ($service->total_service_price ?? 0);
        $outstanding = max(0, $totalInvoice - $totalPaid);
        $paymentStatus = $outstanding <= 0.02 ? __('Lunas') : __('Belum Lunas');
    @endphp
    <tr>
        <td>{{ $service->invoice_number }}</td>
        <td>{{ $service->entry_date?->format('d/m/Y') }}</td>
        <td class="num">{{ number_format($totalPaid, 0, ',', '.') }}</td>
        <td class="num">{{ number_format($outstanding, 0, ',', '.') }}</td>
        <td>{{ $paymentStatus }}</td>
        <td>{{ $service->customer?->name ?? '-' }}</td>
        <td>{{ $service->user?->name ?? '-' }}</td>
        <td>{{ $service->branch?->name ?? '-' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="8">{{ __('Tidak ada data service.') }}</td>
    </tr>
@endforelse
