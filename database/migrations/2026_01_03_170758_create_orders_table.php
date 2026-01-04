<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('table_id')->nullable()->constrained()->onDelete('set null');
    $table->string('order_number')->unique(); // Contoh: INV-20250101-0001
    $table->integer('subtotal')->default(0);
    $table->integer('service_charge')->default(0);
    $table->integer('tax')->default(0);
    $table->integer('total_final')->default(0);
    $table->integer('paid_amount')->default(0); // Uang yang dibayar
    $table->integer('change_amount')->default(0); // Kembalian
    $table->string('payment_method')->nullable(); // cash, debit, qris
    $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
