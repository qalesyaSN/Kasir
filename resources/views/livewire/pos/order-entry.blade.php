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

    public function mount(Table $table) {
        $this->table = $table;
        $this->loadOrder();
    }

    public function loadOrder() {
        $this->order = Order::where('table_id', $this->table->id)
            ->where('status', 'pending')
            ->first();
    }

    public function with(): array {
        $query = Product::where('name', 'like', '%'.$this->search.'%')
                        ->where('is_available', true);

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

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
                $this->order = Order::create([
                    'table_id' => $this->table->id,
                    'user_id' => Auth::id(),
                    'order_number' => 'INV-' . now()->format('YmdHis'),
                    'status' => 'pending',
                    'subtotal' => 0, 'tax' => 0, 'total_final' => 0
                ]);
                $this->table->update(['status' => 'occupied']);
            }

            $item = OrderItem::where('order_id', $this->order->id)
                             ->where('product_id', $product->id)
                             ->first();

            if ($item) {
                $item->increment('qty');
            } else {
                OrderItem::create([
                    'order_id' => $this->order->id,
                    'product_id' => $product->id,
                    'qty' => 1,
                    'price' => $product->price,
                ]);
            }

            $this->recalculateTotal();
        });
    }

    public function incrementQty($itemId) {
        $item = OrderItem::find($itemId);
        $item->increment('qty');
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
            'total_final' => $subtotal + $service + $tax,
        ]);
    }

    public function finishOrder() {
        return redirect()->route('pos.index')->with('success', 'Pesanan berhasil diperbarui');
    }
}; ?>

