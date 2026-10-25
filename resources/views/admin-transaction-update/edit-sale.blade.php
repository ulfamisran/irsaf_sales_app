<x-app-layout>
    <x-slot name="title">{{ __('Update Penjualan') }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Update Penjualan') }}</h2>
                <p class="text-sm text-slate-600 mt-1">{{ $sale->invoice_number }}</p>
            </div>
            <x-icon-btn-back :href="route('sales.show', $sale)" :label="__('Kembali')" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                {{ __('Perubahan metode/nominal pembayaran akan disinkronkan ke kas (cash flow) agar saldo tetap balance. Wajib isi alasan update.') }}
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin-tx.update-sale', $sale) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="sale_date" :value="__('Tanggal Penjualan')" />
                                <x-text-input id="sale_date" type="date" name="sale_date" class="block mt-1 w-full" :value="old('sale_date', optional($sale->sale_date)->format('Y-m-d'))" required />
                            </div>
                            <div>
                                <x-input-label for="description" :value="__('Keterangan')" />
                                <x-text-input id="description" type="text" name="description" class="block mt-1 w-full" :value="old('description', $sale->description)" />
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-slate-800 mb-3">{{ __('Pembayaran') }}</h3>
                            @forelse ($sale->payments as $i => $payment)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3 p-3 rounded-lg border border-slate-200">
                                    <input type="hidden" name="payments[{{ $i }}][id]" value="{{ $payment->id }}">
                                    <div>
                                        <x-input-label :value="__('Metode')" />
                                        <select name="payments[{{ $i }}][payment_method_id]" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            @foreach ($paymentMethods as $pm)
                                                <option value="{{ $pm->id }}" @selected((int) old("payments.$i.payment_method_id", $payment->payment_method_id) === (int) $pm->id)>{{ $pm->display_label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Nominal')" />
                                        <x-text-input type="text" name="payments[{{ $i }}][amount]" data-rupiah="true" class="block mt-1 w-full text-right" :value="old('payments.'.$i.'.amount', $payment->amount)" required />
                                    </div>
                                    <div>
                                        <x-input-label :value="__('Catatan')" />
                                        <x-text-input type="text" name="payments[{{ $i }}][notes]" class="block mt-1 w-full" :value="old('payments.'.$i.'.notes', $payment->notes)" />
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">{{ __('Belum ada pembayaran.') }}</p>
                            @endforelse
                        </div>

                        <div>
                            <x-input-label for="reason" :value="__('Alasan update')" />
                            <textarea id="reason" name="reason" rows="3" required class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Wajib diisi untuk jejak audit') }}">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>

                        <div class="flex gap-3">
                            <x-primary-button type="submit">{{ __('Simpan Update') }}</x-primary-button>
                            <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-300">{{ __('Batal') }}</a>
                        </div>
                    </form>

                    @include('admin-transaction-update.partials.update-logs', ['logs' => $logs])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
