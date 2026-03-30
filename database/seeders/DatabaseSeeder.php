<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\SellerProfile;
use App\Models\Category;
use App\Models\PlatformBankAccount;
use App\Models\AdPackage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PlatformBankSeeder::class,
            AdPackageSeeder::class,
            UserSeeder::class,
        ]);
    }
}
