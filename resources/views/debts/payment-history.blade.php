<div class="p-6">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-800" id="modal-title">{{ __('Riwayat Pembayaran') }} - {{ $purchase->invoice_number }}</h3>
            <p class="text-sm text-slate-500 mt-1">{{ __('Distributor') }}: {{ $purchase->distributor?->name ?? '-' }}</p>
        </div>
        <button type="button" id="modal-close" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="mb-4 flex justify-between text-sm">
        <span class="text-slate-600">{{ __('Total') }}: {{ number_format($purchase->total, 0, ',', '.') }}</span>
        <span class="text-slate-600">{{ __('Terbayar') }}: {{ number_format($purchase->total_paid ?? 0, 0, ',', '.') }}</span>
        <span class="font-semibold text-red-600">{{ __('Sisa') }}: {{ number_format(max(0, (float)$purchase->total - (float)($purchase->total_paid ?? 0)), 0, ',', '.') }}</span>
    </div>

    <div class="max-h-80 overflow-y-auto">
        @forelse ($purchase->payments as $p)
            <div class="flex justify-between items-center py-2 px-3 rounded-lg hover:bg-slate-50 {{ $loop->odd ? 'bg-slate-50/50' : '' }}">
                <div class="flex-1">
                    <p class="font-medium text-slate-800">{{ $p->paymentMethod?->display_label ?? '-' }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $p->payment_date->format('d/m/Y') }}
                        @if ($p->user)
                            · {{ __('oleh') }} {{ $p->user->name }}
                        @endif
                        @if ($p->notes)
                            · {{ $p->notes }}
                        @endif
                    </p>
                </div>
                <span class="font-semibold text-emerald-600 ml-4">{{ number_format($p->amount, 0, ',', '.') }}</span>
            </div>
        @empty
            <p class="py-8 text-center text-slate-500">{{ __('Belum ada pembayaran.') }}</p>
        @endforelse
    </div>

    <div class="mt-4 pt-4 border-t border-slate-200">
        <a href="{{ route('purchases.show', ['purchase' => $purchase, 'view' => 'detail']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
            {{ __('Lihat Detail Pembelian') }}
        </a>
    </div>
</div>
