<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome');

// Monitor Stok diletakkan di luar auth agar tidak terkena session expired (untuk TV/Monitor Dapur)
Volt::route('/pos/stock-monitor', 'pos.stock-monitor')->name('pos.stock-monitor');


/*
|--------------------------------------------------------------------------
| Authenticated Routes (Breeze & POS System)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Breeze Dashboard & Profile
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    // POS System - Main Features
    Route::prefix('pos')->name('pos.')->group(function () {
        Volt::route('/', 'pos.dashboard')->name('index');
        Volt::route('/order/{table}', 'pos.order-entry')->name('order');
        Volt::route('/checkout/{table}', 'pos.checkout')->name('checkout');
        Volt::route('/report', 'pos.report')->name('report');
        
        /*
        |--------------------------------------------------------------------------
        | Printing Routes
        |--------------------------------------------------------------------------
        */
        // Cetak Struk Belanja
        Route::get('/print/{order}', function (Order $order) {
            return view('pos.print', [
                'order' => $order->load('order_items.product', 'table')
            ]);
        })->name('print');

        // Cetak Laporan Penjualan (Closing)
        Route::get('/report/print', function () {
            $today = now()->toDateString();
            
            // Ringkasan Statistik
            $stats = [
                'total_revenue' => Order::where('status', 'paid')->whereDate('created_at', $today)->sum('total_final'),
                'order_count'   => Order::where('status', 'paid')->whereDate('created_at', $today)->count(),
                'total_tax'     => Order::where('status', 'paid')->whereDate('created_at', $today)->sum('tax'),
                'total_service' => Order::where('status', 'paid')->whereDate('created_at', $today)->sum('service_charge'),
            ];

            // Detail Item Terjual
            $sold_items = OrderItem::whereHas('order', function($q) use ($today) {
                    $q->where('status', 'paid')->whereDate('created_at', $today);
                })
                ->select('product_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(qty * price) as total_price'))
                ->groupBy('product_id')
                ->with('product')
                ->get();

            return view('pos.print-report', compact('stats', 'sold_items'));
        })->name('report.print');
    });
});

require __DIR__.'/auth.php';