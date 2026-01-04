<?php
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
    
    // Fitur Diskon
    public $discount_input = ''; 
    public $discount_type = 'fixed'; 

    public function mount(Table $table) {
        $this->table = $table;
        $this->order = Order::where('table_id', $table->id)
                            ->where('status', 'pending')
                            ->with('order_items.product')
                            ->first();
    }

    // --- LOGIKA PERHITUNGAN (COMPUTED) ---

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

    // --- AKSI USER ---

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

<div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-6xl mx-auto p-4 md:p-6">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-800 tracking-tighter uppercase">Checkout</h1>
                <p class="text-sm text-gray-500 font-medium">Meja {{ $table->number }} • {{ $order->order_number ?? '-' }}</p>
            </div>
            <a href="{{ route('pos.index') }}" class="px-5 py-2 bg-white border border-gray-200 text-gray-400 rounded-xl font-bold hover:bg-gray-50 transition text-sm">
                KEMBALI KE MEJA
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- KOLOM KIRI: RINCIAN TAGIHAN & SPLIT -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                        <h3 class="font-black text-gray-700 uppercase tracking-widest text-xs">Daftar Pesanan</h3>
                        <span class="text-[10px] bg-rose-50 text-rose-500 px-3 py-1 rounded-lg font-black italic">Bisa Split Bill</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50">
                                <tr class="text-[10px] uppercase text-gray-400 font-bold tracking-widest">
                                    <th class="px-6 py-4">Pilih</th>
                                    <th class="px-6 py-4">Menu</th>
                                    <th class="px-6 py-4 text-center">Qty</th>
                                    <th class="px-6 py-4 text-right">Subtotal</th>
                                    <th class="px-6 py-4 text-center">Split</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @if($order)
                                    @foreach($order->order_items as $item)
                                        <tr class="hover:bg-gray-50/80 transition group">
                                            <td class="px-6 py-4">
                                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}" 
                                                       class="w-5 h-5 rounded-lg border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="font-bold text-gray-800 text-sm leading-tight">{{ $item->product->name }}</p>
                                                <p class="text-[10px] text-gray-400">@ Rp {{ number_format($item->price) }}</p>
                                            </td>
                                            <td class="px-6 py-4 text-center font-bold text-gray-600">{{ $item->qty }}</td>
                                            <td class="px-6 py-4 text-right font-black text-gray-800 text-sm">
                                                {{ number_format($item->price * $item->qty) }}
                                            </td>
                                            <td class="px-6 py-4">
                                                @if(in_array($item->id, $selectedItems))
                                                    <input type="number" wire:model.live.number="splitQuantities.{{ $item->id }}" 
                                                           min="1" max="{{ $item->qty }}" 
                                                           class="w-14 mx-auto block p-1 text-xs text-center border-emerald-200 rounded-lg bg-emerald-50 font-black text-emerald-700">
                                                @else
                                                    <div class="w-14 h-8 mx-auto flex items-center justify-center text-gray-200 italic">-</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PINDAH MEJA -->
                <div class="bg-amber-50 rounded-2xl p-6 border border-amber-100">
                    <h4 class="text-amber-800 font-black text-[10px] uppercase tracking-widest mb-4">Aksi: Pindahkan Meja</h4>
                    <div class="flex flex-wrap gap-2">
                        @forelse($this->availableTables as $at)
                            <button wire:click="moveTable({{ $at->id }})" wire:confirm="Yakin pindah ke Meja {{ $at->number }}?"
                                    class="px-4 py-2 bg-white border border-amber-200 text-amber-700 rounded-xl text-xs font-bold hover:bg-amber-500 hover:text-white transition shadow-sm">
                                Meja {{ $at->number }}
                            </button>
                        @empty
                            <p class="text-[10px] text-amber-600 font-bold italic">Meja penuh.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: RINGKASAN & BAYAR -->
            <div class="lg:col-span-5 space-y-6">
                
                <!-- PANEL SPLIT BILL (Hanya muncul jika ada item dipilih) -->
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
                        $sServ = $sSub * 0.05;
                        $sTax = ($sSub + $sServ) * 0.10;
                        $sTotal = $sSub + $sServ + $sTax;
                        $sChange = max(0, (int)$split_pay_amount - $sTotal);
                    @endphp
                    <div class="bg-blue-600 rounded-3xl p-6 text-white shadow-xl shadow-blue-100">
                        <h3 class="font-black uppercase tracking-tighter text-xs mb-4">Pembayaran Split</h3>
                        <div class="flex justify-between items-end mb-6">
                            <p class="text-[10px] opacity-80 uppercase font-bold">Total Parsial</p>
                            <h2 class="text-3xl font-black">Rp {{ number_format($sTotal) }}</h2>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-[10px] font-bold opacity-60 uppercase mb-1 block">Uang Tunai</label>
                                <input type="number" wire:model.live.number="split_pay_amount" placeholder="0"
                                       class="w-full p-4 bg-white/10 border border-white/20 rounded-2xl text-xl font-black placeholder:text-white/30 focus:ring-0">
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold opacity-80">KEMBALIAN</span>
                                <span class="font-black text-xl">Rp {{ number_format($sChange) }}</span>
                            </div>

                            <button wire:click="splitBill" @if($split_pay_amount < $sTotal) disabled @endif
                                    class="w-full py-4 bg-white text-blue-700 rounded-2xl font-black shadow-lg hover:bg-blue-50 transition disabled:opacity-30">
                                BAYAR SPLIT
                            </button>
                        </div>
                    </div>
                @endif

                <!-- PANEL PROMO & DISKON -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-black text-gray-700 uppercase tracking-widest text-xs mb-4">Promo & Diskon</h3>
                    <div class="flex gap-2 mb-4">
                        <button wire:click="$set('discount_type', 'fixed')" 
                                @class(['flex-1 py-2 rounded-xl font-bold text-[10px] border transition', $discount_type === 'fixed' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-gray-50 border-gray-200 text-gray-400'])>
                            RP (NOMINAL)
                        </button>
                        <button wire:click="$set('discount_type', 'percent')" 
                                @class(['flex-1 py-2 rounded-xl font-bold text-[10px] border transition', $discount_type === 'percent' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-gray-50 border-gray-200 text-gray-400'])>
                            % (PERSEN)
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 font-black text-gray-400 text-sm">
                            {{ $discount_type === 'fixed' ? 'Rp' : '%' }}
                        </span>
                        <input type="number" wire:model.live="discount_input" 
                               class="w-full pl-12 pr-4 py-3 bg-gray-50 border-none rounded-2xl font-black text-gray-800 focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- RINGKASAN FINAL -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400 font-bold uppercase tracking-widest">Subtotal</span>
                        <span class="font-bold text-gray-700">Rp {{ number_format($order->subtotal ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-rose-500 font-bold uppercase tracking-widest">
                        <span>Diskon</span>
                        <span>- Rp {{ number_format($this->discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-400 font-bold uppercase tracking-widest">Pajak & Service</span>
                        <span class="font-bold text-gray-700">Rp {{ number_format(($order->tax ?? 0) + ($order->service_charge ?? 0)) }}</span>
                    </div>
                    <div class="pt-4 border-t flex justify-between items-center">
                        <span class="font-black text-gray-800 uppercase tracking-tighter">Total Akhir</span>
                        <span class="text-3xl font-black text-emerald-600">Rp {{ number_format($this->total_with_discount) }}</span>
                    </div>
                </div>

                <!-- PANEL BAYAR TUNAI -->
                <div class="bg-gray-900 rounded-3xl p-6 text-white shadow-2xl">
                    @if (session()->has('error'))
                        <div class="mb-4 bg-rose-500/20 text-rose-300 p-3 rounded-xl text-[10px] font-bold border border-rose-500/30">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Uang Tunai</label>
                            <button wire:click="setPayAmount('pas')" class="px-3 py-1 bg-gray-800 border border-gray-700 rounded-lg text-[9px] font-black hover:bg-emerald-500 transition uppercase">Uang Pas</button>
                        </div>
                        <input type="number" wire:model.live.number="pay_amount" 
                               class="w-full bg-gray-800 border-none rounded-2xl p-4 text-3xl font-black text-white focus:ring-2 focus:ring-emerald-500">
                        
                        <div class="flex justify-between items-center pt-4 border-t border-gray-800">
                            <span class="text-xs font-bold text-gray-500 uppercase">Kembalian</span>
                            <span class="text-2xl font-black text-emerald-400">Rp {{ number_format($this->change) }}</span>
                        </div>

                        <button wire:click="processPayment" 
                                @if($pay_amount < $this->total_with_discount) disabled @endif
                                class="w-full py-5 bg-emerald-500 text-white rounded-2xl font-black text-lg shadow-xl shadow-emerald-900/20 hover:bg-emerald-400 transition disabled:opacity-20 disabled:grayscale">
                            SELESAIKAN PEMBAYARAN
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
        setTimeout(() => {
            window.location.href = "{{ route('pos.index') }}";
        }, 1000);
    });
</script>