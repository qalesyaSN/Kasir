<?php

use Livewire\Volt\Component;
use App\Models\Table;
use Livewire\Attributes\Layout;

new 
#[Layout('layouts.app')] 
class extends Component {
    
    public function with(): array
    {
        return [
            'tables' => Table::orderBy('number', 'asc')->get(),
        ];
    }

    // Di dalam class Volt komponen dashboard:
   public function selectTable($tableId)
{
    // Meja hijau atau merah, semua masuk ke halaman Order Entry
    return redirect()->route('pos.order', ['table' => $tableId]);
}


}; ?>

<div class="p-6 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-black text-gray-800">Denah Meja</h1>
                <p class="text-sm text-gray-500">Klik meja untuk memulai pesanan atau bayar</p>
            </div>
            <div class="flex gap-4 text-xs font-bold uppercase">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full"></span> Tersedia
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 bg-rose-500 rounded-full"></span> Terisi
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
            @foreach($tables as $table)
                <button 
                    wire:click="selectTable({{ $table->id }})"
                    @class([
                        'relative group overflow-hidden rounded-2xl transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-sm hover:shadow-xl p-5 border-2',
                        'bg-white border-emerald-100' => $table->status === 'available',
                        'bg-rose-50 border-rose-100 shadow-rose-100' => $table->status === 'occupied',
                    ])
                >
                    <div @class([
                        'absolute -right-4 -top-4 opacity-10 transition-transform group-hover:rotate-12',
                        'text-emerald-600' => $table->status === 'available',
                        'text-rose-600' => $table->status === 'occupied',
                    ])>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>

                    <div class="relative z-10 text-left">
                        <span @class([
                            'text-xs font-black px-2 py-1 rounded-lg uppercase tracking-wider',
                            'bg-emerald-100 text-emerald-700' => $table->status === 'available',
                            'bg-rose-100 text-rose-700' => $table->status === 'occupied',
                        ])>
                            Meja
                        </span>
                        
                        <h3 class="text-4xl font-black mt-2 text-gray-800">
                            {{ $table->number }}
                        </h3>

                        <p @class([
                            'text-[10px] mt-4 font-bold uppercase tracking-widest',
                            'text-emerald-500' => $table->status === 'available',
                            'text-rose-500' => $table->status === 'occupied',
                        ])>
                            {{ $table->status === 'available' ? 'KOSONG' : 'ORDER AKTIF' }}
                        </p>
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>
