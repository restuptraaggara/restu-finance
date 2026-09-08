<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'restu@dev.local'],
            [
                'name' => 'Restu Putra Anggara',
                'password' => Hash::make('password'),
            ]
        );

        foreach (['DANA', 'Bank Jago'] as $name) {
            Wallet::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
            ], [
                'opening_balance' => 0,
                'is_active' => true,
            ]);
        }

        foreach ([
            'Makanan',
            'Transportasi',
            'Belanja',
            'Hiburan',
            'Tagihan',
            'Pendidikan',
            'Tabungan',
            'Investasi',
            'Kebutuhan',
            'Lainnya',
        ] as $name) {
            Category::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
            ], [
                'type' => 'expense',
            ]);
        }
    }
}