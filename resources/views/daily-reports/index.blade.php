<x-app-layout>
    <x-slot name="title">{{ __('Laporan Harian') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ __('Laporan Harian') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <p class="text-sm text-slate-600 mb-6">{{ __('Laporan operasional harian. Default filter tanggal hari ini.') }}</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('daily-reports.barang-masuk') }}" class="block p-6 bg-white rounded-xl shadow-sm border border-slate-100 hover:border-cyan-300 hover:shadow transition">
                <h3 class="font-semibold text-lg text-slate-800">{{ __('Laporan Barang Masuk') }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ __('Pembelian, Input Barang Masuk, Distribusi, dan Tukar Tambah') }}</p>
            </a>

            <a href="{{ route('daily-reports.user-activity') }}" class="block p-6 bg-white rounded-xl shadow-sm border border-slate-100 hover:border-cyan-300 hover:shadow transition">
                <h3 class="font-semibold text-lg text-slate-800">{{ __('Laporan Log Pengguna') }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ __('Aktifitas input transaksi, filter user & jenis transaksi') }}</p>
            </a>

            <a href="{{ route('daily-reports.barang-terjual') }}" class="block p-6 bg-white rounded-xl shadow-sm border border-slate-100 hover:border-cyan-300 hover:shadow transition">
                <h3 class="font-semibold text-lg text-slate-800">{{ __('Laporan Barang Terjual') }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ __('Produk, unit, user penjual, waktu, status lunas') }}</p>
            </a>

            <a href="{{ route('daily-reports.service') }}" class="block p-6 bg-white rounded-xl shadow-sm border border-slate-100 hover:border-cyan-300 hover:shadow transition">
                <h3 class="font-semibold text-lg text-slate-800">{{ __('Laporan Service') }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ __('Barang masuk, keluar (selesai), dan menunggu diambil') }}</p>
            </a>

            <a href="{{ route('daily-reports.barang-rusak') }}" class="block p-6 bg-white rounded-xl shadow-sm border border-slate-100 hover:border-cyan-300 hover:shadow transition">
                <h3 class="font-semibold text-lg text-slate-800">{{ __('Laporan Barang Rusak') }}</h3>
                <p class="text-sm text-slate-600 mt-1">{{ __('Pencatatan barang rusak / dicadangkan') }}</p>
            </a>
        </div>
    </div>
</x-app-layout>
