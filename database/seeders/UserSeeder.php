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
            'role'       => 'admin',
            'wa_number'  => '081000000001',
            'is_verified'=> true,
            'balance'    => 0,
        ]);
        UserProfile::create(['user_id' => $admin->id, 'city' => 'Jakarta', 'province' => 'DKI Jakarta']);

        // Demo Seller
        $seller = User::create([
            'name'       => 'Toko Demo Seller',
            'email'      => 'seller@demo.com',
            'phone'      => '081000000002',
            'password'   => Hash::make('password'),
            'role'       => 'seller',
            'wa_number'  => '081234567890',
            'is_verified'=> true,
            'balance'    => 500000,
        ]);
        UserProfile::create([
            'user_id'  => $seller->id,
            'city'     => 'Bandung',
            'province' => 'Jawa Barat',
            'latitude' => -6.9175,
            'longitude'=> 107.6191,
        ]);
        SellerProfile::create([
            'user_id'          => $seller->id,
            'shop_name'        => 'Toko Elektronik Murah',
            'shop_description' => 'Jual beli elektronik second berkualitas, garansi kepuasan.',
            'rating_avg'       => 4.8,
            'rating_count'     => 32,
            'total_sold'       => 32,
        ]);

        // Demo Buyer
        $buyer = User::create([
            'name'       => 'Demo Buyer',
            'email'      => 'buyer@demo.com',
            'phone'      => '081000000003',
            'password'   => Hash::make('password'),
            'role'       => 'buyer',
            'wa_number'  => '089876543210',
            'is_verified'=> true,
        ]);
        UserProfile::create([
            'user_id'  => $buyer->id,
            'city'     => 'Surabaya',
            'province' => 'Jawa Timur',
        ]);

        $this->command->info('✅ Users seeded:');
        $this->command->info('   admin@marketplace.com  / password');
        $this->command->info('   seller@demo.com        / password');
        $this->command->info('   buyer@demo.com         / password');
    }
}
