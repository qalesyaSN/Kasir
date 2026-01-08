<?php
use Livewire\Volt\Component;
use App\Models\Product;
use Livewire\Attributes\Layout;

new class extends Component {
    #[Layout('layouts.monitor')] 
    public function with(): array {
        return [
            'sold_out' => Product::where('is_available', false)
                                ->orWhere(function($q) {
                                    $q->where('track_stock', true)->where('stock', '<=', 0);
                                })->get(),
            
            'low_stock' => Product::where('track_stock', true)
                                 ->where('stock', '>', 0)
                                 ->where('stock', '<=', 5)
                                 ->get(),
        ];
    }
}; ?>

<div class="min-h-screen bg-[#0a0a0a] text-zinc-400 p-6 font-sans antialiased" wire:poll.10s>
    
    <!-- HEADER RINGKAS -->
    <div class="flex justify-between items-center mb-8 border-b border-zinc-800/50 pb-5">
        <div class="flex items-center gap-4">
            <div class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </div>
            <h1 class="text-xl font-extrabold tracking-[0.05em] text-white uppercase">Monitor Ketersediaan Menu</h1>
        </div>
        <div class="text-right">
            <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-[0.2em] mb-1">Terakhir Update</p>
            <p class="text-sm font-black text-zinc-300 tabular-nums">{{ now()->format('H:i:s') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        
        <!-- KOLOM SOLD OUT (HABIS) -->
        <div class="space-y-5">
            <div class="flex items-center justify-between px-2">
                <div class="flex items-center gap-3">
                    <span class="w-1.5 h-5 bg-rose-600 rounded-full shadow-[0_0_10px_rgba(225,29,72,0.5)]"></span>
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-rose-500">Menu Habis ({{ count($sold_out) }})</h2>
                </div>
            </div>

            <div class="bg-zinc-900/40 rounded-[2rem] border border-zinc-800/50 overflow-hidden backdrop-blur-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] uppercase text-zinc-500 border-b border-zinc-800/50 bg-zinc-900/80">
                            <th class="px-6 py-4 font-black tracking-[0.15em]">Nama Menu</th>
                            <th class="px-6 py-4 font-black tracking-[0.15em]">Kategori</th>
                            <th class="px-6 py-4 text-right font-black tracking-[0.15em]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/50">
                        @forelse($sold_out as $item)
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="text-base font-bold text-zinc-100 group-hover:text-rose-400 transition-colors">{{ $item->name }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-[0.1em]">{{ $item->category->name ?? '-' }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-[9px] font-black bg-rose-500/10 text-rose-500 px-3 py-1.5 rounded-lg border border-rose-500/20 uppercase tracking-[0.1em]">Kosong</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-16 text-center">
                                    <p class="text-xs text-zinc-600 font-bold uppercase tracking-[0.3em] italic">Semua menu tersedia</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KOLOM LOW STOCK (LIMIT) -->
        <div class="space-y-5">
            <div class="flex items-center justify-between px-2">
                <div class="flex items-center gap-3">
                    <span class="w-1.5 h-5 bg-amber-500 rounded-full shadow-[0_0_10px_rgba(245,158,11,0.5)]"></span>
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-amber-500">Stok Menipis ({{ count($low_stock) }})</h2>
                </div>
            </div>

            <div class="bg-zinc-900/40 rounded-[2rem] border border-zinc-800/50 overflow-hidden backdrop-blur-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] uppercase text-zinc-500 border-b border-zinc-800/50 bg-zinc-900/80">
                            <th class="px-6 py-4 font-black tracking-[0.15em]">Nama Menu</th>
                            <th class="px-6 py-4 font-black text-center tracking-[0.15em]">Sisa</th>
                            <th class="px-6 py-4 text-right font-black tracking-[0.15em]">Level</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/50">
                        @forelse($low_stock as $item)
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="text-base font-bold text-zinc-100 group-hover:text-amber-400 transition-colors">{{ $item->name }}</span>
                                    <p class="text-[9px] text-zinc-600 font-bold uppercase tracking-[0.1em] mt-0.5">{{ $item->category->name ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-2xl font-black text-amber-500 tabular-nums drop-shadow-[0_0_8px_rgba(245,158,11,0.3)]">{{ $item->stock }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        @for($i=0; $i < 5; $i++)
                                            <div @class([
                                                'w-1.5 h-4 rounded-full transition-all duration-500',
                                                'bg-amber-500 shadow-[0_0_5px_rgba(245,158,11,0.5)]' => $i < $item->stock,
                                                'bg-zinc-800' => $i >= $item->stock
                                            ])></div>
                                        @endfor
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-16 text-center">
                                    <p class="text-xs text-zinc-600 font-bold uppercase tracking-[0.3em] italic">Stok menu aman</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- FOOTER MODERN -->
    <div class="fixed bottom-0 left-0 right-0 bg-zinc-900/80 backdrop-blur-md border-t border-zinc-800 px-8 py-3 flex justify-between items-center text-[10px] font-bold text-zinc-500 uppercase tracking-[0.25em]">
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                System Operational
            </span>
            <span class="h-3 w-px bg-zinc-800"></span>
            <span>v2.6.0 Stable</span>
        </div>
        <span class="text-zinc-600 tracking-widest">© {{ date('Y') }} Production Monitoring</span>
    </div>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        table { width: 100%; border-spacing: 0; }
        
        /* Memberikan ruang pada setiap huruf kapital agar tidak "dempet" */
        .uppercase {
            letter-spacing: 0.125em; /* Default tracking-wider */
        }
    </style>
</div>