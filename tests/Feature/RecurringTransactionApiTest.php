<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RecurringTransactionProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Wallet $walletA;
    protected Category $categoryA;
    protected Wallet $walletB;
    protected Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::create([
            'name' => 'Restu User A',
            'email' => 'userA@dev.local',
            'password' => bcrypt('password123'),
            'theme' => 'dark',
            'currency' => 'IDR',
            'language' => 'id',
        ]);

        $this->walletA = Wallet::create([
            'user_id' => $this->userA->id,
            'name' => 'BCA Utama',
            'opening_balance' => 5000000,
            'is_active' => true,
        ]);

        $this->categoryA = Category::create([
            'user_id' => $this->userA->id,
            'name' => 'Gaji',
            'type' => 'income',
        ]);

        $this->userB = User::create([
            'name' => 'User B',
            'email' => 'userB@dev.local',
            'password' => bcrypt('password123'),
            'theme' => 'light',
            'currency' => 'USD',
            'language' => 'en',
        ]);

        $this->walletB = Wallet::create([
            'user_id' => $this->userB->id,
            'name' => 'Mandiri B',
            'opening_balance' => 2000000,
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'user_id' => $this->userB->id,
            'name' => 'Bonus B',
            'type' => 'income',
        ]);
    }

    /**
     * 1. Unauthenticated request to recurring endpoints is rejected (401).
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/recurring-transactions')->assertStatus(401);
        $this->postJson('/api/recurring-transactions', [])->assertStatus(401);
        $this->getJson('/api/recurring-transactions/1')->assertStatus(401);
        $this->putJson('/api/recurring-transactions/1', [])->assertStatus(401);
        $this->deleteJson('/api/recurring-transactions/1')->assertStatus(401);
        $this->postJson('/api/recurring-transactions/process')->assertStatus(401);
    }

    /**
     * 2. User can create a recurring transaction.
     */
    public function test_user_can_create_recurring_transaction(): void
    {
        $payload = [
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 8000000,
            'description' => 'Gaji Bulanan PT Maju',
            'frequency' => 'monthly',
            'start_date' => '2026-09-01',
            'next_date' => '2026-10-01',
            'end_date' => '2027-09-01',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->userA)->postJson('/api/recurring-transactions', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Recurring transaction berhasil dibuat.',
                     'data' => [
                         'wallet' => 'BCA Utama',
                         'category' => 'Gaji',
                         'type' => 'income',
                         'amount' => 8000000,
                         'description' => 'Gaji Bulanan PT Maju',
                         'frequency' => 'monthly',
                         'start_date' => '2026-09-01',
                         'next_date' => '2026-10-01',
                         'end_date' => '2027-09-01',
                         'is_active' => true,
                     ],
                 ]);

        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $this->userA->id,
            'description' => 'Gaji Bulanan PT Maju',
            'amount' => 8000000,
        ]);
    }

    /**
     * 3. User can view their own recurring transactions list.
     */
    public function test_user_can_view_own_recurring_transactions_list(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji A',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/recurring-transactions');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ])
                 ->assertJsonCount(1, 'data');
    }

    /**
     * 4. User can view specific recurring transaction.
     */
    public function test_user_can_view_specific_recurring_transaction(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Detail A',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA)->getJson("/api/recurring-transactions/{$rt->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $rt->id,
                         'description' => 'Gaji Detail A',
                     ],
                 ]);
    }

    /**
     * 5. User can update their own recurring transaction.
     */
    public function test_user_can_update_own_recurring_transaction(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Lama',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA)->putJson("/api/recurring-transactions/{$rt->id}", [
            'amount' => 6000000,
            'description' => 'Gaji Baru Naik',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'amount' => 6000000,
                         'description' => 'Gaji Baru Naik',
                     ],
                 ]);

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $rt->id,
            'amount' => 6000000,
            'description' => 'Gaji Baru Naik',
        ]);
    }

    /**
     * 6. User can delete their own recurring transaction.
     */
    public function test_user_can_delete_own_recurring_transaction(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Mau Dihapus',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA)->deleteJson("/api/recurring-transactions/{$rt->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        $this->assertDatabaseMissing('recurring_transactions', [
            'id' => $rt->id,
        ]);
    }

    /**
     * 7. Cross-user isolation: User A cannot view, edit, or delete User B's recurring transaction.
     */
    public function test_cross_user_recurring_isolation(): void
    {
        $rtB = RecurringTransaction::create([
            'user_id' => $this->userB->id,
            'wallet' => 'Mandiri B',
            'category' => 'Bonus B',
            'type' => 'income',
            'amount' => 10000000,
            'description' => 'Rahasia User B',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        // User A tries to view B's recurring
        $this->actingAs($this->userA)->getJson("/api/recurring-transactions/{$rtB->id}")->assertStatus(404);

        // User A tries to edit B's recurring
        $this->actingAs($this->userA)->putJson("/api/recurring-transactions/{$rtB->id}", [
            'description' => 'Hacked by A',
        ])->assertStatus(404);

        // User A tries to delete B's recurring
        $this->actingAs($this->userA)->deleteJson("/api/recurring-transactions/{$rtB->id}")->assertStatus(404);

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $rtB->id,
            'description' => 'Rahasia User B',
        ]);
    }

    /**
     * 8. Validation tests: amount, type, frequency, end_date before start_date.
     */
    public function test_validation_errors(): void
    {
        // 8a. Invalid amount (<= 0 or not numeric)
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 0,
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

        // 8b. Invalid type
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'transfer',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);

        // 8c. Invalid frequency
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 100000,
            'frequency' => 'bi-weekly',
            'next_date' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['frequency']);

        // 8d. End date before start date
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 100000,
            'frequency' => 'monthly',
            'start_date' => '2026-10-01',
            'next_date' => '2026-10-01',
            'end_date' => '2026-09-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    /**
     * 9. Wallet and Category must belong to the authenticated user.
     */
    public function test_wallet_and_category_must_belong_to_user(): void
    {
        // User A tries to use User B's wallet
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'Mandiri B', // Belongs to userB
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
        ])->assertStatus(422);

        // User A tries to use User B's category
        $this->actingAs($this->userA)->postJson('/api/recurring-transactions', [
            'wallet' => 'BCA Utama',
            'category' => 'Bonus B', // Belongs to userB
            'type' => 'income',
            'amount' => 100000,
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
        ])->assertStatus(422);
    }

    /**
     * 10. Processor creates real transaction when recurring is due.
     */
    public function test_processor_creates_real_transaction(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 7500000,
            'description' => 'Gaji Kantor',
            'frequency' => 'monthly',
            'start_date' => '2026-08-01',
            'next_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $processor = new RecurringTransactionProcessor();
        $result = $processor->processForUser($this->userA);

        $this->assertEquals(1, $result['processed']);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 7500000,
            'date' => '2026-09-01',
        ]);

        // next_date must have advanced to 2026-10-01
        $rt->refresh();
        $this->assertEquals('2026-10-01', $rt->next_date->toDateString());
    }

    /**
     * 11. Frequency calculation for daily, weekly, monthly, yearly.
     */
    public function test_frequency_advancements(): void
    {
        $baseDate = '2026-01-01';

        $daily = new RecurringTransaction(['frequency' => 'daily']);
        $this->assertEquals('2026-01-02', $daily->calculateNextDate($baseDate)->toDateString());

        $weekly = new RecurringTransaction(['frequency' => 'weekly']);
        $this->assertEquals('2026-01-08', $weekly->calculateNextDate($baseDate)->toDateString());

        $monthly = new RecurringTransaction(['frequency' => 'monthly']);
        $this->assertEquals('2026-02-01', $monthly->calculateNextDate($baseDate)->toDateString());

        $yearly = new RecurringTransaction(['frequency' => 'yearly']);
        $this->assertEquals('2027-01-01', $yearly->calculateNextDate($baseDate)->toDateString());
    }

    /**
     * 12. Duplicate protection: running processor twice on the same day does not duplicate transactions.
     */
    public function test_processor_duplicate_protection(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Proteksi Duplikat',
            'frequency' => 'monthly',
            'next_date' => Carbon::today()->toDateString(),
            'is_active' => true,
        ]);

        $processor = new RecurringTransactionProcessor();

        // First run: should create 1 transaction
        $result1 = $processor->processForUser($this->userA);
        $this->assertEquals(1, $result1['processed']);
        $this->assertEquals(1, Transaction::where('user_id', $this->userA->id)->count());

        // Second run on same day: should create 0 additional transactions
        $result2 = $processor->processForUser($this->userA);
        $this->assertEquals(0, $result2['processed']);
        $this->assertEquals(1, Transaction::where('user_id', $this->userA->id)->count());
    }

    /**
     * 13. Multiple missed schedules are processed sequentially.
     */
    public function test_multiple_missed_schedules_processed(): void
    {
        // Scheduled on 2026-06-01 monthly. Today is in September 2026.
        // It should process 2026-06-01, 2026-07-01, 2026-08-01, 2026-09-01 (4 transactions)
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Missed',
            'frequency' => 'monthly',
            'next_date' => '2026-06-01',
            'is_active' => true,
        ]);

        $processor = new RecurringTransactionProcessor();
        $result = $processor->processForUser($this->userA);

        $this->assertEquals(4, $result['processed']);
        $this->assertEquals(4, Transaction::where('user_id', $this->userA->id)->count());

        $rt->refresh();
        $this->assertEquals('2026-10-01', $rt->next_date->toDateString());
    }

    /**
     * 14. End date is respected and recurring transaction is deactivated.
     */
    public function test_end_date_is_respected_and_deactivates(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Kontrak Selesai',
            'frequency' => 'monthly',
            'next_date' => '2026-08-01',
            'end_date' => '2026-08-15',
            'is_active' => true,
        ]);

        $processor = new RecurringTransactionProcessor();
        $result = $processor->processForUser($this->userA);

        // Should create 1 transaction (2026-08-01), then next_date would be 2026-09-01 which is > 2026-08-15, so deactivated
        $this->assertEquals(1, $result['processed']);

        $rt->refresh();
        $this->assertFalse((bool)$rt->is_active);
    }

    /**
     * 15. Inactive recurring transaction is ignored by processor.
     */
    public function test_inactive_recurring_is_not_processed(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Inactive Recurring',
            'frequency' => 'monthly',
            'next_date' => '2026-08-01',
            'is_active' => false,
        ]);

        $processor = new RecurringTransactionProcessor();
        $result = $processor->processForUser($this->userA);

        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, Transaction::where('user_id', $this->userA->id)->count());
    }

    /**
     * 16. Generated transactions have correct ownership and details.
     */
    public function test_generated_transaction_ownership_and_details(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 12500000,
            'description' => 'Bonus Tahunan',
            'frequency' => 'yearly',
            'next_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $processor = new RecurringTransactionProcessor();
        $processor->processForUser($this->userA);

        $tx = Transaction::where('user_id', $this->userA->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals($this->userA->id, $tx->user_id);
        $this->assertEquals('BCA Utama', $tx->wallet);
        $this->assertEquals('Gaji', $tx->category);
        $this->assertEquals('income', $tx->type);
        $this->assertEquals(12500000, $tx->amount);
        $this->assertEquals('2026-09-01', $tx->date->toDateString());
    }

    /**
     * 17. Artisan command recurring:process runs successfully.
     */
    public function test_artisan_command_recurring_process(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 3000000,
            'description' => 'Artisan Test',
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $this->artisan('recurring:process', ['--user' => $this->userA->id])
             ->assertSuccessful();

        $this->assertEquals(1, Transaction::where('user_id', $this->userA->id)->count());
    }

    /**
     * 18. API process endpoint POST /api/recurring-transactions/process works for auth user.
     */
    public function test_api_process_endpoint_works_for_auth_user(): void
    {
        RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 4500000,
            'description' => 'API Process Test',
            'frequency' => 'monthly',
            'next_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA)->postJson('/api/recurring-transactions/process');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'processed' => 1,
                     ],
                 ]);

        $this->assertEquals(1, Transaction::where('user_id', $this->userA->id)->count());
    }

    /**
     * 19. Mass assignment protection: User cannot inject another user's user_id.
     */
    public function test_mass_assignment_user_id_tampering_is_prevented(): void
    {
        $payload = [
            'user_id' => $this->userB->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Tamper Test',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
        ];

        $response = $this->actingAs($this->userA)->postJson('/api/recurring-transactions', $payload);

        $response->assertStatus(201);
        $data = $response->json('data');

        // Verify the created record belongs to userA, not userB
        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $data['id'],
            'user_id' => $this->userA->id,
            'description' => 'Tamper Test',
        ]);
        $this->assertDatabaseMissing('recurring_transactions', [
            'id' => $data['id'],
            'user_id' => $this->userB->id,
        ]);
    }

    /**
     * 20. Update validation: Cannot change wallet/category to another user's wallet/category.
     */
    public function test_update_validation_wallet_and_category(): void
    {
        $rt = RecurringTransaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA Utama',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Validasi Update',
            'frequency' => 'monthly',
            'next_date' => '2026-10-01',
            'is_active' => true,
        ]);

        // Try updating to User B's wallet
        $this->actingAs($this->userA)->putJson("/api/recurring-transactions/{$rt->id}", [
            'wallet' => 'Mandiri B',
        ])->assertStatus(422);

        // Try updating to User B's category
        $this->actingAs($this->userA)->putJson("/api/recurring-transactions/{$rt->id}", [
            'category' => 'Bonus B',
        ])->assertStatus(422);
    }
}
