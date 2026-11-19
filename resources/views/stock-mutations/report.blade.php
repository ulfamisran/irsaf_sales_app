<x-transaction-report
    :number="$distribution->invoice_number"
    :back-url="route('stock-mutations.index')"
    :proofs="$distribution->proofs"
    proof-route="stock-mutations.proofs.show"
    :owner="$distribution"
/>
