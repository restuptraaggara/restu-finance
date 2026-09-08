<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rawPassword = 'password123';
        try {
            $hashedPassword = Hash::make($rawPassword);
        } catch (\Throwable) {
            $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);
        }

        $user = User::updateOrCreate(
            ['email' => 'restu@dev.local'],
            [
                'name' => 'Restu Putra Anggara',
                'password' => $hashedPassword,
                'theme' => 'dark',
                'currency' => 'IDR',
                'language' => 'id',
            ]
        );

        // Seed default wallets if empty
        if ($user->wallets()->count() === 0) {
            $user->wallets()->createMany([
                ['name' => 'BCA Utama', 'opening_balance' => 5000000, 'is_active' => true],
                ['name' => 'Dompet Tunai', 'opening_balance' => 500000, 'is_active' => true],
            ]);
        }

        // Seed default categories if empty
        if ($user->categories()->count() === 0) {
            $defaultCats = ['Makanan', 'Transportasi', 'Belanja', 'Tagihan', 'Hiburan', 'Gaji', 'Bonus'];
            foreach ($defaultCats as $cat) {
                $user->categories()->create([
                    'name' => $cat,
                    'type' => in_array($cat, ['Gaji', 'Bonus']) ? 'income' : 'expense',
                ]);
            }
        }
    }
}
