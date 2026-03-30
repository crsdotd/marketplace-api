<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdPackage;

class AdPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'          => 'Basic',
                'description'   => 'Produk tampil di bagian atas kategori selama 7 hari.',
                'price'         => 25000,
                'duration_days' => 7,
                'type'          => 'featured',
            ],
            [
                'name'          => 'Standard',
                'description'   => 'Produk muncul di hasil pencarian paling atas selama 14 hari.',
                'price'         => 75000,
                'duration_days' => 14,
                'type'          => 'search_boost',
            ],
            [
                'name'          => 'Premium',
                'description'   => 'Banner di halaman utama + boost pencarian selama 30 hari.',
                'price'         => 200000,
                'duration_days' => 30,
                'type'          => 'homepage_banner',
            ],
        ];

        foreach ($packages as $pkg) {
            AdPackage::create($pkg);
        }
    }
}
