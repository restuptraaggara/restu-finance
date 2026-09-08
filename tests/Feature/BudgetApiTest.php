<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $categoryFood;
    protected Category $categoryTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['email' => 'restu@dev.local'],
            [
                'name' => 'Restu Putra Anggara',
                'password' => bcrypt('password'),
            ]
        );

        $this->categoryFood = Category::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Makanan'],
            ['type' => 'expense']
        );

        $this->categoryTransport = Category::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Transportasi'],
            ['type' => 'expense']
        );
    }

    /**
     * 1. User dapat melihat budget miliknya beserta ringkasan penggunaan.
     */
    public function test_user_can_list_own_budgets(): void
    {
        Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        $response = $this->getJson('/api/budgets', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'total',
                     'period',
                     'summary' => [
                         'total_budget',
                         'total_spent',
                         'total_remaining',
                         'overall_percentage',
                     ],
                     'data' => [
                         '*' => [
                             'id',
                             'category',
                             'limit_amount',
                             'used',
                             'remaining',
                             'percentage',
                             'is_over_budget',
                         ],
                     ],
                 ]);
    }

    /**
     * 2. User tidak dapat melihat budget user lain.
     */
    public function test_user_cannot_view_other_users_budget(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherBudget = Budget::create([
            'user_id' => $otherUser->id,
            'category' => 'Belanja Rahasia',
            'limit_amount' => 5000000,
        ]);

        $response = $this->getJson("/api/budgets/{$otherBudget->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    /**
     * 3. User dapat membuat budget (menggunakan category_id atau category name).
     */
    public function test_user_can_create_budget(): void
    {
        $payload = [
            'category_id' => $this->categoryFood->id,
            'amount' => 1200000,
        ];

        $response = $this->postJson('/api/budgets', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Anggaran berhasil disimpan.',
                     'data' => [
                         'user_id' => $this->user->id,
                         'category' => 'Makanan',
                         'limit_amount' => 1200000,
                         'remaining' => 1200000,
                         'used' => 0,
                     ],
                 ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1200000,
        ]);
    }

    /**
     * 4. User dapat mengedit nominal atau kategori budget.
     */
    public function test_user_can_edit_budget(): void
    {
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        $payload = [
            'amount' => 1500000,
        ];

        $response = $this->putJson("/api/budgets/{$budget->id}", $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Anggaran berhasil diperbarui.',
                     'data' => [
                         'id' => $budget->id,
                         'limit_amount' => 1500000,
                     ],
                 ]);

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'limit_amount' => 1500000,
        ]);
    }

    /**
     * 5. User dapat menghapus budget tanpa menghapus transaksi.
     */
    public function test_user_can_delete_budget_without_deleting_transactions(): void
    {
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        $tx = $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Makan Bakso',
            'date' => now()->toDateString(),
        ]);

        $response = $this->deleteJson("/api/budgets/{$budget->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Anggaran berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('budgets', [
            'id' => $budget->id,
        ]);

        // Transaksi tidak boleh terhapus
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'description' => 'Makan Bakso',
        ]);
    }

    /**
     * 6. Validation nominal budget (wajib, numeric, > 0).
     */
    public function test_budget_amount_validation(): void
    {
        $payload = [
            'category' => 'Makanan',
            'amount' => -50000,
        ];

        $response = $this->postJson('/api/budgets', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => ['amount'],
                 ]);
    }

    /**
     * 7. Validation category (category_id harus valid dan milik user yang sama).
     */
    public function test_budget_category_ownership_validation(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherCategory = Category::create([
            'user_id' => $otherUser->id,
            'name' => 'Kategori Orang Lain',
            'type' => 'expense',
        ]);

        $payload = [
            'category_id' => $otherCategory->id,
            'amount' => 500000,
        ];

        $response = $this->postJson('/api/budgets', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => ['category_id'],
                 ]);
    }

    /**
     * 8, 9, 10, 11. Expense masuk ke 'used', income TIDAK masuk, remaining dan percentage akurat.
     */
    public function test_budget_usage_and_remaining_calculation(): void
    {
        $currentMonth = now()->format('Y-m');

        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000, // Limit: 1.000.000
        ]);

        // Expense 1: 400.000 (masuk ke used)
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 400000,
            'description' => 'Makan 1',
            'date' => $currentMonth . '-05',
        ]);

        // Expense 2: 250.000 (masuk ke used)
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 250000,
            'description' => 'Makan 2',
            'date' => $currentMonth . '-10',
        ]);

        // Income: 300.000 pada kategori yang sama (TIDAK boleh masuk ke used budget)
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'income',
            'amount' => 300000,
            'description' => 'Cashback Makanan',
            'date' => $currentMonth . '-12',
        ]);

        $response = $this->getJson("/api/budgets/{$budget->id}?month={$currentMonth}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $budget->id,
                         'limit' => 1000000,
                         'used' => 650000,     // 400.000 + 250.000 = 650.000 (Income 300rb tidak dihitung)
                         'remaining' => 350000, // 1.000.000 - 650.000 = 350.000
                         'percentage' => 65.0,  // (650.000 / 1.000.000) * 100% = 65%
                         'is_over_budget' => false,
                     ],
                 ]);
    }

    /**
     * 12. Transaksi kategori berbeda tidak masuk ke budget kategori ini.
     */
    public function test_transactions_in_other_category_do_not_affect_budget(): void
    {
        $currentMonth = now()->format('Y-m');

        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        // Expense Transportasi (kategori berbeda)
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Transportasi',
            'type' => 'expense',
            'amount' => 200000,
            'description' => 'Bensin',
            'date' => $currentMonth . '-05',
        ]);

        $response = $this->getJson("/api/budgets/{$budget->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.used', 0)
                 ->assertJsonPath('data.remaining', 1000000);
    }

    /**
     * 13. Transaksi di luar periode budget (beda bulan) tidak masuk ke budget.
     */
    public function test_transactions_in_different_month_do_not_affect_budget(): void
    {
        $budget = Budget::create([
            'user_id' => $this->user->id,
            'category' => 'Makanan',
            'limit_amount' => 1000000,
        ]);

        // Expense Makanan di bulan lalu (2025-01-15)
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 500000,
            'description' => 'Makan Bulan Lalu',
            'date' => '2025-01-15',
        ]);

        // Cek budget untuk bulan 2026-09
        $response = $this->getJson("/api/budgets/{$budget->id}?month=2026-09", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.used', 0)
                 ->assertJsonPath('data.remaining', 1000000);
    }
}
