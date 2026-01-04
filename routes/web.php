<?php
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt; // Tambahkan ini di atas

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';


// ... route bawaan breeze lainnya ...

Route::middleware(['auth', 'verified'])->group(function () {
    
    // Route Utama POS (Grid Meja)
    Volt::route('/pos', 'pos.dashboard')->name('pos.index');

    // Route untuk Order (Saat meja diklik) - Kita siapkan dulu namanya
    Volt::route('/pos/order/{table}', 'pos.order-entry')
        ->name('pos.order');
    // Route untuk meja yang sudah terisi
    Volt::route('/pos/checkout/{table}', 'pos.checkout')->name('pos.checkout');
    Volt::route('/pos/report', 'pos.report')->name('pos.report');

});


Route::get('/pos/print/{order}', function (App\Models\Order $order) {
    return view('pos.print', ['order' => $order->load('order_items.product', 'table')]);
})->name('pos.print')->middleware('auth');

Route::get('/pos/report/print', function () {
    $today = now()->toDateString();
    
    // Ambil statistik ringkasan
    $stats = [
    // Ambil total_final (sudah termasuk tax + service)
    'total_revenue' => App\Models\Order::where('status', 'paid')->whereDate('created_at', $today)->sum('total_final'),
    'order_count' => App\Models\Order::where('status', 'paid')->whereDate('created_at', $today)->count(),
    // Ambil total pajak
    'total_tax' => App\Models\Order::where('status', 'paid')->whereDate('created_at', $today)->sum('tax'),
    // Ambil total service
    'total_service' => App\Models\Order::where('status', 'paid')->whereDate('created_at', $today)->sum('service_charge'),
];


    // Ambil detail item yang laku (digabung berdasarkan produk)
    $sold_items = App\Models\OrderItem::whereHas('order', function($q) use ($today) {
            $q->where('status', 'paid')->whereDate('created_at', $today);
        })
        ->select('product_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(qty * price) as total_price'))
        ->groupBy('product_id')
        ->with('product')
        ->get();

    return view('pos.print-report', compact('stats', 'sold_items'));
})->name('pos.report.print')->middleware('auth');
