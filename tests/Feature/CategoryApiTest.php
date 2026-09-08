<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $categoryExpense;
    protected Category $categoryIncome;

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

        $this->categoryExpense = Category::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Makanan'],
            ['type' => 'expense']
        );

        $this->categoryIncome = Category::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Gaji'],
            ['type' => 'income']
        );
    }

    /**
     * 1. User dapat melihat kategori miliknya.
     */
    public function test_user_can_list_own_categories(): void
    {
        $response = $this->getJson('/api/categories', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'total',
                     'data' => [
                         '*' => ['id', 'user_id', 'name', 'type', 'created_at', 'updated_at'],
                     ],
                 ]);
    }

    /**
     * 2. User tidak dapat melihat kategori user lain.
     */
    public function test_user_cannot_view_other_users_category(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherCategory = Category::create([
            'user_id' => $otherUser->id,
            'name' => 'Kategori Rahasia',
            'type' => 'expense',
        ]);

        $response = $this->getJson("/api/categories/{$otherCategory->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    /**
     * 3. User dapat membuat kategori (income dan expense).
     */
    public function test_user_can_create_expense_and_income_category(): void
    {
        // Buat kategori expense
        $expensePayload = [
            'name' => 'Kesehatan',
            'type' => 'expense',
        ];

        $res1 = $this->postJson('/api/categories', $expensePayload, ['X-User-Id' => $this->user->id]);
        $res1->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Kategori berhasil dibuat.',
                 'data' => [
                     'user_id' => $this->user->id,
                     'name' => 'Kesehatan',
                     'type' => 'expense',
                 ],
             ]);

        // Buat kategori income
        $incomePayload = [
            'name' => 'Dividen Investasi',
            'type' => 'income',
        ];

        $res2 = $this->postJson('/api/categories', $incomePayload, ['X-User-Id' => $this->user->id]);
        $res2->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'data' => [
                     'name' => 'Dividen Investasi',
                     'type' => 'income',
                 ],
             ]);
    }

    /**
     * 4. User dapat mengedit kategori miliknya (dan nama transaksi terkait ikut tersinkron).
     */
    public function test_user_can_edit_own_category(): void
    {
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Makan Siang',
            'date' => '2026-09-02',
        ]);

        $payload = [
            'name' => 'Kuliner & Makanan',
        ];

        $response = $this->putJson("/api/categories/{$this->categoryExpense->id}", $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Kategori berhasil diperbarui.',
                     'data' => [
                         'id' => $this->categoryExpense->id,
                         'name' => 'Kuliner & Makanan',
                     ],
                 ]);

        // Pastikan transaksi yang memakai nama lama ikut terupdate
        $this->assertDatabaseHas('transactions', [
            'category' => 'Kuliner & Makanan',
            'description' => 'Makan Siang',
        ]);
    }

    /**
     * 5. User dapat menghapus kategori yang aman dihapus (tidak memiliki transaksi).
     */
    public function test_user_can_delete_unused_category(): void
    {
        $unusedCategory = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Kategori Sementara',
            'type' => 'expense',
        ]);

        $response = $this->deleteJson("/api/categories/{$unusedCategory->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Kategori berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $unusedCategory->id,
        ]);
    }

    /**
     * 6. Kategori yang masih digunakan transaksi tidak dapat dihapus sembarangan.
     */
    public function test_category_used_in_transaction_cannot_be_deleted(): void
    {
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => $this->categoryExpense->name,
            'type' => 'expense',
            'amount' => 30000,
            'description' => 'Sarapan',
            'date' => '2026-09-02',
        ]);

        $response = $this->deleteJson("/api/categories/{$this->categoryExpense->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                 ]);

        $this->assertDatabaseHas('categories', [
            'id' => $this->categoryExpense->id,
            'name' => $this->categoryExpense->name,
        ]);
    }

    /**
     * 7. Validation nama kategori (wajib, tidak boleh kosong, tidak boleh duplikat).
     */
    public function test_category_name_validation(): void
    {
        // Kosong
        $resEmpty = $this->postJson('/api/categories', ['name' => '   ', 'type' => 'expense'], ['X-User-Id' => $this->user->id]);
        $resEmpty->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['name']]);

        // Duplikat
        $resDup = $this->postJson('/api/categories', ['name' => 'Makanan', 'type' => 'expense'], ['X-User-Id' => $this->user->id]);
        $resDup->assertStatus(422)
               ->assertJsonStructure(['errors' => ['name']]);
    }

    /**
     * 8. Validation tipe kategori (wajib, hanya income atau expense).
     */
    public function test_category_type_validation(): void
    {
        $payload = [
            'name' => 'Investasi Saham',
            'type' => 'unknown_type',
        ];

        $response = $this->postJson('/api/categories', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => ['type'],
                 ]);
    }

    /**
     * 9. Filter kategori berdasarkan type (income / expense).
     */
    public function test_can_filter_categories_by_type(): void
    {
        $response = $this->getJson('/api/categories?type=expense', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200);
        $categories = $response->json('data');

        foreach ($categories as $cat) {
            $this->assertEquals('expense', $cat['type']);
        }
    }
}
