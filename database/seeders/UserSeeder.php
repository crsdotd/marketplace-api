<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\SellerProfile;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'name'       => 'Admin Marketplace',
            'email'      => 'admin@marketplace.com',
            'phone'      => '081000000001',
            'password'   => Hash::make('password'),
            'is_buyer'   => true,
            'is_seller'  => false,
            'is_admin'   => true,
            'wa_number'  => '081000000001',
            'is_verified'=> true,
        ]);
        UserProfile::create(['user_id' => $admin->id, 'city' => 'Jakarta', 'province' => 'DKI Jakarta']);

        // Demo User — punya KEDUA role (buyer + seller)
        // Simulasi user yang sudah aktifkan mode seller
        $userSeller = User::create([
            'name'       => 'Budi Santoso',
            'email'      => 'budi@demo.com',
            'phone'      => '081000000002',
            'password'   => Hash::make('password'),
            'is_buyer'   => true,   // bisa beli
            'is_seller'  => true,   // sudah aktifkan mode seller
            'is_admin'   => false,
            'wa_number'  => '081234567890',
            'is_verified'=> true,
            'balance'    => 500000,
        ]);
        UserProfile::create([
            'user_id'   => $userSeller->id,
            'city'      => 'Bandung',
            'province'  => 'Jawa Barat',
            'latitude'  => -6.9175,
            'longitude' => 107.6191,
        ]);
        SellerProfile::create([
            'user_id'          => $userSeller->id,
            'shop_name'        => 'Toko Elektronik Budi',
            'shop_description' => 'Jual beli elektronik second berkualitas.',
            'rating_avg'       => 4.8,
            'rating_count'     => 25,
            'total_sold'       => 25,
        ]);

        // Demo User — hanya buyer (belum aktifkan seller)
        $userBuyer = User::create([
            'name'       => 'Sari Dewi',
            'email'      => 'sari@demo.com',
            'phone'      => '081000000003',
            'password'   => Hash::make('password'),
            'is_buyer'   => true,
            'is_seller'  => false,  // belum aktifkan mode seller
            'is_admin'   => false,
            'wa_number'  => '089876543210',
            'is_verified'=> true,
        ]);
        UserProfile::create([
            'user_id'  => $userBuyer->id,
            'city'     => 'Surabaya',
            'province' => 'Jawa Timur',
        ]);

        $this->command->info('✅ Users seeded:');
        $this->command->info('   admin@marketplace.com  / password  (admin)');
        $this->command->info('   budi@demo.com          / password  (buyer + seller)');
        $this->command->info('   sari@demo.com          / password  (buyer only)');
    }
}
