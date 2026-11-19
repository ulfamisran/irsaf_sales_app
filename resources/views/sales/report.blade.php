<x-transaction-report
    :number="$sale->invoice_number"
    :back-url="route('sales.show', $sale)"
    :proofs="$sale->proofs"
    proof-route="sales.proofs.show"
    :owner="$sale"
    invoice-view="sales.invoice"
    :invoice-data="['sale' => $sale, 'embedded' => true, 'forReport' => true]"
/>
