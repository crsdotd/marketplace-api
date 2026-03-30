<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronik',     'icon' => '💻', 'children' => ['Laptop', 'Handphone', 'Kamera', 'TV & Audio', 'Aksesoris']],
            ['name' => 'Kendaraan',      'icon' => '🚗', 'children' => ['Mobil', 'Motor', 'Suku Cadang', 'Aksesoris Kendaraan']],
            ['name' => 'Fashion',        'icon' => '👗', 'children' => ['Pakaian Pria', 'Pakaian Wanita', 'Sepatu', 'Tas & Aksesoris']],
            ['name' => 'Rumah & Taman',  'icon' => '🏠', 'children' => ['Furnitur', 'Peralatan Rumah', 'Dekorasi', 'Taman']],
            ['name' => 'Olahraga',       'icon' => '⚽', 'children' => ['Sepeda', 'Alat Fitness', 'Outdoor']],
            ['name' => 'Hobi',           'icon' => '🎮', 'children' => ['Gaming', 'Koleksi', 'Buku', 'Musik', 'Seni']],
            ['name' => 'Bayi & Anak',    'icon' => '👶', 'children' => ['Pakaian Bayi', 'Mainan', 'Perlengkapan Bayi']],
            ['name' => 'Hewan Peliharaan','icon' => '🐾', 'children' => ['Anjing', 'Kucing', 'Aksesoris Hewan']],
            ['name' => 'Lainnya',        'icon' => '📦', 'children' => []],
        ];

        foreach ($categories as $sort => $cat) {
            $parent = Category::create([
                'name'       => $cat['name'],
                'slug'       => Str::slug($cat['name']),
                'icon'       => $cat['icon'],
                'sort_order' => $sort,
            ]);

            foreach ($cat['children'] as $childSort => $childName) {
                Category::create([
                    'name'       => $childName,
                    'slug'       => Str::slug($childName) . '-' . Str::random(4),
                    'parent_id'  => $parent->id,
                    'sort_order' => $childSort,
                ]);
            }
        }
    }
}
