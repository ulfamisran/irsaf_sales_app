<x-app-layout>
    <x-slot name="title">{{ __('Cut-off Verifikasi') }}</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Cut-off Verifikasi') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Transaksi dengan tanggal transaksi pada atau sebelum tanggal ini tidak perlu diverifikasi dan tidak ditampilkan.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto">
        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('verification-cutoff.update') }}" class="card-modern p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="cutoff_date" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Tanggal cut-off') }}</label>
                <input id="cutoff_date" type="date" name="cutoff_date" value="{{ old('cutoff_date', $cutoffDate) }}" class="rounded-lg border border-slate-300 shadow-sm">
                @error('cutoff_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-sm text-slate-500">{{ __('Kosongkan tanggal lalu simpan jika semua transaksi harus diverifikasi.') }}</p>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">{{ __('Simpan') }}</button>
        </form>
    </div>
</x-app-layout>
