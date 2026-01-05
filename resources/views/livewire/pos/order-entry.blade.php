<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use App\Models\Table;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    public Table $table;
    public ?Order $order = null;
    public $search = '';
    public $categoryId = null;
    
    // Properti customer name
    public $customer_name = '';
    public $editingNoteId = null;
    public $currentNote = '';

    public function mount(Table $table) {
        $this->table = $table;
        $this->loadOrder();
        
        // Ambil nama pelanggan jika order sudah ada di database
        if ($this->order) {
            $this->customer_name = $this->order->customer_name;
        }
    }

    public function loadOrder() {
        $this->order = Order::where('table_id', $this->table->id)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * Pastikan nama terupdate di DB setiap kali input berubah
     */
    public function updatedCustomerName($value) {
        if ($this->order) {
            $this->order->update([
                'customer_name' => $value
            ]);
        }
    }

    public function with(): array {
        $query = Product::where('name', 'like', '%'.$this->search.'%')->where('is_available', true);
        if ($this->categoryId) { $query->where('category_id', $this->categoryId); }

        return [
            'products' => $query->get(),
            'categories' => Category::all(),
            'order_items' => $this->order ? $this->order->order_items()->with('product')->get() : collect(),
        ];
    }

    public function setCategory($id = null) {
        $this->categoryId = $id;
        $this->search = ''; 
    }

    public function addToOrder($productId) {
        $product = Product::find($productId);
        DB::transaction(function () use ($product) {
            if (!$this->order) {
                // Saat membuat order pertama kali, pastikan customer_name ikut masuk
                $this->order = Order::create([
                    'table_id' => $this->table->id,
                    'user_id' => Auth::id(),
                    'order_number' => 'INV-' . now()->format('YmdHis'),
                    'customer_name' => $this->customer_name,
                    'status' => 'pending',
                    'subtotal' => 0, 
                    'tax' => 0, 
                    'total_final' => 0
                ]);
                $this->table->update(['status' => 'occupied']);
            }

            $item = OrderItem::where('order_id', $this->order->id)->where('product_id', $product->id)->first();
            if ($item) { 
                $item->increment('qty'); 
            } else { 
                OrderItem::create([
                    'order_id' => $this->order->id, 
                    'product_id' => $product->id, 
                    'qty' => 1, 
                    'price' => $product->price
                ]); 
            }
            
            $this->recalculateTotal();
        });
    }

    public function openNote($itemId) {
        $item = OrderItem::find($itemId);
        $this->editingNoteId = $itemId;
        $this->currentNote = $item->notes;
    }

    public function saveNote() {
        if ($this->editingNoteId) {
            OrderItem::find($this->editingNoteId)->update(['notes' => $this->currentNote]);
            $this->editingNoteId = null;
            $this->currentNote = '';
        }
    }

    public function incrementQty($itemId) {
        OrderItem::find($itemId)->increment('qty');
        $this->recalculateTotal();
    }

    public function decrementQty($itemId) {
        $item = OrderItem::find($itemId);
        if ($item->qty > 1) { 
            $item->decrement('qty'); 
        } else { 
            $item->delete(); 
        }
        $this->recalculateTotal();
    }

    private function recalculateTotal() {
        if (!$this->order) return;
        $this->order->refresh();
        $subtotal = $this->order->order_items->sum(fn($i) => $i->price * $i->qty);
        $service = $subtotal * 0.05;
        $tax = ($subtotal + $service) * 0.10;
        
        $this->order->update([
            'subtotal' => $subtotal, 
            'service_charge' => $service, 
            'tax' => $tax, 
            'total_final' => $subtotal + $service + $tax
        ]);
    }

    // Fungsi untuk diarahkan ke halaman checkout
    public function checkout() {
        if (!$this->order || $this->order->order_items->isEmpty()) return;
        
        // Simpan nama terakhir kali sebelum pindah halaman
        $this->order->update(['customer_name' => $this->customer_name]);
        
        return redirect()->route('pos.checkout', $this->table->id);
    }

    public function finishOrder() {
        return redirect()->route('pos.index');
    }
}; ?>

