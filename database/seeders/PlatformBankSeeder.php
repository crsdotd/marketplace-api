<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformBankAccount;

class PlatformBankSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['bank_name' => 'BCA',     'account_number' => '1234567890', 'account_holder' => 'PT Marketplace Indonesia'],
            ['bank_name' => 'BNI',     'account_number' => '0987654321', 'account_holder' => 'PT Marketplace Indonesia'],
            ['bank_name' => 'BRI',     'account_number' => '1122334455', 'account_holder' => 'PT Marketplace Indonesia'],
            ['bank_name' => 'Mandiri', 'account_number' => '5544332211', 'account_holder' => 'PT Marketplace Indonesia'],
        ];

        foreach ($accounts as $account) {
            PlatformBankAccount::create($account);
        }
    }
}
