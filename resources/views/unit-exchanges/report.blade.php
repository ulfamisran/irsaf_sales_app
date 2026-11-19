<x-transaction-report
    :number="$exchange->exchange_number"
    :back-url="route('sales.unit-exchanges.show', $exchange)"
    :proofs="$exchange->proofs"
    proof-route="sales.unit-exchanges.proofs.show"
    :owner="$exchange"
    :invoice-view="$exchange->sale ? 'sales.invoice' : null"
    :invoice-data="$exchange->sale ? ['sale' => $exchange->sale, 'embedded' => true, 'forReport' => true] : []"
/>
