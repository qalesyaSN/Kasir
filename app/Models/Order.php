<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    // Tambahkan ini agar kita bisa simpan data lewat Order::create()
    protected $fillable = [
        'table_id',
        'user_id',
        'order_number',
        'subtotal',
        'service_charge',
        'discount',
        'tax',
        'total_final',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status'
    ];
    
    public function order_items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function table(): BelongsTo
{
    return $this->belongsTo(Table::class);
}
}