<div class="flex flex-col md:flex-row h-[calc(100vh-4.1rem)] bg-gray-100 overflow-hidden border-t">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <!-- KIRI: PRODUK -->
    <div class="flex-1 overflow-y-auto p-4 md:p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">Meja {{ $table->number }}</h2>
            <div class="relative w-48 md:w-64">
                <input type="text" wire:model.live="search" placeholder="Cari..." class="w-full pl-9 pr-4 py-1.5 border-none rounded-xl shadow-sm focus:ring-2 focus:ring-emerald-500 text-sm">
                <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        <!-- Filter Kategori -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2 no-scrollbar">
            <button wire:click="setCategory(null)" @class(['px-5 py-2 rounded-full font-bold text-xs transition-all shadow-sm whitespace-nowrap', 'bg-emerald-600 text-white'=> $this->categoryId === null, 'bg-white text-gray-500' => $this->categoryId !== null])>SEMUA</button>
            @foreach($categories as $cat)
                <button wire:click="setCategory({{ $cat->id }})" @class(['px-5 py-2 rounded-full font-bold text-xs transition-all shadow-sm whitespace-nowrap', 'bg-emerald-600 text-white'=> $this->categoryId == $cat->id, 'bg-white text-gray-500' => $this->categoryId != $cat->id])>{{ strtoupper($cat->name) }}</button>
            @endforeach
        </div>

        <!-- Grid Produk -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach($products as $product)
                <button wire:click="addToOrder({{ $product->id }})" class="group bg-white p-3 rounded-2xl shadow-sm hover:shadow-lg transition-all transform active:scale-95 text-left border border-transparent hover:border-emerald-500">
                    <div @class(['w-full h-24 rounded-xl mb-2 flex items-center justify-center transition-colors uppercase font-black text-xl', 'bg-blue-50 text-blue-600 group-hover:bg-blue-500 group-hover:text-white' => ($product->category_id % 3 == 0), 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white' => ($product->category_id % 3 == 1), 'bg-purple-50 text-purple-600 group-hover:bg-purple-500 group-hover:text-white' => ($product->category_id % 3 == 2)])>{{ substr($product->name, 0, 2) }}</div>
                    <h4 class="font-bold text-gray-800 text-xs h-8 overflow-hidden">{{ $product->name }}</h4>
                    <p class="text-emerald-600 font-black mt-1 text-sm">Rp {{ number_format($product->price) }}</p>
                </button>
            @endforeach
        </div>
    </div>

    <!-- KANAN: KERANJANG -->
    <div class="w-full md:w-80 lg:w-96 bg-white shadow-2xl flex flex-col h-full border-l relative">
        <!-- Input Nama Pelanggan -->
        <div class="p-4 border-b bg-gray-50 flex-none space-y-3">
            <h3 class="font-black text-gray-700 uppercase tracking-tighter text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                PELANGGAN
            </h3>
            <input type="text" wire:model.live="customer_name" placeholder="Tulis nama pelanggan..." 
                   class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-emerald-500">
        </div>

        <!-- List Pesanan -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white no-scrollbar">
            @forelse($order_items as $item)
                <div class="bg-gray-50 p-3 rounded-xl border border-gray-100 shadow-sm space-y-2">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 pr-2">
                            <p class="font-bold text-gray-800 text-[11px] leading-tight">{{ $item->product->name }}</p>
                            <p class="text-[10px] text-emerald-600 font-bold">Rp {{ number_format($item->price) }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button wire:click="decrementQty({{ $item->id }})" class="w-6 h-6 flex items-center justify-center bg-white border rounded-lg text-gray-500 hover:bg-rose-500 hover:text-white transition shadow-sm font-bold text-xs">-</button>
                            <span class="font-black text-gray-800 w-4 text-center text-[11px]">{{ $item->qty }}</span>
                            <button wire:click="incrementQty({{ $item->id }})" class="w-6 h-6 flex items-center justify-center bg-white border rounded-lg text-gray-500 hover:bg-emerald-500 hover:text-white transition shadow-sm font-bold text-xs">+</button>
                        </div>
                    </div>
                    
                    <!-- Area Catatan -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        @if($editingNoteId === $item->id)
                            <div class="flex gap-1 w-full">
                                <input type="text" wire:model="currentNote" placeholder="Catatan..." class="flex-1 px-2 py-1 bg-white border border-gray-200 rounded text-[10px] focus:ring-emerald-500">
                                <button wire:click="saveNote" class="px-2 py-1 bg-emerald-600 text-white rounded text-[10px] font-bold">OK</button>
                            </div>
                        @else
                            <button wire:click="openNote({{ $item->id }})" class="text-[9px] flex items-center gap-1 text-gray-400 hover:text-emerald-600 transition italic">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                {{ $item->notes ?? 'Tambah catatan...' }}
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-gray-300 opacity-60">
                    <p class="font-bold text-[10px] uppercase tracking-widest">Kosong</p>
                </div>
            @endforelse
        </div>

        <!-- Footer Sticky -->
        <div class="p-4 bg-gray-50 border-t flex-none shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.03)]">
            <div class="flex justify-between items-end mb-3">
                <div>
                    <span class="text-gray-400 font-bold uppercase text-[9px] tracking-widest">Total Bayar</span>
                    <div class="text-xl font-black text-gray-800 leading-none">Rp {{ number_format($this->order?->total_final ?? 0) }}</div>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('pos.index') }}" class="py-2 text-center bg-white border text-gray-500 rounded-xl font-bold text-[10px]">KEMBALI</a>
                    <button wire:click="finishOrder" @if(!$this->order || $this->order->order_items->isEmpty()) disabled @endif class="py-2 bg-white border border-emerald-500 text-emerald-600 rounded-xl font-bold text-[10px] disabled:opacity-50">SIMPAN</button>
                </div>
                @if($this->order && !$this->order->order_items->isEmpty())
                    <!-- Ganti dari <a> ke <button> wire:click agar lebih stabil -->
                    <button wire:click="checkout" class="block w-full py-3 bg-emerald-600 text-white text-center rounded-xl font-black shadow-lg uppercase text-xs">
                        BAYAR SEKARANG
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>