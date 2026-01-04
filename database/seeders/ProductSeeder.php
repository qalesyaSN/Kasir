<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Kategori
        $makanan = Category::create(['name' => 'Makanan']);
        $minuman = Category::create(['name' => 'Minuman']);

        // 2. Buat Produk Makanan
        Product::create([
            'category_id' => $makanan->id,
            'code' => 'NGR',
            'name' => 'Nasi Goreng Spesial',
            'price' => 25000,
            'is_available' => true,
        ]);

        Product::create([
            'category_id' => $makanan->id,
            'code' => 'AYM',
            'name' => 'Ayam Bakar Madu',
            'price' => 30000,
            'is_available' => true,
        ]);

        // 3. Buat Produk Minuman
        Product::create([
            'category_id' => $minuman->id,
            'code' => 'EST',
            'name' => 'Es Teh Manis',
            'price' => 5000,
            'is_available' => true,
        ]);

        Product::create([
            'category_id' => $minuman->id,
            'code' => 'JRK',
            'name' => 'Es Jeruk Peras',
            'price' => 10000,
            'is_available' => true,
        ]);
    }
}