<!-- Container utama menggunakan calc(100vh - navbar) agar tidak terpotong di bawah -->
<div class="flex flex-col md:flex-row h-[calc(100vh-4.1rem)] bg-gray-100 overflow-hidden border-t">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <!-- KIRI: KATALOG PRODUK -->
    <div class="flex-1 overflow-y-auto p-4 md:p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">Meja {{ $table->number }}</h2>
            <div class="relative w-48 md:w-64">
                <input type="text" wire:model.live="search" placeholder="Cari..." 
                       class="w-full pl-9 pr-4 py-1.5 border-none rounded-xl shadow-sm focus:ring-2 focus:ring-emerald-500 text-sm">
                <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>

        <!-- Filter Kategori -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2 no-scrollbar">
            <button wire:click="setCategory(null)"
                @class([
                    'px-5 py-2 rounded-full font-bold text-xs transition-all shadow-sm whitespace-nowrap',
                    'bg-emerald-600 text-white'=> $this->categoryId === null,
                    'bg-white text-gray-500 hover:bg-emerald-50' => $this->categoryId !== null,
                ])>
                SEMUA
            </button>

            @foreach($categories as $cat)
                <button wire:click="setCategory({{ $cat->id }})"
                    @class([
                        'px-5 py-2 rounded-full font-bold text-xs transition-all shadow-sm whitespace-nowrap',
                        'bg-emerald-600 text-white'=> $this->categoryId == $cat->id,
                        'bg-white text-gray-500 hover:bg-emerald-50' => $this->categoryId != $cat->id,
                    ])>
                    {{ strtoupper($cat->name) }}
                </button>
            @endforeach
        </div>

        <!-- Grid Produk -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4">
            @foreach($products as $product)
                <button wire:click="addToOrder({{ $product->id }})"
                    class="group bg-white p-3 rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 transform active:scale-95 text-left border border-transparent hover:border-emerald-500">
                    <div @class([
                        'w-full h-24 md:h-28 rounded-xl mb-2 flex items-center justify-center transition-colors uppercase font-black text-xl',
                        'bg-blue-50 text-blue-600 group-hover:bg-blue-500 group-hover:text-white' => ($product->category_id % 3 == 0),
                        'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white' => ($product->category_id % 3 == 1),
                        'bg-purple-50 text-purple-600 group-hover:bg-purple-500 group-hover:text-white' => ($product->category_id % 3 == 2),
                    ])>
                        {{ substr($product->name, 0, 2) }}
                    </div>
                    <h4 class="font-bold text-gray-800 leading-tight text-sm h-10 overflow-hidden">{{ $product->name }}</h4>
                    <p class="text-emerald-600 font-black mt-1 text-base">Rp {{ number_format($product->price) }}</p>
                </button>
            @endforeach
            
            @if($products->isEmpty())
                <div class="col-span-full py-20 text-center text-gray-400 italic text-sm">
                    Menu tidak ditemukan...
                </div>
            @endif
        </div>
    </div>

    <!-- KANAN: KERANJANG PESANAN (Sticky) -->
    <div class="w-full md:w-80 lg:w-96 bg-white shadow-2xl flex flex-col h-full border-l relative">
        <div class="p-4 border-b bg-gray-50 flex-none">
            <h3 class="font-black text-gray-700 flex items-center gap-2 uppercase tracking-tighter text-sm">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                KERANJANG
            </h3>
        </div>

        <!-- Scrollable Area -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-white no-scrollbar">
            @forelse($order_items as $item)
                <div class="flex justify-between items-center bg-gray-50 p-2.5 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex-1 pr-2">
                        <p class="font-bold text-gray-800 text-[11px] leading-tight">{{ $item->product->name }}</p>
                        <p class="text-[10px] text-emerald-600 font-bold">Rp {{ number_format($item->price) }}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button wire:click="decrementQty({{ $item->id }})" class="w-6 h-6 flex items-center justify-center bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-rose-500 hover:text-white transition shadow-sm font-bold">-</button>
                        <span class="font-black text-gray-800 w-4 text-center text-[11px]">{{ $item->qty }}</span>
                        <button wire:click="incrementQty({{ $item->id }})" class="w-6 h-6 flex items-center justify-center bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-emerald-500 hover:text-white transition shadow-sm font-bold">+</button>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-gray-300 opacity-60">
                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <p class="font-bold text-[10px] uppercase tracking-widest">Kosong</p>
                </div>
            @endforelse
        </div>

        <!-- Footer yang selalu terlihat di bawah -->
        <div class="p-4 bg-gray-50 border-t shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.03)] flex-none">
            <div class="flex justify-between items-end mb-3">
                <div>
                    <span class="text-gray-400 font-bold uppercase text-[9px] tracking-widest">Total Bayar</span>
                    <div class="text-xl font-black text-gray-800 leading-none">
                        Rp {{ number_format($this->order?->total_final ?? 0) }}
                    </div>
                    <p class="text-[8px] text-gray-400 mt-1">*Sudah termasuk Pajak & Service</p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('pos.index') }}" class="py-2 text-center bg-white border border-gray-200 text-gray-500 rounded-xl font-bold hover:bg-gray-100 transition text-[10px]">
                        KEMBALI
                    </a>
                    <button wire:click="finishOrder" 
                        @if(!$this->order || $this->order->order_items->isEmpty()) disabled @endif
                        class="py-2 bg-white border border-emerald-500 text-emerald-600 rounded-xl font-bold hover:bg-emerald-50 transition text-[10px] disabled:opacity-50">
                        SIMPAN
                    </button>
                </div>
                
                @if($this->order && !$this->order->order_items->isEmpty())
                    <a href="{{ route('pos.checkout', $table->id) }}" 
                       class="block w-full py-3 bg-emerald-600 text-white text-center rounded-xl font-black hover:bg-emerald-700 transition shadow-lg tracking-widest uppercase text-xs border-b-4 border-emerald-800 active:border-b-0 active:translate-y-1">
                        BAYAR SEKARANG
                    </a>
                @else
                    <button disabled class="w-full py-3 bg-gray-300 text-gray-500 text-center rounded-xl font-black uppercase text-xs">
                        PILIH MENU
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>