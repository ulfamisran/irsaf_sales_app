<x-app-layout>
    <x-slot name="title">{{ __('Data Utang') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Data Utang') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="card-modern overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <form method="GET" action="{{ route('debts.index') }}" class="flex flex-wrap gap-4 items-end">
                    @if($canFilterLocation ?? false)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tipe Lokasi') }}</label>
                            <select name="location_type" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                <option value="branch" {{ request('location_type') === 'branch' ? 'selected' : '' }}>{{ __('Cabang') }}</option>
                                <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>{{ __('Gudang') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Cabang') }}</label>
                            <select name="branch_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Gudang') }}</label>
                            <select name="warehouse_id" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($filterLocked ?? false)
                        <div class="min-w-[180px]">
                            <x-locked-location label="{{ __('Lokasi') }}" :value="$locationLabel ?? ''" />
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Status Utang') }}</label>
                        <select name="status" class="rounded-lg border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="outstanding" {{ ($statusFilter ?? 'outstanding') === 'outstanding' ? 'selected' : '' }}>{{ __('Outstanding (Belum Lunas)') }}</option>
                            <option value="lunas" {{ ($statusFilter ?? '') === 'lunas' ? 'selected' : '' }}>{{ __('Lunas') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            {{ __('Filter') }}
                        </button>
                        <a href="{{ route('debts.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
        @endif

        @if (($statusFilter ?? 'outstanding') === 'outstanding')
            <div class="card-modern p-6 mb-6">
                <p class="text-sm text-slate-600">{{ __('Total Utang Belum Terbayar') }}</p>
                <p class="text-2xl font-bold text-red-600">{{ number_format($totalUnpaid, 0, ',', '.') }}</p>
            </div>
        @endif

        <div class="card-modern overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No. Invoice') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Lokasi') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Distributor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Tanggal') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Jatuh Tempo') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Terbayar') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Sisa Utang') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/50 {{ $row->is_due_soon ? 'bg-red-50' : '' }}">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('purchases.show', ['purchase' => $row->purchase, 'view' => 'detail']) }}" class="text-indigo-600 hover:underline">{{ $row->purchase->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row->purchase->warehouse_id)
                                        {{ __('Gudang') }}: {{ $row->purchase->warehouse?->name ?? '-' }}
                                    @else
                                        {{ __('Cabang') }}: {{ $row->purchase->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row->purchase->distributor?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row->purchase->purchase_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 {{ $row->is_due_soon ? 'font-semibold text-red-600' : '' }}">
                                    {{ $row->due_date?->format('d/m/Y') ?? '-' }}
                                    @if ($row->is_due_soon && $row->days_until_due !== null)
                                        <span class="text-xs">({{ $row->days_until_due }} hari)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->purchase->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->purchase->total_paid ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">{{ number_format($row->remaining, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2 justify-center">
                                        @if ($row->remaining > 0)
                                            <a href="{{ route('purchases.show', ['purchase' => $row->purchase, 'view' => 'detail']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                                {{ __('Detail & Bayar') }}
                                            </a>
                                        @endif
                                        <button type="button" class="btn-history inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-indigo-100 text-indigo-700 text-sm font-medium hover:bg-indigo-200" data-purchase-id="{{ $row->purchase->id }}">
                                            {{ __('Riwayat Bayar') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-500">{{ __('Tidak ada data utang.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $purchases->links() }}</div>
            </div>
        </div>
    </div>

    {{-- Modal Riwayat Pembayaran --}}
    <div id="history-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="history-modal-backdrop"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="history-modal-content" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                {{-- Content loaded via fetch --}}
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const baseUrl = '{{ url("") }}';
        const historyModal = document.getElementById('history-modal');
        const historyModalBackdrop = document.getElementById('history-modal-backdrop');

        document.querySelectorAll('.btn-history').forEach(btn => {
            btn.addEventListener('click', async function() {
                const purchaseId = this.dataset.purchaseId;
                const response = await fetch(baseUrl + '/debts/' + purchaseId + '/payment-history');
                if (!response.ok) return;
                const html = await response.text();
                const container = document.getElementById('history-modal-content');
                if (container) {
                    container.innerHTML = html;
                    document.getElementById('modal-close')?.addEventListener('click', closeHistoryModal);
                }
                historyModal?.classList.remove('hidden');
            });
        });

        historyModalBackdrop?.addEventListener('click', closeHistoryModal);
        function closeHistoryModal() {
            historyModal?.classList.add('hidden');
        }
    </script>
    @endpush
</x-app-layout>
