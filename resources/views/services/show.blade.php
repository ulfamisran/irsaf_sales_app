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
                @if ($service->status === \App\Models\Service::STATUS_OPEN && (auth()->user()?->isSuperAdminOrAdminPusat() || auth()->user()?->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR])))
                    <a href="{{ route('services.edit', $service) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
                        {{ __('Edit') }}
                    </a>
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
                    @if ($service->status === \App\Models\Service::STATUS_CANCEL)
                        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50/50 p-4">
                            <p class="text-sm font-semibold text-rose-700">{{ __('Informasi Pembatalan') }}</p>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-700">
                                <div>
                                    <p class="text-xs text-slate-500">{{ __('Tanggal Batal') }}</p>
                                    <p class="font-medium">{{ $service->cancel_date?->format('d/m/Y') ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">{{ __('Dibatalkan Oleh') }}</p>
                                    <p class="font-medium">{{ $service->cancelUser?->name ?? '-' }}</p>
                                </div>
                                <div class="md:col-span-1">
                                    <p class="text-xs text-slate-500">{{ __('Alasan Batal') }}</p>
                                    <p class="font-medium whitespace-pre-line">{{ $service->cancel_reason ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
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
                        <div><p class="text-sm text-gray-500">{{ __('Biaya Jasa Service') }}</p><p class="font-medium">{{ number_format($service->service_price, 0, ',', '.') }}</p></div>
                        <div><p class="text-sm text-gray-500">{{ __('Status Pengambilan') }}</p>
                            <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $service->pickup_status === 'sudah_diambil' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $service->pickup_status === 'sudah_diambil' ? __('Sudah Diambil') : __('Belum Diambil') }}
                            </span>
                        </div>
                    </div>

                    @php
                        $materials = $service->serviceMaterials ?? collect();
                        $materialsTotalPrice = (float) $service->materials_total_price;
                        $totalServicePrice = (float) $service->total_service_price;
                    @endphp

                    <div class="mt-6">
                        <p class="text-sm font-semibold text-gray-800">{{ __('Bahan/Material Service') }}</p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('No') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Material') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Harga') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Subtotal') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Catatan') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($materials as $idx => $mat)
                                        @php $subtotal = (float) $mat->price * (float) $mat->quantity; @endphp
                                        <tr>
                                            <td class="px-3 py-2">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-2 font-medium">{{ $mat->name }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format((float) $mat->quantity, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format((float) $mat->price, 0, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($subtotal, 0, ',', '.') }}</td>
                                            <td class="px-3 py-2">{{ $mat->notes ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-4 text-center text-slate-500">-</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-sm text-slate-700 space-y-1">
                            <div class="flex justify-between">
                                <span>{{ __('Total Material') }}</span>
                                <span class="font-medium">{{ number_format($materialsTotalPrice, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between font-semibold">
                                <span>{{ __('Total Service Keseluruhan') }}</span>
                                <span>{{ number_format($totalServicePrice, 0, ',', '.') }}</span>
                            </div>
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
                                <div class="text-amber-700">{{ __('Sisa') }}: {{ number_format((float)$service->total_service_price - (float)$service->total_paid, 0, ',', '.') }}</div>
                            @endif
                        </div>
                    </div>

                    @if ($service->status === 'open')
                        <div class="mt-6 p-4 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-sm text-amber-800">{{ __('Untuk menambah material dan pembayaran, gunakan tombol Edit di atas.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()?->isSuperAdmin() && in_array($service->status, [\App\Models\Service::STATUS_OPEN, \App\Models\Service::STATUS_COMPLETED], true))
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mt-10 border border-red-200 rounded-lg p-4 bg-red-50/40">
                <p class="font-semibold text-red-700 mb-2">{{ __('Batalkan Transaksi') }}</p>
                <form method="POST" action="{{ route('services.cancel', $service) }}" onsubmit="return confirm('{{ $service->status === \App\Models\Service::STATUS_COMPLETED ? __('Transaksi sudah RELEASED. Batalkan service ini?') : __('Batalkan service ini?') }}')">
                    @csrf
                    <div class="flex flex-col gap-2 mb-3">
                        <textarea name="cancel_reason" class="w-full rounded-md border-gray-300" rows="2" placeholder="{{ __('Alasan pembatalan') }}" required></textarea>
                        @if ($service->status === \App\Models\Service::STATUS_COMPLETED)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="confirm_released" value="1" class="rounded">
                                <span>{{ __('Saya yakin membatalkan transaksi released') }}</span>
                            </label>
                        @endif
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                        {{ __('Batalkan') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

</x-app-layout>
