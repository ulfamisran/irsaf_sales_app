<x-transaction-report
    :number="$purchase->invoice_number"
    :back-url="route('purchases.show', $purchase)"
    :proofs="$purchase->proofs"
    proof-route="purchases.proofs.show"
    :owner="$purchase"
/>
