<x-app-layout>
    <x-slot name="title">{{ __('Detail Servis') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Service') }}: {{ $service->invoice_number }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('services.invoice', $service) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Print Invoice') }}
                </a>
                @if ($service->status === \App\Models\Service::STATUS_OPEN)
                    <a href="{{ route('services.edit', $service) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
                        {{ __('Edit') }}
                    </a>
                    <form method="POST" action="{{ route('services.cancel', $service) }}" onsubmit="return confirm('{{ __('Batalkan service ini?') }}')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                            {{ __('Batalkan') }}
                        </button>
                    </form>
                @endif
                <x-icon-btn-back :href="route('services.index')" :label="__('Kembali')" />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div><p class="text-sm text-gray-500">{{ __('Cabang') }}</p><p class="font-medium">{{ $service->branch?->name }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Status') }}</p>
                            <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $service->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($service->status === 'cancel' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ $service->status === 'cancel' ? __('Dibatalkan') : ($service->status === 'completed' ? __('Selesai') : __('Open')) }}
                            </span>
                            <span class="ml-1 px-2 py-1 rounded-lg text-xs font-medium {{ $service->isPaidOff() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $service->isPaidOff() ? __('Lunas') : __('Belum Lunas') }}
                            </span>
                        </div>
                        <div><p class="text-sm text-gray-500">{{ __('Tanggal Masuk') }}</p><p class="font-medium">{{ $service->entry_date->format('d/m/Y') }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Tanggal Keluar') }}</p><p class="font-medium">{{ $service->exit_date?->format('d/m/Y') ?? '-' }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Pelanggan') }}</p><p class="font-medium">{{ $service->customer?->name ?? '-' }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('User') }}</p><p class="font-medium">{{ $service->user?->name }}</p></div>
                        <div class="md:col-span-2"><p class="text-sm text-gray-500">{{ __('Jenis Laptop') }}</p><p class="font-medium">{{ $service->laptop_type }}</p></div>
                        <div class="md:col-span-2"><p class="text-sm text-gray-500">{{ __('Detail Laptop') }}</p><p class="font-medium">{{ $service->laptop_detail ?? '-' }}</p></div>
                        <div class="md:col-span-2"><p class="text-sm text-gray-500">{{ __('Kerusakan') }}</p><p class="font-medium whitespace-pre-line">{{ $service->damage_description ?? '-' }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Biaya Service') }}</p><p class="font-medium">{{ number_format($service->service_cost, 0, ',', '.') }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Harga Service') }}</p><p class="font-medium">{{ number_format($service->service_price, 0, ',', '.') }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Status Pengambilan') }}</p>
                            <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $service->pickup_status === 'sudah_diambil' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $service->pickup_status === 'sudah_diambil' ? __('Sudah Diambil') : __('Belum Diambil') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-sm font-semibold text-gray-800">{{ __('Pembayaran') }}</p>
                        <div class="mt-2 space-y-1 text-sm text-gray-700">
                            @forelse ($service->payments as $p)
                                <div class="flex justify-between">
                                    <span>{{ $p->paymentMethod?->display_label }}</span>
                                    <span class="font-medium">{{ number_format($p->amount, 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <div class="text-gray-500">-</div>
                            @endforelse
                            <div class="flex justify-between pt-2 border-t">
                                <span class="font-semibold">{{ __('Total Dibayar') }}</span>
                                <span class="font-semibold">{{ number_format($service->total_paid, 0, ',', '.') }}</span>
                            </div>
                            @if (!$service->isPaidOff())
                                <div class="text-amber-700">{{ __('Sisa') }}: {{ number_format((float)$service->service_price - (float)$service->total_paid, 0, ',', '.') }}</div>
                            @endif
                        </div>
                    </div>

                    @if ($service->status === 'open')
                        <div class="mt-8 border rounded-lg p-4 bg-slate-50">
                            <p class="font-semibold text-slate-800">{{ __('Tambah Pembayaran / Pelunasan') }}</p>
                            <form method="POST" action="{{ route('services.add-payment', $service) }}" class="mt-4">
                                @csrf
                                <div id="add-payment-rows" class="space-y-2"></div>
                                <button type="button" id="add-payment-btn" class="mt-2 inline-flex items-center px-3 py-2 rounded-md bg-white border border-slate-200 text-sm hover:bg-slate-100">+ {{ __('Tambah') }}</button>
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ __('Tanggal Keluar') }}</label>
                                        <input type="date" name="exit_date" value="{{ old('exit_date', $service->exit_date?->toDateString()) }}" class="block mt-1 w-full rounded-md border-gray-300">
                                    </div>
                                    <div class="flex items-center gap-4 pt-6">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="mark_completed" value="1" class="rounded">
                                            <span class="text-sm">{{ __('Tandai Selesai') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="mark_picked_up" value="1" class="rounded">
                                            <span class="text-sm">{{ __('Sudah Diambil') }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                        {{ __('Simpan Pembayaran') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="mt-4">
                            <form method="POST" action="{{ route('services.complete', $service) }}">
                                @csrf
                                <input type="hidden" name="mark_picked_up" value="0">
                                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-medium hover:bg-slate-700">
                                    {{ __('Tandai Selesai (tanpa tambah pembayaran)') }}
                                </button>
                            </form>
                        </div>

                        @if ($service->pickup_status === 'belum_diambil' && $service->status === 'completed')
                            <div class="mt-4">
                                <form method="POST" action="{{ route('services.mark-picked-up', $service) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                        {{ __('Tandai Sudah Diambil') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    @php
        $showPaymentMethods = ($paymentMethods ?? collect())->map(fn ($m) => ['id' => $m->id, 'label' => $m->display_label])->values()->toArray();
    @endphp
    <script>
        const showPaymentMethods = @json($showPaymentMethods);
        const addPaymentRows = document.getElementById('add-payment-rows');
        let addPaymentIdx = 0;
        function addPaymentRowHtml() {
            return '<option value="">Pilih</option>' + showPaymentMethods.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
        }
        document.getElementById('add-payment-btn')?.addEventListener('click', function() {
            if (!addPaymentRows) return;
            const i = addPaymentIdx++;
            const div = document.createElement('div');
            div.className = 'flex gap-2 items-end';
            div.innerHTML = `
                <div class="flex-1">
                    <select name="payments[${i}][payment_method_id]" class="block w-full rounded-md border-gray-300" required>
                        ${addPaymentRowHtml()}
                    </select>
                </div>
                <div class="w-40">
                    <input type="number" name="payments[${i}][amount]" step="0.01" min="0.01" class="block w-full rounded-md border-gray-300" placeholder="Nominal" required>
                </div>
                <button type="button" class="remove-add-payment px-3 py-2 bg-red-100 text-red-700 rounded">-</button>
            `;
            addPaymentRows.appendChild(div);
            div.querySelector('.remove-add-payment')?.addEventListener('click', () => div.remove());
        });
        if (addPaymentRows && addPaymentRows.children.length === 0) {
            document.getElementById('add-payment-btn')?.click();
        }
    </script>
</x-app-layout>
