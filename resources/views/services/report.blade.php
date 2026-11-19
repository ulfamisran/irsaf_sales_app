<x-transaction-report
    :number="$service->invoice_number"
    :back-url="route('services.show', $service)"
    :proofs="$service->proofs"
    proof-route="services.proofs.show"
    :owner="$service"
    invoice-view="services.invoice"
    :invoice-data="['service' => $service, 'embedded' => true, 'forReport' => true]"
/>
