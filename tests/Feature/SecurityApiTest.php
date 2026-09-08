<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Goal;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::create([
            'name' => 'User Alpha',
            'email' => 'alpha@dev.local',
            'password' => bcrypt('password123'),
            'theme' => 'dark',
            'currency' => 'IDR',
            'language' => 'id',
        ]);

        $this->userB = User::create([
            'name' => 'User Beta',
            'email' => 'beta@dev.local',
            'password' => bcrypt('password456'),
            'theme' => 'light',
            'currency' => 'USD',
            'language' => 'en',
        ]);
    }

    /**
     * 1. Seluruh endpoint data finansial menolak request unauthenticated (HTTP 401).
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $endpoints = [
            ['GET', '/api/init'],
            ['GET', '/api/transactions'],
            ['POST', '/api/transactions'],
            ['GET', '/api/wallets'],
            ['POST', '/api/wallets'],
            ['GET', '/api/categories'],
            ['POST', '/api/categories'],
            ['GET', '/api/budgets'],
            ['POST', '/api/budgets'],
            ['GET', '/api/goals'],
            ['POST', '/api/goals'],
            ['GET', '/api/profile'],
            ['PUT', '/api/profile'],
            ['GET', '/api/recurring-transactions'],
            ['POST', '/api/recurring-transactions'],
            ['GET', '/api/lan-info'],
            ['GET', '/api/me'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401)
                     ->assertJson([
                         'success' => false,
                     ]);
        }
    }

    /**
     * 2. Login berhasil dengan kredensial yang valid.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $payload = [
            'email' => 'alpha@dev.local',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Login berhasil.',
                     'data' => [
                         'user' => [
                             'id' => $this->userA->id,
                             'name' => 'User Alpha',
                             'email' => 'alpha@dev.local',
                         ],
                     ],
                 ])
                 ->assertJsonMissingPath('data.user.password');

        $this->assertAuthenticatedAs($this->userA);
    }

    /**
     * 3. Login gagal jika password salah (HTTP 401).
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $payload = [
            'email' => 'alpha@dev.local',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                 ]);

        $this->assertGuest();
    }

    /**
     * 4. Logout menghapus sesi autentikasi.
     */
    public function test_user_can_logout_and_session_invalidates(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logout berhasil.',
                 ]);

        $this->assertGuest();
    }

    /**
     * 5. User A tidak dapat melihat atau memanipulasi Transaksi milik User B.
     */
    public function test_cross_user_transaction_isolation(): void
    {
        $txB = Transaction::create([
            'user_id' => $this->userB->id,
            'wallet' => 'Bank Mandiri',
            'category' => 'Rahasia',
            'type' => 'expense',
            'amount' => 500000,
            'description' => 'Transaksi Rahasia B',
            'date' => '2026-09-02',
        ]);

        // User A mencoba melihat detail transaksi B
        $resShow = $this->actingAs($this->userA)->getJson("/api/transactions/{$txB->id}");
        $resShow->assertStatus(404);

        // User A mencoba update transaksi B
        $resUpdate = $this->actingAs($this->userA)->putJson("/api/transactions/{$txB->id}", ['description' => 'Hacked']);
        $resUpdate->assertStatus(404);

        // User A mencoba delete transaksi B
        $resDelete = $this->actingAs($this->userA)->deleteJson("/api/transactions/{$txB->id}");
        $resDelete->assertStatus(404);

        // Pastikan transaksi B di database tidak berubah
        $this->assertDatabaseHas('transactions', [
            'id' => $txB->id,
            'description' => 'Transaksi Rahasia B',
        ]);
    }

    /**
     * 6. User A tidak dapat memanipulasi Wallet milik User B.
     */
    public function test_cross_user_wallet_isolation(): void
    {
        $walletB = Wallet::create([
            'user_id' => $this->userB->id,
            'name' => 'Dompet Rahasia B',
            'opening_balance' => 10000000,
            'is_active' => true,
        ]);

        $resShow = $this->actingAs($this->userA)->getJson("/api/wallets/{$walletB->id}");
        $resShow->assertStatus(404);

        $resDelete = $this->actingAs($this->userA)->deleteJson("/api/wallets/{$walletB->id}");
        $resDelete->assertStatus(404);

        $this->assertDatabaseHas('wallets', ['id' => $walletB->id]);
    }

    /**
     * 7. User A tidak dapat memanipulasi Budget & Goal milik User B.
     */
    public function test_cross_user_budget_and_goal_isolation(): void
    {
        $budgetB = Budget::create([
            'user_id' => $this->userB->id,
            'category' => 'Anggaran Rahasia B',
            'limit_amount' => 5000000,
        ]);

        $goalB = Goal::create([
            'user_id' => $this->userB->id,
            'name' => 'Goal Rahasia B',
            'target_amount' => 50000000,
            'saved_amount' => 1000000,
            'deadline' => '2026-12-31',
        ]);

        $this->actingAs($this->userA)->getJson("/api/budgets/{$budgetB->id}")->assertStatus(404);
        $this->actingAs($this->userA)->getJson("/api/goals/{$goalB->id}")->assertStatus(404);

        $this->actingAs($this->userA)->deleteJson("/api/budgets/{$budgetB->id}")->assertStatus(404);
        $this->actingAs($this->userA)->deleteJson("/api/goals/{$goalB->id}")->assertStatus(404);
    }

    /**
     * 8. Mass Assignment Protection: User tidak dapat menyusupkan `user_id` milik orang lain saat membuat transaksi.
     */
    public function test_mass_assignment_user_id_tampering_is_prevented(): void
    {
        $payload = [
            'user_id' => $this->userB->id, // Percobaan injeksi user_id target
            'type' => 'expense',
            'amount' => 75000,
            'category' => 'Makanan',
            'wallet' => 'BCA Debit',
            'description' => 'Makan Siang User A',
            'date' => '2026-09-02',
        ];

        $response = $this->actingAs($this->userA)->postJson('/api/transactions', $payload);

        $response->assertStatus(201);
        $createdTx = $response->json('data');

        // Pastikan kepemilikan tetap terkunci pada User A yang terautentikasi, BUKAN User B
        $this->assertEquals($this->userA->id, $createdTx['user_id']);
        $this->assertDatabaseHas('transactions', [
            'id' => $createdTx['id'],
            'user_id' => $this->userA->id,
        ]);
        $this->assertDatabaseMissing('transactions', [
            'id' => $createdTx['id'],
            'user_id' => $this->userB->id,
        ]);
    }

    /**
     * 9. Mass Assignment Protection: User tidak dapat menyusupkan `user_id` milik orang lain saat membuat wallet.
     */
    public function test_mass_assignment_user_id_tampering_on_wallets_is_prevented(): void
    {
        $payload = [
            'user_id' => $this->userB->id,
            'name' => 'Dompet Alpha Khusus',
            'opening_balance' => 500000,
        ];

        $response = $this->actingAs($this->userA)->postJson('/api/wallets', $payload);

        $response->assertStatus(201);
        $wallet = $response->json('data');

        $this->assertEquals($this->userA->id, $wallet['user_id']);
        $this->assertDatabaseHas('wallets', [
            'id' => $wallet['id'],
            'user_id' => $this->userA->id,
        ]);
    }

    /**
     * 10. User dapat mengganti password dengan menyertakan password saat ini yang valid.
     */
    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $payload = [
            'current_password' => 'password123',
            'password' => 'newSecretPass2026',
        ];

        $response = $this->actingAs($this->userA)->putJson('/api/profile', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Profil pengguna berhasil diperbarui.',
                 ]);

        $this->userA->refresh();
        $this->assertTrue(Hash::check('newSecretPass2026', $this->userA->password));
    }

    /**
     * 11. Ganti password ditolak jika password saat ini salah.
     */
    public function test_password_change_fails_with_invalid_current_password(): void
    {
        $payload = [
            'current_password' => 'wrongPassword',
            'password' => 'newSecretPass2026',
        ];

        $response = $this->actingAs($this->userA)->putJson('/api/profile', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['current_password']);

        $this->userA->refresh();
        $this->assertTrue(Hash::check('password123', $this->userA->password));
    }

    /**
     * 12. Ganti password ditolak jika password baru kurang dari 8 karakter.
     */
    public function test_password_change_fails_with_short_new_password(): void
    {
        $payload = [
            'current_password' => 'password123',
            'password' => 'short',
        ];

        $response = $this->actingAs($this->userA)->putJson('/api/profile', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /**
     * 13. Isolasi Recurring Transaction: User A tidak dapat melihat, mengubah, atau menghapus recurring User B.
     */
    public function test_cross_user_recurring_transaction_isolation(): void
    {
        $rtB = RecurringTransaction::create([
            'user_id' => $this->userB->id,
            'wallet' => 'Dompet Beta',
            'category' => 'Tagihan Beta',
            'type' => 'expense',
            'amount' => 250000,
            'description' => 'Langganan Rahasia B',
            'frequency' => 'monthly',
            'start_date' => '2026-09-01',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        $this->actingAs($this->userA)->getJson("/api/recurring-transactions/{$rtB->id}")->assertStatus(404);
        $this->actingAs($this->userA)->putJson("/api/recurring-transactions/{$rtB->id}", ['amount' => 999999])->assertStatus(404);
        $this->actingAs($this->userA)->deleteJson("/api/recurring-transactions/{$rtB->id}")->assertStatus(404);
    }

    /**
     * 14. Rate Limiting pada Login: Setelah 5 kali percobaan gagal berturut-turut, request dibatasi (HTTP 429).
     */
    public function test_login_rate_limiting_throttles_after_five_failed_attempts(): void
    {
        $payload = [
            'email' => 'alpha@dev.local',
            'password' => 'wrong_attempt',
        ];

        // Lakukan 5 request gagal pertama -> status 401
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', $payload);
            $response->assertStatus(401);
        }

        // Request ke-6 harus dibatasi oleh rate limiter -> status 429 Too Many Requests
        $response = $this->postJson('/api/login', $payload);
        $response->assertStatus(429);
    }
}
