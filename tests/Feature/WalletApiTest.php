<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

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
            ['opening_balance' => 500000, 'is_active' => true]
        );
    }

    /**
     * 1. User dapat melihat wallet miliknya.
     */
    public function test_user_can_list_own_wallets(): void
    {
        $response = $this->getJson('/api/wallets', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'total',
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'opening_balance',
                             'initial_balance',
                             'total_income',
                             'total_expense',
                             'current_balance',
                         ],
                     ],
                 ]);
    }

    /**
     * 2. User tidak dapat melihat wallet user lain.
     */
    public function test_user_cannot_view_other_users_wallet(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherWallet = Wallet::create([
            'user_id' => $otherUser->id,
            'name' => 'Bank Mandiri Rahasia',
            'opening_balance' => 10000000,
        ]);

        $response = $this->getJson("/api/wallets/{$otherWallet->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    /**
     * 3. User dapat membuat wallet.
     */
    public function test_user_can_create_wallet(): void
    {
        $payload = [
            'name' => 'Bank Jago',
            'initial_balance' => 1500000,
        ];

        $response = $this->postJson('/api/wallets', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Dompet berhasil dibuat.',
                     'data' => [
                         'user_id' => $this->user->id,
                         'name' => 'Bank Jago',
                         'initial_balance' => 1500000,
                         'current_balance' => 1500000,
                     ],
                 ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
            'name' => 'Bank Jago',
            'opening_balance' => 1500000,
        ]);
    }

    /**
     * 4. User dapat mengedit wallet miliknya (dan nama transaksi terkait ikut tersinkron).
     */
    public function test_user_can_edit_own_wallet(): void
    {
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Makan',
            'date' => '2026-09-02',
        ]);

        $payload = [
            'name' => 'DANA Premium',
            'initial_balance' => 750000,
        ];

        $response = $this->putJson("/api/wallets/{$this->wallet->id}", $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Dompet berhasil diperbarui.',
                     'data' => [
                         'name' => 'DANA Premium',
                         'initial_balance' => 750000,
                         'total_expense' => 50000,
                         'current_balance' => 700000, // 750000 - 50000
                     ],
                 ]);

        // Pastikan transaksi terkait ikut terupdate namanya
        $this->assertDatabaseHas('transactions', [
            'wallet' => 'DANA Premium',
            'description' => 'Makan',
        ]);
    }

    /**
     * 5. User dapat menghapus wallet yang aman dihapus (0 transaksi).
     */
    public function test_user_can_delete_wallet_without_transactions(): void
    {
        $emptyWallet = Wallet::create([
            'user_id' => $this->user->id,
            'name' => 'GoPay Kosong',
            'opening_balance' => 0,
        ]);

        $response = $this->deleteJson("/api/wallets/{$emptyWallet->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Dompet berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('wallets', [
            'id' => $emptyWallet->id,
        ]);
    }

    /**
     * 6. Wallet dengan transaksi tidak dapat dihapus secara sembarangan.
     */
    public function test_wallet_with_transactions_cannot_be_deleted(): void
    {
        $this->user->transactions()->create([
            'wallet' => $this->wallet->name,
            'category' => 'Tagihan',
            'type' => 'expense',
            'amount' => 100000,
            'description' => 'Bayar Listrik',
            'date' => '2026-09-02',
        ]);

        $response = $this->deleteJson("/api/wallets/{$this->wallet->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                 ]);

        // Dompet tetap ada di database
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'name' => $this->wallet->name,
        ]);
    }

    /**
     * 7. Initial balance tervalidasi (tidak boleh negatif atau bukan angka).
     */
    public function test_initial_balance_validation(): void
    {
        $payload = [
            'name' => 'Wallet Invalid',
            'initial_balance' => -100000,
        ];

        $response = $this->postJson('/api/wallets', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => ['initial_balance'],
                 ]);
    }

    /**
     * 8, 9, 10. Saldo wallet dihitung dengan benar (initial + income - expense).
     */
    public function test_wallet_balance_calculated_correctly_with_income_and_expense(): void
    {
        // Initial: 500.000
        // Income: 300.000
        $this->user->transactions()->create([
            'wallet' => $this->wallet->name,
            'category' => 'Pemasukan',
            'type' => 'income',
            'amount' => 300000,
            'description' => 'Transfer Masuk',
            'date' => '2026-09-02',
        ]);

        // Expense: 150.000
        $this->user->transactions()->create([
            'wallet' => $this->wallet->name,
            'category' => 'Belanja',
            'type' => 'expense',
            'amount' => 150000,
            'description' => 'Belanja Bulanan',
            'date' => '2026-09-02',
        ]);

        $response = $this->getJson("/api/wallets/{$this->wallet->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $this->wallet->id,
                         'initial_balance' => 500000,
                         'total_income' => 300000,
                         'total_expense' => 150000,
                         'current_balance' => 650000, // 500.000 + 300.000 - 150.000 = 650.000
                     ],
                 ]);
    }

    /**
     * 11. Edit transaksi memengaruhi saldo secara otomatis.
     */
    public function test_editing_transaction_updates_wallet_balance(): void
    {
        $tx = $this->user->transactions()->create([
            'wallet' => $this->wallet->name,
            'category' => 'Belanja',
            'type' => 'expense',
            'amount' => 100000,
            'description' => 'Beli Baju',
            'date' => '2026-09-02',
        ]);

        // Sebelum diedit, saldo = 500.000 - 100.000 = 400.000
        $before = $this->getJson("/api/wallets/{$this->wallet->id}", ['X-User-Id' => $this->user->id]);
        $before->assertJsonPath('data.current_balance', 400000);

        // Edit nominal expense menjadi 200.000
        $this->putJson("/api/transactions/{$tx->id}", ['amount' => 200000], ['X-User-Id' => $this->user->id])
             ->assertStatus(200);

        // Setelah diedit, saldo = 500.000 - 200.000 = 300.000
        $after = $this->getJson("/api/wallets/{$this->wallet->id}", ['X-User-Id' => $this->user->id]);
        $after->assertJsonPath('data.current_balance', 300000);
    }

    /**
     * 12. Delete transaksi memengaruhi saldo secara otomatis.
     */
    public function test_deleting_transaction_updates_wallet_balance(): void
    {
        $tx = $this->user->transactions()->create([
            'wallet' => $this->wallet->name,
            'category' => 'Belanja',
            'type' => 'expense',
            'amount' => 100000,
            'description' => 'Beli Barang',
            'date' => '2026-09-02',
        ]);

        // Saldo dengan expense = 400.000
        $withTx = $this->getJson("/api/wallets/{$this->wallet->id}", ['X-User-Id' => $this->user->id]);
        $withTx->assertJsonPath('data.current_balance', 400000);

        // Hapus transaksi
        $this->deleteJson("/api/transactions/{$tx->id}", [], ['X-User-Id' => $this->user->id])
             ->assertStatus(200);

        // Saldo kembali ke initial balance = 500.000
        $afterDelete = $this->getJson("/api/wallets/{$this->wallet->id}", ['X-User-Id' => $this->user->id]);
        $afterDelete->assertJsonPath('data.current_balance', 500000);
    }
}
