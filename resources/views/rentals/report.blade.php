<x-transaction-report
    :number="$rental->invoice_number"
    :back-url="route('rentals.show', $rental)"
    :proofs="$rental->proofs"
    proof-route="rentals.proofs.show"
    :owner="$rental"
    invoice-view="rentals.invoice"
    :invoice-data="['rental' => $rental, 'embedded' => true, 'forReport' => true]"
/>
