<?php
use Livewire\Volt\Component;
use App\Models\Table;
use App\Models\Order;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    public function with(): array {
        return [
            // Kita ambil meja beserta order pending-nya untuk memunculkan nama pelanggan
            'tables' => Table::with(['orders' => function($q) {
                $q->where('status', 'pending');
            }])->orderBy('number', 'asc')->get(),
            
            // Statistik singkat untuk header
            'stats' => [
                'total' => Table::count(),
                'occupied' => Table::where('status', 'occupied')->count(),
                'available' => Table::where('status', 'available')->count(),
            ]
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-7xl mx-auto p-4 md:p-6">
        
        <!-- HEADER DASHBOARD -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-800 tracking-tighter uppercase">Dashboard Meja</h1>
                <p class="text-sm text-gray-500 font-medium italic">Pantau status meja dan pesanan pelanggan secara real-time</p>
            </div>
            
            <!-- Ringkasan Status -->
            <div class="flex gap-3 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                <div class="px-4 py-2 text-center border-r border-gray-50">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Tersedia</p>
                    <p class="text-xl font-black text-emerald-600">{{ $stats['available'] }}</p>
                </div>
                <div class="px-4 py-2 text-center">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Terisi</p>
                    <p class="text-xl font-black text-rose-500">{{ $stats['occupied'] }}</p>
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
                       'relative group flex flex-col items-center justify-center p-6 rounded-[1.5rem] transition-all duration-300 transform active:scale-95 shadow-sm border-2',
                       'bg-white border-transparent hover:border-emerald-500' => !$isOccupied,
                       'bg-white border-rose-500 shadow-xl shadow-rose-100' => $isOccupied,
                   ])>
                   
                    
                    <!-- Ikon Meja / Status Circle -->
                    <div @class([
                        'w-16 h-16 rounded-full flex items-center justify-center mb-4 transition-all duration-500',
                        'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white' => !$isOccupied,
                        'bg-rose-500 text-white animate-pulse' => $isOccupied,
                    ])>
                    
                        <span class="text-2xl font-black">{{ $table->number }}</span>
                    </div>

                    <div class="text-center">
                        <p @class([
                            'text-[10px] font-black uppercase tracking-widest mb-1',
                            'text-gray-300 group-hover:text-emerald-500' => !$isOccupied,
                            'text-rose-500' => $isOccupied,
                        ])>
                            {{ $isOccupied ? 'Terisi' : 'Kosong' }}
                        </p>
                        
                        
                        <!-- NAMA PELANGGAN (Bintang Utama Kita) -->
                        @if($isOccupied && $activeOrder)
                            <h4 class="text-sm font-black text-gray-800 leading-tight truncate max-w-[120px]">
                                {{ $activeOrder->customer_name ?? 'Tanpa Nama' }}
                            </h4>
                            <p class="text-[9px] font-bold text-gray-400 mt-1">
                                Rp {{ number_format($activeOrder->total_final) }}
                            </p>
                        @else
                            <h4 class="text-sm font-bold text-gray-300 group-hover:text-emerald-500 transition-colors">
                                Meja Baru
                            </h4>
                        @endif
                    </div>

                    <!-- Indikator Waktu Masuk (Jika Terisi) -->
                    @if($isOccupied && $activeOrder)
                        <div class="absolute top-4 right-6">
                            <span class="text-[9px] font-black text-rose-300 uppercase">
                                {{ $activeOrder->created_at->format('H:i') }}
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