@if ($logs->isNotEmpty())
    <div class="mt-8 border-t border-slate-200 pt-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">{{ __('Riwayat Update') }}</h3>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Waktu') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Oleh') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Alasan') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Perubahan') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-700">{{ $log->updated_at_action?->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $log->user?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700 whitespace-pre-line">{{ $log->reason }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">
                                @php $changes = $log->changes ?? []; @endphp
                                @if (!empty($changes['header']))
                                    <p class="font-medium text-slate-700 mb-1">{{ __('Data operasional') }}</p>
                                    <ul class="list-disc list-inside space-y-0.5 mb-2">
                                        @foreach ($changes['header'] as $field => $pair)
                                            <li>
                                                <span class="font-mono">{{ $field }}</span>:
                                                <span class="text-rose-600">{{ is_array($pair['old'] ?? null) ? json_encode($pair['old']) : ($pair['old'] ?? '-') }}</span>
                                                →
                                                <span class="text-emerald-700">{{ is_array($pair['new'] ?? null) ? json_encode($pair['new']) : ($pair['new'] ?? '-') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (!empty($changes['payments']))
                                    <p class="font-medium text-slate-700 mb-1">{{ __('Pembayaran') }}</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($changes['payments'] as $pay)
                                            <li>
                                                #{{ $pay['payment_id'] ?? '-' }}
                                                @foreach (($pay['changes'] ?? []) as $field => $pair)
                                                    <div class="ml-4">
                                                        <span class="font-mono">{{ $field }}</span>:
                                                        <span class="text-rose-600">{{ $pair['old'] ?? '-' }}</span>
                                                        →
                                                        <span class="text-emerald-700">{{ $pair['new'] ?? '-' }}</span>
                                                    </div>
                                                @endforeach
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (empty($changes['header']) && empty($changes['payments']))
                                    <pre class="whitespace-pre-wrap text-[11px]">{{ json_encode($changes, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
