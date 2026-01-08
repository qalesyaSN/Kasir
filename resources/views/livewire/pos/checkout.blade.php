<?php
// ... (Logika PHP tetap sama seperti sebelumnya)
use Livewire\Volt\Component;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')]
class extends Component {
    public Table $table;
    public ?Order $order = null;
    public $pay_amount = 0; 
    public $selectedItems = []; 
    public $splitQuantities = []; 
    public $split_pay_amount = 0;
    public $payment_method = 'cash';
    
    public $discount_input = ''; 
    public $discount_type = 'fixed'; 

    public function mount(Table $table) {
        $this->table = $table;
        $this->order = Order::where('table_id', $table->id)
                            ->where('status', 'pending')
                            ->with('order_items.product')
                            ->first();
    }

    public function getDiscountAmountProperty() {
        $input = (float)($this->discount_input ?: 0);
        if ($this->discount_type === 'percent') {
            $base = $this->order ? $this->order->subtotal : 0;
            return ($base * ($input / 100));
        }
        return $input;
    }

    public function getTotalWithDiscountProperty() {
        if (!$this->order) return 0;
        $total_before_discount = $this->order->subtotal + $this->order->service_charge + $this->order->tax;
        return max(0, $total_before_discount - $this->discount_amount);
    }

    public function getChangeProperty() {
        return max(0, (int)$this->pay_amount - (int)$this->total_with_discount);
    }

    public function getAvailableTablesProperty() {
        return Table::where('status', 'available')->orderBy('number', 'asc')->get();
    }

    public function setPayAmount($amount) {
        if ($amount === 'pas') {
            $this->pay_amount = $this->total_with_discount;
        } else {
            $this->pay_amount = (int)$amount;
        }
    }

    public function processPayment() {
        if ($this->pay_amount < $this->total_with_discount) {
            session()->flash('error', 'Uang pembayaran kurang!');
            return;
        }

        

        DB::transaction(function() {
            $this->order->update([
                'discount' => $this->discount_amount,
                'paid_amount' => $this->pay_amount,
                'change_amount' => $this->change,
                'total_final' => $this->total_with_discount,
                'payment_method' => $this->payment_method,
                'status' => 'paid',
            ]);

            // Tambahkan di dalam fungsi processPayment() sebelum DB::commit
        foreach ($this->order->order_items as $item) {
        if ($item->product->track_stock) {
        $item->product->decrement('stock', $item->qty);
        
        // Otomatis ubah status jadi tidak tersedia jika stok 0
        if ($item->product->stock <= 0) {
            $item->product->update(['is_available' => false]);
        }
        }
        }

            $this->table->update(['status' => 'available']);
        });

        $this->dispatch('open-print', url: route('pos.print', $this->order->id));
    }

    public function moveTable($targetTableId) {
        $targetTable = Table::find($targetTableId);
        if ($targetTable->status !== 'available') {
            session()->flash('error', 'Meja tujuan sudah terisi!');
            return;
        }

        DB::transaction(function () use ($targetTable) {
            $this->order->update(['table_id' => $targetTable->id]);
            $this->table->update(['status' => 'available']);
            $targetTable->update(['status' => 'occupied']);
        });

        return redirect()->route('pos.index')->with('success', 'Berhasil pindah ke Meja ' . $targetTable->number);
    }

