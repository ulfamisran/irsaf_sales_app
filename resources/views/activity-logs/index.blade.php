<x-app-layout>
    <x-slot name="title">{{ __('Log Aktivitas') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Log Aktivitas') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Timeline aktivitas user dalam waktu WITA. Tambah, ubah, hapus, batal, aktifkan, dan nonaktifkan disertai nomor dokumen.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto">
        @php
            $pagerQuery = request()->except('day');
            $prevUrl = $page > 1 ? route('activity-logs.index', array_merge($pagerQuery, ['day' => $page - 1])) : null;
            $nextUrl = $page < $totalDays ? route('activity-logs.index', array_merge($pagerQuery, ['day' => $page + 1])) : null;
            $fieldClass = 'rounded-md border border-slate-300 text-sm py-1 px-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
        @endphp
        <div class="card-modern overflow-hidden mb-4">
            <form method="GET" action="{{ route('activity-logs.index') }}" class="p-3 flex flex-wrap items-end gap-2">
                <div>
                    <label for="date_from" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Dari') }}</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $dateFrom }}" class="{{ $fieldClass }}">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Sampai') }}</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $dateTo }}" class="{{ $fieldClass }}">
                </div>
                <div>
                    <label for="user_id" class="block text-xs font-medium text-slate-600 mb-1">{{ __('User') }}</label>
                    <select id="user_id" name="user_id" class="{{ $fieldClass }} max-w-[10rem]">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="action" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Aktivitas') }}</label>
                    <select id="action" name="action" class="{{ $fieldClass }}">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="module" class="block text-xs font-medium text-slate-600 mb-1">{{ __('Modul') }}</label>
                    <select id="module" name="module" class="{{ $fieldClass }} max-w-[11rem]">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($modules as $slug => $label)
                            <option value="{{ $slug }}" @selected(request('module') === $slug)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center px-3 py-1 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    {{ __('Tampilkan') }}
                </button>
            </form>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                {{ __(':count aktivitas pada :date WITA.', ['count' => $total, 'date' => \Carbon\Carbon::parse($date)->format('d/m/Y')]) }}
                @if ($truncated)
                    {{ __('Ditampilkan 1000 kejadian terakhir pada tanggal ini.') }}
                @endif
            </p>
            <div class="flex items-center gap-2 text-sm">
                @if ($prevUrl)
                    <a href="{{ $prevUrl }}" class="px-2 py-1 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50">{{ __('Sebelumnya') }}</a>
                @else
                    <span class="px-2 py-1 rounded-md border border-slate-200 text-slate-300">{{ __('Sebelumnya') }}</span>
                @endif
                <span class="text-slate-600">{{ $page }} / {{ $totalDays }}</span>
                @if ($nextUrl)
                    <a href="{{ $nextUrl }}" class="px-2 py-1 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50">{{ __('Berikutnya') }}</a>
                @else
                    <span class="px-2 py-1 rounded-md border border-slate-200 text-slate-300">{{ __('Berikutnya') }}</span>
                @endif
            </div>
        </div>

        @forelse ($groups as $hour => $items)
            <section class="mb-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">{{ $hour }} WITA</h3>
                <ol style="border-left: 2px solid #e2e8f0; margin-left: 0.75rem; padding-left: 1.25rem;">
                    @foreach ($items as $log)
                        @php
                            $time = $log->occurred_at?->timezone(\App\Support\CatatRange::TZ)->format('H:i:s');
                            $tone = match ($log->action) {
                                'delete', 'cancel', 'login_failed' => ['#fff1f2', '#be123c'],
                                'login', 'logout' => ['#eff6ff', '#1d4ed8'],
                                'create', 'activate' => ['#ecfdf5', '#047857'],
                                'deactivate' => ['#fff7ed', '#c2410c'],
                                default => ['#f8fafc', '#334155'],
                            };
                        @endphp
                        <li class="relative pb-4">
                            <span style="position:absolute; left:-1.45rem; top:0.35rem; width:0.55rem; height:0.55rem; border-radius:999px; background:#6366f1;"></span>
                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-semibold text-slate-800">{{ $time }}</span>
                                    <span style="background: {{ $tone[0] }}; color: {{ $tone[1] }};" class="rounded-full px-2 py-0.5 text-xs font-semibold">{{ $actions[$log->action] ?? $log->action }}</span>
                                    <span class="text-sm font-medium text-slate-800">{{ $log->user?->name ?? __('Tidak dikenal') }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-700">{{ $log->description }}</p>
                                @php $documentUrl = app(\App\Services\UserActivityLogger::class)->documentUrl($log); @endphp
                                @if ($documentUrl)
                                    <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-sm font-medium text-indigo-700 hover:underline">{{ __('Buka dokumen') }}</a>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">{{ $log->ip_address ?: '-' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @empty
            <div class="card-modern p-8 text-center text-slate-500">{{ __('Belum ada aktivitas pada tanggal ini.') }}</div>
        @endforelse
    </div>
</x-app-layout>
