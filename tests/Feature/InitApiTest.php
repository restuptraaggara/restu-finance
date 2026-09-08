<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['email' => 'restu@dev.local'],
            [
                'name' => 'Restu Putra Anggara',
                'password' => bcrypt('password123'),
                'theme' => 'dark',
                'currency' => 'IDR',
                'language' => 'id',
            ]
        );
    }

    /**
     * 1. Authenticated user dapat mengambil /api/init dan mendapatkan struktur payload yang lengkap.
     */
    public function test_user_can_retrieve_init_data(): void
    {
        // Seed user's data
        $wallet = Wallet::create([
            'user_id' => $this->user->id,
            'name' => 'BCA Debit',
            'opening_balance' => 5000000,
            'is_active' => true,
        ]);

        $catFood = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Makanan',
            'type' => 'expense',
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'wallet' => 'BCA Debit',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 100000,
            'description' => 'Makan Siang',
            'date' => now()->toDateString(),
        ]);

        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Beli Gadget',
            'target_amount' => 10000000,
            'saved_amount' => 2000000,
            'deadline' => '2026-12-31',
        ]);

        $response = $this->getJson('/api/init', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => ['id', 'name', 'email', 'theme', 'currency', 'language'],
                         'wallets' => [
                             '*' => ['id', 'name', 'opening_balance', 'current_balance', 'is_active'],
                         ],
                         'categories' => [
                             '*' => ['id', 'name', 'type'],
                         ],
                         'transactions' => [
                             '*' => ['id', 'wallet', 'category', 'type', 'amount', 'date'],
                         ],
                         'budgets' => [
                             '*' => ['id', 'category', 'limit_amount', 'used', 'remaining', 'percentage'],
                         ],
                         'goals' => [
                             '*' => ['id', 'name', 'target_amount', 'saved', 'remaining', 'percentage'],
                         ],
                         'summary' => [
                             'total_balance',
                             'month_income',
                             'month_expense',
                             'period',
                         ],
                     ],
                 ]);

        // Verifikasi saldo dompet BCA (5.000.000 - 100.000 = 4.900.000)
        $response->assertJsonPath('data.wallets.0.current_balance', 4900000)
                 ->assertJsonPath('data.summary.total_balance', 4900000)
                 ->assertJsonPath('data.summary.month_expense', 100000);
    }

    /**
     * 2. Data user lain TIDAK bocor ke response /api/init user ini.
     */
    public function test_other_users_data_does_not_leak_in_init(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('secret'),
        ]);

        Wallet::create([
            'user_id' => $otherUser->id,
            'name' => 'Dompet Rahasia Orang Lain',
            'opening_balance' => 99999999,
            'is_active' => true,
        ]);

        Category::create([
            'user_id' => $otherUser->id,
            'name' => 'Kategori Orang Lain',
            'type' => 'expense',
        ]);

        Transaction::create([
            'user_id' => $otherUser->id,
            'wallet' => 'Dompet Rahasia Orang Lain',
            'category' => 'Kategori Orang Lain',
            'type' => 'income',
            'amount' => 88888888,
            'description' => 'Transaksi Orang Lain',
            'date' => now()->toDateString(),
        ]);

        Budget::create([
            'user_id' => $otherUser->id,
            'category' => 'Kategori Orang Lain',
            'limit_amount' => 77777777,
        ]);

        Goal::create([
            'user_id' => $otherUser->id,
            'name' => 'Target Orang Lain',
            'target_amount' => 66666666,
            'saved_amount' => 11111111,
            'deadline' => '2026-12-31',
        ]);

        $response = $this->getJson('/api/init', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200);

        $jsonStr = $response->getContent();

        $this->assertStringNotContainsString('Dompet Rahasia Orang Lain', $jsonStr);
        $this->assertStringNotContainsString('Kategori Orang Lain', $jsonStr);
        $this->assertStringNotContainsString('Transaksi Orang Lain', $jsonStr);
        $this->assertStringNotContainsString('Target Orang Lain', $jsonStr);
        $this->assertStringNotContainsString('other_', $jsonStr);
    }

    /**
     * 3. Password dan remember_token tidak bocor di response /api/init.
     */
    public function test_sensitive_attributes_hidden_in_init(): void
    {
        $response = $this->getJson('/api/init', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonMissingPath('data.user.password')
                 ->assertJsonMissingPath('data.user.remember_token');
    }
}
