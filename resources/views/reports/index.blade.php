<x-app-layout>
    <x-slot name="title">{{ __('Laporan') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::STAFF_GUDANG]))
                    <a href="{{ route('reports.stock-warehouse') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Stock Warehouse') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View stock levels in warehouses') }}</p>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                    <a href="{{ route('reports.stock-branch') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Stock Per Branch') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View stock levels per branch') }}</p>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::STAFF_GUDANG]))
                    <a href="{{ route('incoming-goods.index') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Incoming Goods') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View incoming goods records') }}</p>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR, \App\Models\Role::STAFF_GUDANG]))
                    <a href="{{ route('stock-mutations.index') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Stock Distribution') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View stock distribution records') }}</p>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR, \App\Models\Role::STAFF_GUDANG]))
                    <a href="{{ route('stock-inout.index') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Stock In/Out') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View stock in/out movements') }}</p>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->hasAnyRole([\App\Models\Role::ADMIN_CABANG, \App\Models\Role::KASIR]))
                    <a href="{{ route('sales.index') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Sales Report') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View sales transactions') }}</p>
                    </a>

                    <a href="{{ route('cash-flows.index') }}" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                        <h3 class="font-semibold text-lg">{{ __('Cash Flow Report') }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ __('View cash flow transactions') }}</p>
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
