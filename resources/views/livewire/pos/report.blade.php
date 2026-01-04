<?php
use Livewire\Volt\Component;
use App\Models\Order;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    public function getStatsProperty() {
        $today = now()->toDateString();
        $query = Order::where('status', 'paid')->whereDate('created_at', $today);
        
        return [
            'total_revenue' => $query->sum('total_final'),
            'order_count' => $query->count(),
            'total_tax' => $query->sum('tax'),
            'total_service' => $query->sum('service_charge'),
            'pure_sales' => $query->sum('subtotal'),
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-5xl mx-auto p-6">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <h1 class="text-4xl font-black text-gray-800 tracking-tighter uppercase">Laporan Harian</h1>
                <p class="text-gray-500 font-medium">Ringkasan performa penjualan tanggal {{ now()->format('d M Y') }}</p>
            </div>
            
            <a href="{{ route('pos.report.print') }}" target="_blank" 
               class="group bg-gray-900 text-white px-8 py-4 rounded-2xl font-black flex items-center gap-3 hover:bg-black transition-all shadow-xl shadow-gray-200 active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-400 group-hover:rotate-12 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                CETAK CLOSING
            </a>
        </div>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Revenue Card -->
            <div class="bg-emerald-600 p-6 rounded-3xl shadow-xl shadow-emerald-100 text-white relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-10 group-hover:scale-110 transition-transform">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.82v-1.91c-1.61-.31-3.13-1.12-4.14-2.31l1.45-1.45c.78.89 1.84 1.48 2.69 1.72v-3.41c-1.84-.52-3.84-1.14-3.84-3.41 0-1.84 1.34-3.11 3.29-3.46V4h2.82v1.91c1.51.34 2.67 1.13 3.49 2.15l-1.45 1.45c-.53-.66-1.19-1.07-2.04-1.25v3.21c1.84.52 3.84 1.14 3.84 3.41 0 1.95-1.39 3.19-3.29 3.46z"/></svg>
                </div>
                <p class="text-xs font-bold uppercase opacity-80 tracking-widest mb-1">Total Pendapatan</p>
                <h3 class="text-2xl font-black">Rp {{ number_format($this->stats['total_revenue']) }}</h3>
                <p class="text-[10px] mt-4 font-medium opacity-70 italic">*Sudah termasuk pajak & service</p>
            </div>

            <!-- Orders Card -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 group">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Pesanan Selesai</p>
                <div class="flex items-end gap-2">
                    <h3 class="text-3xl font-black text-gray-800">{{ $this->stats['order_count'] }}</h3>
                    <span class="text-xs font-bold text-gray-400 pb-1 uppercase">Nota</span>
                </div>
                <div class="mt-4 flex gap-1">
                    <div class="h-1 flex-1 bg-blue-100 rounded-full"></div>
                    <div class="h-1 flex-1 bg-blue-500 rounded-full"></div>
                    <div class="h-1 flex-1 bg-blue-100 rounded-full"></div>
                </div>
            </div>

            <!-- Clean Sales Card -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Penjualan Bersih</p>
                <h3 class="text-xl font-black text-gray-800">Rp {{ number_format($this->stats['pure_sales']) }}</h3>
                <p class="text-[10px] mt-2 text-emerald-500 font-bold uppercase">+ Estimasi Stok Keluar</p>
            </div>

            <!-- Tax & Service Card -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Pajak & Service</p>
                <h3 class="text-xl font-black text-rose-500">Rp {{ number_format($this->stats['total_tax'] + $this->stats['total_service']) }}</h3>
                <div class="flex justify-between mt-2 text-[9px] font-bold text-gray-400 uppercase border-t pt-2">
                    <span>Tax: {{ number_format($this->stats['total_tax']) }}</span>
                    <span>Svc: {{ number_format($this->stats['total_service']) }}</span>
                </div>
            </div>
        </div>

        <!-- TRANSACTION TABLE -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-white">
                <h3 class="font-black text-gray-800 uppercase tracking-widest text-xs">10 Transaksi Terakhir</h3>
                <span class="px-3 py-1 bg-emerald-100 text-emerald-600 text-[10px] font-black rounded-lg">LIVE UPDATING</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50/50 text-[10px] uppercase text-gray-400 font-black tracking-widest">
                            <th class="px-8 py-4">Waktu</th>
                            <th class="px-8 py-4 text-center">Meja</th>
                            <th class="px-8 py-4">No. Nota</th>
                            <th class="px-8 py-4">Metode</th>
                            <th class="px-8 py-4 text-right">Total Transaksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse(App\Models\Order::where('status', 'paid')->whereDate('created_at', now()->toDateString())->latest()->take(10)->get() as $item)
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            <td class="px-8 py-5 text-sm font-medium text-gray-500">{{ $item->created_at->format('H:i') }}</td>
                            <td class="px-8 py-5 text-center">
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-black">
                                    {{ $item->table->number }}
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <p class="text-sm font-black text-gray-800">{{ $item->order_number }}</p>
                            </td>
                            <td class="px-8 py-5">
                                <span @class([
                                    'text-[10px] font-black px-2 py-1 rounded-lg uppercase tracking-wider',
                                    'bg-emerald-100 text-emerald-700' => $item->payment_method == 'cash',
                                    'bg-blue-100 text-blue-700' => $item->payment_method != 'cash',
                                ])>
                                    {{ $item->payment_method ?? 'CASH' }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right font-black text-gray-900 text-base group-hover:text-emerald-600 transition-colors">
                                Rp {{ number_format($item->total_final) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-8 py-20 text-center">
                                <p class="text-gray-400 font-bold italic">Belum ada transaksi hari ini.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="p-6 bg-gray-50/50 border-t border-gray-50 text-center">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Semua data disinkronkan secara real-time dari database</p>
            </div>
        </div>
    </div>
</div>