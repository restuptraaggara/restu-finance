<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;
    protected Category $category;

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

        $this->wallet = Wallet::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'DANA'],
            ['opening_balance' => 0, 'is_active' => true]
        );

        $this->category = Category::firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Makanan'],
            ['type' => 'expense']
        );
    }

    public function test_can_list_transactions(): void
    {
        $response = $this->getJson('/api/transactions', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'total',
                     'data',
                 ]);
    }

    public function test_can_create_transaction_with_name_attributes(): void
    {
        $payload = [
            'type' => 'expense',
            'amount' => 50000,
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'transaction_date' => '2026-09-02',
            'description' => 'Makan Siang Nasi Padang',
            'method' => 'E-wallet',
            'note' => 'Pakai kupon',
        ];

        $response = $this->postJson('/api/transactions', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Transaksi berhasil dibuat.',
                     'data' => [
                         'user_id' => $this->user->id,
                         'type' => 'expense',
                         'amount' => 50000,
                         'wallet' => 'DANA',
                         'category' => 'Makanan',
                         'description' => 'Makan Siang Nasi Padang',
                     ],
                 ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 50000,
            'description' => 'Makan Siang Nasi Padang',
        ]);
    }

    public function test_can_create_transaction_with_id_attributes(): void
    {
        $payload = [
            'type' => 'income',
            'amount' => 2500000,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'date' => '2026-09-01',
            'description' => 'Gaji Freelance',
        ];

        $response = $this->postJson('/api/transactions', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'type' => 'income',
                         'amount' => 2500000,
                         'wallet' => 'DANA',
                         'category' => 'Makanan',
                     ],
                 ]);
    }

    public function test_validation_fails_on_invalid_amount_and_type(): void
    {
        $payload = [
            'type' => 'invalid_type',
            'amount' => -500,
        ];

        $response = $this->postJson('/api/transactions', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => ['type', 'amount'],
                 ]);
    }

    public function test_can_view_single_transaction(): void
    {
        $transaction = $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Transportasi',
            'type' => 'expense',
            'amount' => 25000,
            'description' => 'Ojek Online',
            'date' => '2026-09-02',
        ]);

        $response = $this->getJson("/api/transactions/{$transaction->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $transaction->id,
                         'description' => 'Ojek Online',
                         'amount' => 25000,
                     ],
                 ]);
    }

    public function test_can_update_transaction(): void
    {
        $transaction = $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 30000,
            'description' => 'Bakso',
            'date' => '2026-09-02',
        ]);

        $payload = [
            'amount' => 35000,
            'description' => 'Bakso Spesial + Es Teh',
        ];

        $response = $this->putJson("/api/transactions/{$transaction->id}", $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $transaction->id,
                         'amount' => 35000,
                         'description' => 'Bakso Spesial + Es Teh',
                     ],
                 ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 35000,
            'description' => 'Bakso Spesial + Es Teh',
        ]);
    }

    public function test_can_delete_transaction(): void
    {
        $transaction = $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Lainnya',
            'type' => 'expense',
            'amount' => 15000,
            'description' => 'Parkir',
            'date' => '2026-09-02',
        ]);

        $response = $this->deleteJson("/api/transactions/{$transaction->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Transaksi berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_user_cannot_access_other_users_transaction(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherTransaction = $otherUser->transactions()->create([
            'wallet' => 'Bank BCA',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 10000000,
            'description' => 'Gaji Bulanan Lain',
            'date' => '2026-09-02',
        ]);

        // User Restu mencoba mengakses transaksi milik Other User
        $response = $this->getJson("/api/transactions/{$otherTransaction->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                 ]);
    }
}