    public function splitBill() {
        if (empty($this->selectedItems)) return;

        DB::transaction(function () {
            $splitSubtotal = 0;
            $newOrderItems = [];

            foreach ($this->selectedItems as $itemId) {
                $orderItem = OrderItem::find($itemId);
                $qtyToPay = $this->splitQuantities[$itemId] ?? 1;
                $qtyToPay = min($qtyToPay, $orderItem->qty);

                $splitSubtotal += ($orderItem->price * $qtyToPay);
                $newOrderItems[] = [
                    'product_id' => $orderItem->product_id,
                    'qty' => $qtyToPay,
                    'price' => $orderItem->price,
                    'original_item' => $orderItem
                ];
            }

            $splitService = $splitSubtotal * 0.05;
            $splitTax = ($splitSubtotal + $splitService) * 0.10;
            $splitTotal = $splitSubtotal + $splitService + $splitTax;

            $newOrder = Order::create([
                'table_id' => $this->table->id,
                'user_id' => auth()->id(),
                'order_number' => 'SPLIT-' . now()->format('YmdHis'),
                'subtotal' => $splitSubtotal,
                'service_charge' => $splitService,
                'tax' => $splitTax,
                'total_final' => $splitTotal,
                'paid_amount' => $this->split_pay_amount,
                'status' => 'paid',
                'payment_method' => 'cash',
            ]);

            foreach ($newOrderItems as $data) {
                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $data['product_id'],
                    'qty' => $data['qty'],
                    'price' => $data['price'],
                ]);

                $originalItem = $data['original_item'];
                if ($originalItem->qty <= $data['qty']) {
                    $originalItem->delete();
                } else {
                    $originalItem->decrement('qty', $data['qty']);
                }
            }

            $this->order->refresh();
            $this->recalculateOriginalOrder();
        });

        return redirect()->route('pos.index')->with('success', 'Pembayaran split berhasil!');
    }

    private function recalculateOriginalOrder() {
        $newSubtotal = $this->order->order_items->sum(fn($item) => $item->price * $item->qty);
        if ($newSubtotal <= 0) {
            $this->order->update(['status' => 'paid']);
            $this->table->update(['status' => 'available']);
            return;
        }
        $newService = $newSubtotal * 0.05;
        $newTax = ($newSubtotal + $newService) * 0.10;
        $this->order->update([
            'subtotal' => $newSubtotal,
            'service_charge' => $newService,
            'tax' => $newTax,
            'total_final' => $newSubtotal + $newService + $newTax,
        ]);
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-950 pb-20 transition-colors duration-500">
    <div class="max-w-6xl mx-auto p-4 md:p-6">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-800 dark:text-gray-100 tracking-tighter uppercase">Checkout</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium italic">Meja {{ $table->number }} • {{ $order->order_number ?? '-' }}</p>
            </div>
            <a href="{{ route('pos.index') }}" class="px-5 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 rounded-xl font-bold hover:bg-gray-50 dark:hover:bg-gray-800 transition text-sm">
                KEMBALI KE DASHBOARD
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- KOLOM KIRI: RINCIAN TAGIHAN & SPLIT -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Tabel Pesanan (Modern & Clean) -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-800 flex justify-between items-center">
                        <h3 class="font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest text-xs">Rincian Nota</h3>
                        <div class="flex items-center gap-2">
                             <span class="text-[9px] bg-rose-50 dark:bg-rose-900/20 text-rose-500 px-2 py-1 rounded-lg font-black uppercase">Mode Split Aktif</span>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                <tr class="text-[10px] uppercase text-gray-400 dark:text-gray-500 font-bold tracking-widest">
                                    <th class="px-6 py-4">Pilih</th>
                                    <th class="px-6 py-4">Menu</th>
                                    <th class="px-6 py-4 text-center">Qty</th>
                                    <th class="px-6 py-4 text-right">Subtotal</th>
                                    <th class="px-6 py-4 text-center">Qty Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                @if($order)
                                    @foreach($order->order_items as $item)
                                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/50 transition">
                                            <td class="px-6 py-4">
                                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}" 
                                                       class="w-5 h-5 rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-emerald-600 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="font-bold text-gray-800 dark:text-gray-100 text-sm leading-tight">{{ $item->product->name }}</p>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium italic">@ Rp {{ number_format($item->price) }}</p>
                                            </td>
                                            <td class="px-6 py-4 text-center font-black text-gray-600 dark:text-gray-400">{{ $item->qty }}</td>
                                            <td class="px-6 py-4 text-right font-black text-gray-800 dark:text-gray-100 text-sm">
                                                Rp {{ number_format($item->price * $item->qty) }}
                                            </td>
                                            <td class="px-6 py-4">
                                                @if(in_array($item->id, $selectedItems))
                                                    <input type="number" wire:model.live.number="splitQuantities.{{ $item->id }}" 
                                                           min="1" max="{{ $item->qty }}" 
                                                           class="w-14 mx-auto block p-1.5 text-xs text-center border-emerald-200 dark:border-emerald-900 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 font-black text-emerald-700 dark:text-emerald-400">
                                                @else
                                                    <div class="w-14 h-8 mx-auto flex items-center justify-center text-gray-200 dark:text-gray-800 italic">-</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PINDAH MEJA (Dibuat Lebih Kalem & Senada Meja) -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 border-l-4 border-amber-500 shadow-sm border border-gray-100 dark:border-gray-800">
                    <h4 class="text-amber-600 dark:text-amber-500 font-black text-[10px] uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        Pindahkan Pesanan ke Meja Lain
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @forelse($this->availableTables as $at)
                            <button wire:click="moveTable({{ $at->id }})" wire:confirm="Pindah ke Meja {{ $at->number }}?"
                                    class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-black hover:bg-amber-500 hover:text-white dark:hover:bg-amber-600 transition shadow-sm">
                                MEJA {{ $at->number }}
                            </button>
                        @empty
                            <p class="text-[10px] text-gray-400 dark:text-gray-600 font-bold italic italic">Tidak ada meja kosong tersedia.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: RINGKASAN & BAYAR -->
            <div class="lg:col-span-5 space-y-6">
                
                <!-- PANEL SPLIT BILL (Lebih Elegan) -->
                @if(!empty($selectedItems))
                    @php
                        $sSub = 0;
                        foreach($selectedItems as $sId) {
                            $it = $order->order_items->firstWhere('id', $sId);
                            if($it) {
                                $qS = isset($splitQuantities[$sId]) && (int)$splitQuantities[$sId] > 0 ? (int)$splitQuantities[$sId] : 1;
                                $sSub += ($it->price * min($qS, $it->qty));
                            }
                        }
                        $sServ = $sSub * 0.05; $sTax = ($sSub + $sServ) * 0.10; $sTotal = $sSub + $sServ + $sTax;
                        $sChange = max(0, (int)$split_pay_amount - $sTotal);
                    @endphp
                    <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 border-l-4 border-blue-500 shadow-xl border border-gray-100 dark:border-gray-800">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-black uppercase tracking-tighter text-[10px] text-blue-600 dark:text-blue-400 mb-1">Nota Split Aktif</h3>
                                <p class="text-2xl font-black text-gray-800 dark:text-gray-100 tracking-tighter">Rp {{ number_format($sTotal) }}</p>
                            </div>
                            <svg class="w-8 h-8 text-blue-100 dark:text-blue-900/30" fill="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase mb-2 block tracking-widest">Uang Diterima</label>
                                <input type="number" wire:model.live.number="split_pay_amount" placeholder="0"
                                       class="w-full p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl text-xl font-black text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="flex justify-between items-center py-2">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Kembalian</span>
                                <span class="font-black text-xl text-emerald-500 dark:text-emerald-400">Rp {{ number_format($sChange) }}</span>
                            </div>

                            <button wire:click="splitBill" @if($split_pay_amount < $sTotal) disabled @endif
                                    class="w-full py-4 bg-blue-600 dark:bg-blue-700 text-white rounded-2xl font-black shadow-lg hover:bg-blue-700 transition disabled:opacity-30 disabled:grayscale">
                                BAYAR PARSIAL SEKARANG
                            </button>
                        </div>
                    </div>
                @endif

                <!-- PANEL PROMO & DISKON (Warna Senada Dashboard) -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <h3 class="font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest text-[10px] mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12z" /></svg>
                        Potongan Harga & Promo
                    </h3>
                    <div class="flex gap-2 mb-4">
                        <button wire:click="$set('discount_type', 'fixed')" 
                                @class(['flex-1 py-2 rounded-xl font-black text-[10px] border transition', $discount_type === 'fixed' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400'])>
                            RUPIAH (RP)
                        </button>
                        <button wire:click="$set('discount_type', 'percent')" 
                                @class(['flex-1 py-2 rounded-xl font-black text-[10px] border transition', $discount_type === 'percent' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400'])>
                            PERSEN (%)
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 font-black text-gray-400 dark:text-gray-500 text-sm">
                            {{ $discount_type === 'fixed' ? 'Rp' : '%' }}
                        </span>
                        <input type="number" wire:model.live="discount_input" 
                               class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl font-black text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500 transition-colors">
                    </div>
                </div>

                <!-- RINGKASAN TAGIHAN FINAL -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 space-y-4">
                    <div class="space-y-2">
                        <div class="flex justify-between text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <span>Subtotal Menu</span>
                            <span class="text-gray-700 dark:text-gray-300 font-black">Rp {{ number_format($order->subtotal ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] font-bold text-rose-500 dark:text-rose-400 uppercase tracking-widest">
                            <span>Diskon Promo</span>
                            <span class="font-black">- Rp {{ number_format($this->discount_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <span>Biaya Tambahan (Tax/Svc)</span>
                            <span class="text-gray-700 dark:text-gray-300 font-black">Rp {{ number_format(($order->tax ?? 0) + ($order->service_charge ?? 0)) }}</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t dark:border-gray-800 flex justify-between items-center">
                        <span class="font-black text-gray-800 dark:text-gray-200 uppercase tracking-tighter text-sm">Total Tagihan Akhir</span>
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tighter shadow-emerald-500/10">Rp {{ number_format($this->total_with_discount) }}</span>
                    </div>
                </div>

                <!-- PANEL PEMBAYARAN TUNAI (Dark UI Tetap Konsisten) -->
                <div class="bg-gray-900 dark:bg-black rounded-3xl p-6 text-white shadow-2xl transition-all border border-transparent dark:border-gray-800">
                    @if (session()->has('error'))
                        <div class="mb-4 bg-rose-500/20 text-rose-300 p-3 rounded-xl text-[10px] font-bold border border-rose-500/30">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="space-y-5">
                        <div class="flex justify-between items-center">
                            <label class="text-[10px] font-bold text-gray-500 dark:text-gray-500 uppercase tracking-widest">Tunai Diterima</label>
                            <button wire:click="setPayAmount('pas')" class="px-4 py-1.5 bg-gray-800 dark:bg-gray-900 border border-gray-700 dark:border-gray-800 rounded-xl text-[10px] font-black hover:bg-emerald-500 hover:border-emerald-500 transition-all uppercase tracking-tighter">UANG PAS</button>
                        </div>
                        <input type="number" wire:model.live.number="pay_amount" 
                               class="w-full bg-gray-800 dark:bg-gray-900 border border-gray-700 dark:border-gray-800 rounded-2xl p-5 text-4xl font-black text-white focus:ring-2 focus:ring-emerald-500 transition-all placeholder:text-gray-700">
                        
                        <div class="flex justify-between items-center pt-5 border-t border-gray-800 dark:border-gray-800 font-black">
                            <span class="text-xs text-gray-500 uppercase tracking-widest">Kembalian Kasir</span>
                            <span class="text-2xl text-emerald-400 italic">Rp {{ number_format($this->change) }}</span>
                        </div>

                        <button wire:click="processPayment" 
                                @if($pay_amount < $this->total_with_discount) disabled @endif
                                class="w-full py-5 bg-emerald-500 dark:bg-emerald-600 text-white rounded-2xl font-black text-xl shadow-xl shadow-emerald-900/20 hover:bg-emerald-400 dark:hover:bg-emerald-500 transition-all disabled:opacity-20 disabled:grayscale active:scale-95 border-b-4 border-emerald-800">
                            CETAK STRUK & SELESAI
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('open-print', event => {
        window.open(event.detail.url, '_blank');
        setTimeout(() => { window.location.href = "{{ route('pos.index') }}"; }, 1000);
    });
</script>