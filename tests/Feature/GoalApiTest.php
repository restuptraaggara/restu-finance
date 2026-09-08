<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalApiTest extends TestCase
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
                'password' => bcrypt('password'),
            ]
        );
    }

    /**
     * 1. User dapat melihat goal miliknya beserta kalkulasi progress.
     */
    public function test_user_can_list_own_goals(): void
    {
        Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Beli MacBook Pro',
            'target_amount' => 25000000,
            'saved_amount' => 5000000,
            'deadline' => '2026-12-31',
        ]);

        $response = $this->getJson('/api/goals', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'total',
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'target_amount',
                             'saved_amount',
                             'saved',
                             'remaining',
                             'percentage',
                             'is_achieved',
                             'deadline',
                         ],
                     ],
                 ]);
    }

    /**
     * 2. User tidak dapat melihat goal user lain.
     */
    public function test_user_cannot_view_other_users_goal(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $otherGoal = Goal::create([
            'user_id' => $otherUser->id,
            'name' => 'Target Rahasia',
            'target_amount' => 100000000,
            'saved_amount' => 0,
            'deadline' => '2026-12-31',
        ]);

        $response = $this->getJson("/api/goals/{$otherGoal->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                 ]);
    }

    /**
     * 3. User dapat membuat goal baru.
     */
    public function test_user_can_create_goal(): void
    {
        $payload = [
            'name' => 'Dana Darurat',
            'target_amount' => 10000000,
            'saved_amount' => 2000000,
            'deadline' => '2026-12-31',
        ];

        $response = $this->postJson('/api/goals', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Target tabungan berhasil dibuat.',
                     'data' => [
                         'user_id' => $this->user->id,
                         'name' => 'Dana Darurat',
                         'target_amount' => 10000000,
                         'saved' => 2000000,
                         'remaining' => 8000000,
                         'percentage' => 20.0,
                     ],
                 ]);

        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'name' => 'Dana Darurat',
            'target_amount' => 10000000,
        ]);
    }

    /**
     * 4. User dapat mengedit goal miliknya (dan nama pada transaksi terkait ikut tersinkron).
     */
    public function test_user_can_edit_goal(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Beli Motor',
            'target_amount' => 20000000,
            'saved_amount' => 0,
            'deadline' => '2026-12-31',
        ]);

        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Tabungan',
            'type' => 'expense',
            'amount' => 1000000,
            'description' => 'Nabung Motor',
            'goal' => 'Beli Motor',
            'date' => '2026-09-02',
        ]);

        $payload = [
            'name' => 'Beli Motor Listrik',
            'target_amount' => 22000000,
        ];

        $response = $this->putJson("/api/goals/{$goal->id}", $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Target tabungan berhasil diperbarui.',
                     'data' => [
                         'id' => $goal->id,
                         'name' => 'Beli Motor Listrik',
                         'target_amount' => 22000000,
                     ],
                 ]);

        // Transaksi terkait otomatis terupdate nama goal-nya
        $this->assertDatabaseHas('transactions', [
            'goal' => 'Beli Motor Listrik',
            'description' => 'Nabung Motor',
        ]);
    }

    /**
     * 5 & 12. Menghapus goal tidak boleh menghapus transaksi terkait.
     */
    public function test_deleting_goal_preserves_transactions(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Liburan Bali',
            'target_amount' => 5000000,
            'saved_amount' => 0,
            'deadline' => '2026-11-30',
        ]);

        $tx = $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Tabungan',
            'type' => 'expense',
            'amount' => 1500000,
            'description' => 'Nabung Tiket',
            'goal' => 'Liburan Bali',
            'date' => '2026-09-02',
        ]);

        $response = $this->deleteJson("/api/goals/{$goal->id}", [], ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Target tabungan berhasil dihapus.',
                 ]);

        $this->assertDatabaseMissing('goals', [
            'id' => $goal->id,
        ]);

        // Transaksi tetap ada dan field goal di-unlink (null)
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'description' => 'Nabung Tiket',
            'goal' => null,
        ]);
    }

    /**
     * 6, 7, 8. Validasi nama, target_amount, deadline.
     */
    public function test_goal_validation(): void
    {
        // Target negatif & nama kosong & deadline kosong
        $payload = [
            'name' => '   ',
            'target_amount' => -1000,
        ];

        $response = $this->postJson('/api/goals', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => ['name', 'target_amount', 'deadline'],
                 ]);
    }

    /**
     * 9 & 10. Progress goal dihitung secara benar dari saved_amount dan transaksi terkait.
     */
    public function test_goal_progress_calculated_with_transactions(): void
    {
        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Beli Laptop',
            'target_amount' => 5000000, // Target: 5.000.000
            'saved_amount' => 500000,  // Base: 500.000
            'deadline' => '2026-12-31',
        ]);

        // Transaksi 1 terkait goal: 1.000.000
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Tabungan',
            'type' => 'expense',
            'amount' => 1000000,
            'description' => 'Nabung Laptop 1',
            'goal' => 'Beli Laptop',
            'date' => '2026-09-02',
        ]);

        // Transaksi 2 terkait goal: 500.000
        $this->user->transactions()->create([
            'wallet' => 'DANA',
            'category' => 'Tabungan',
            'type' => 'expense',
            'amount' => 500000,
            'description' => 'Nabung Laptop 2',
            'goal' => 'Beli Laptop',
            'date' => '2026-09-02',
        ]);

        $response = $this->getJson("/api/goals/{$goal->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $goal->id,
                         'target' => 5000000,
                         'saved' => 2000000,     // 500.000 + 1.000.000 + 500.000 = 2.000.000
                         'remaining' => 3000000, // 5.000.000 - 2.000.000 = 3.000.000
                         'percentage' => 40.0,   // (2.000.000 / 5.000.000) * 100% = 40%
                         'is_achieved' => false,
                         'transactions_count' => 2,
                     ],
                 ]);
    }

    /**
     * 11. Transaksi user lain tidak memengaruhi progress goal user ini.
     */
    public function test_other_users_transactions_do_not_affect_goal_progress(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('password'),
        ]);

        $goal = Goal::create([
            'user_id' => $this->user->id,
            'name' => 'Beli Kamera',
            'target_amount' => 10000000,
            'saved_amount' => 0,
            'deadline' => '2026-12-31',
        ]);

        // User lain membuat transaksi dengan nama goal yang sama
        $otherUser->transactions()->create([
            'wallet' => 'Bank Mandiri',
            'category' => 'Tabungan',
            'type' => 'expense',
            'amount' => 5000000,
            'description' => 'Nabung Kamera Orang Lain',
            'goal' => 'Beli Kamera',
            'date' => '2026-09-02',
        ]);

        $response = $this->getJson("/api/goals/{$goal->id}", ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.saved', 0)
                 ->assertJsonPath('data.remaining', 10000000)
                 ->assertJsonPath('data.transactions_count', 0);
    }
}
