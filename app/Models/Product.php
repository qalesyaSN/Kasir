<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //  category_id	code	name	price	stock	track_stock	is_available	
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'price',
        'stock',
        'track_stock',
        'is_available'
    ];
}
