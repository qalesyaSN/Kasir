<?php
use Livewire\Volt\Component;
use App\Models\Table;
use App\Models\Order;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    public function with(): array {
        return [
            'tables' => Table::with(['orders' => function($q) {
                $q->where('status', 'pending');
            }])->orderBy('number', 'asc')->get(),
            
            'stats' => [
                'total' => Table::count(),
                'occupied' => Table::where('status', 'occupied')->count(),
                'available' => Table::where('status', 'available')->count(),
            ]
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-20 transition-colors duration-500">
    <div class="max-w-7xl mx-auto p-4 md:p-6">
        
        <!-- HEADER DASHBOARD -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase">Dashboard Meja</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium italic">Pantau status meja secara real-time</p>
            </div>
            
            <!-- Ringkasan Status -->
            <div class="flex gap-3 bg-white dark:bg-gray-900 p-2 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="px-4 py-2 text-center border-r border-gray-50 dark:border-gray-800">
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Tersedia</p>
                    <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ $stats['available'] }}</p>
                </div>
                <div class="px-4 py-2 text-center">
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Terisi</p>
                    <p class="text-xl font-black text-rose-500 dark:text-rose-400">{{ $stats['occupied'] }}</p>
                </div>
            </div>
        </div>

        <!-- GRID MEJA -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 md:gap-6">
            @foreach($tables as $table)
                @php
                    $activeOrder = $table->orders->first();
                    $isOccupied = $table->status === 'occupied';
                @endphp

                <a href="{{ route('pos.order', $table->id) }}" 
                   @class([
                       'relative group flex flex-col items-center justify-center p-6 rounded-[2.5rem] transition-all duration-300 transform active:scale-95 shadow-sm border-2',
                       'bg-white dark:bg-gray-900 border-transparent hover:border-emerald-500' => !$isOccupied,
                       'bg-white dark:bg-gray-900 border-rose-500 shadow-xl shadow-rose-100 dark:shadow-rose-900/20' => $isOccupied,
                   ])>
                    
                    <!-- AREA IKON & NOMOR -->
                    <div @class([
                        'w-20 h-20 rounded-full flex flex-col items-center justify-center mb-4 transition-all duration-500 shadow-inner',
                        'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white' => !$isOccupied,
                        'bg-rose-500 text-white animate-pulse' => $isOccupied,
                    ])>
                        <svg class="w-6 h-6 mb-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10m16-10v10M4 7h16M4 11h16M4 15h16" />
                        </svg>
                        <span class="text-xl font-black leading-none">{{ $table->number }}</span>
                    </div>

                    <div class="text-center">
                        <p @class([
                            'text-[10px] font-black uppercase tracking-widest mb-1',
                            'text-gray-300 dark:text-gray-600 group-hover:text-emerald-500 dark:group-hover:text-emerald-400' => !$isOccupied,
                            'text-rose-500 dark:text-rose-400' => $isOccupied,
                        ])>
                            {{ $isOccupied ? 'Terisi' : 'Kosong' }}
                        </p>
                        
                        @if($isOccupied && $activeOrder)
                            <h4 class="text-sm font-black text-gray-800 dark:text-gray-100 leading-tight truncate max-w-[120px]">
                                {{ $activeOrder->customer_name ?? 'Tanpa Nama' }}
                            </h4>
                            <p class="text-[9px] font-bold text-gray-400 dark:text-gray-500 mt-1">
                                Rp {{ number_format($activeOrder->total_final) }}
                            </p>
                        @else
                            <h4 class="text-sm font-bold text-gray-300 dark:text-gray-700 group-hover:text-emerald-500 transition-colors italic">
                                Siap Digunakan
                            </h4>
                        @endif
                    </div>

                    @if($isOccupied && $activeOrder)
                        <div class="absolute top-6 right-8">
                            <span class="text-[9px] font-black text-rose-300 dark:text-rose-600 uppercase">
                                {{ $activeOrder->created_at->format('H:i') }}
                            </span>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
    
    
        <!-- Tombol Floating Akses Cepat (Optional) -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3">
        <a href="{{ route('pos.report') }}" class="w-14 h-14 bg-gray-900 text-white rounded-2xl flex items-center justify-center shadow-2xl hover:bg-black transition-all group">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14" />
            </svg>
        </a>
    </div>
</div>