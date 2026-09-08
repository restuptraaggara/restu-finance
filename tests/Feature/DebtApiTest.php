<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'email' => 'restu@dev.local',
            'name' => 'Restu Putra Anggara',
            'password' => bcrypt('password'),
        ]);

        $this->otherUser = User::create([
            'email' => 'other@dev.local',
            'name' => 'Other User',
            'password' => bcrypt('password'),
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'name' => 'Dompet Utama',
            'opening_balance' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_user_can_list_debts_and_see_summary(): void
    {
        Debt::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'type' => 'debt',
            'person_name' => 'Budi Santoso',
            'amount' => 500000,
            'paid_amount' => 100000,
            'status' => 'partial',
            'due_date' => '2026-12-31',
        ]);

        Debt::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'type' => 'credit',
            'person_name' => 'Siti Rahma',
            'amount' => 200000,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'due_date' => '2026-11-30',
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/debts');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total' => 2,
                'summary' => [
                    'total_debt_remaining' => 400000,
                    'total_credit_remaining' => 200000,
                    'total_debt_paid' => 100000,
                    'total_credit_paid' => 0,
                ],
            ]);
    }

    public function test_user_can_filter_debts_by_type_and_status(): void
    {
        Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Budi',
            'amount' => 100000,
            'status' => 'unpaid',
        ]);

        Debt::create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'person_name' => 'Siti',
            'amount' => 200000,
            'status' => 'paid',
            'paid_amount' => 200000,
        ]);

        // Filter debt
        $resDebt = $this->actingAs($this->user)->getJson('/api/debts?type=debt');
        $resDebt->assertStatus(200)->assertJson(['total' => 1]);
        $this->assertEquals('Budi', $resDebt->json('data.0.person_name'));

        // Filter credit
        $resCredit = $this->actingAs($this->user)->getJson('/api/debts?type=credit');
        $resCredit->assertStatus(200)->assertJson(['total' => 1]);
        $this->assertEquals('Siti', $resCredit->json('data.0.person_name'));

        // Filter status paid
        $resPaid = $this->actingAs($this->user)->getJson('/api/debts?status=paid');
        $resPaid->assertStatus(200)->assertJson(['total' => 1]);
        $this->assertEquals('Siti', $resPaid->json('data.0.person_name'));
    }

    public function test_user_can_create_debt(): void
    {
        $payload = [
            'type' => 'debt',
            'person_name' => 'Pak Haji',
            'amount' => 1500000,
            'wallet_id' => $this->wallet->id,
            'due_date' => '2026-10-15',
            'notes' => 'Pinjaman modal usaha kecil',
        ];

        $res = $this->actingAs($this->user)->postJson('/api/debts', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'person_name' => 'Pak Haji',
                    'type' => 'debt',
                    'amount' => 1500000,
                    'paid_amount' => 0,
                    'status' => 'unpaid',
                    'remaining_amount' => 1500000,
                ],
            ]);

        $this->assertDatabaseHas('debts', [
            'user_id' => $this->user->id,
            'person_name' => 'Pak Haji',
            'amount' => 1500000,
        ]);
    }

    public function test_validation_errors_when_creating_debt(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/debts', [
            'type' => 'invalid_type',
            'amount' => -100,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'person_name', 'amount']);
    }

    public function test_user_can_view_single_debt_details(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Ahmad',
            'amount' => 500000,
            'status' => 'unpaid',
        ]);

        $res = $this->actingAs($this->user)->getJson("/api/debts/{$debt->id}");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $debt->id,
                    'person_name' => 'Ahmad',
                ],
            ]);
    }

    public function test_user_can_update_debt(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Ahmad',
            'amount' => 500000,
            'status' => 'unpaid',
        ]);

        $res = $this->actingAs($this->user)->putJson("/api/debts/{$debt->id}", [
            'person_name' => 'Ahmad Fauzi',
            'amount' => 600000,
            'due_date' => '2026-11-20',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'person_name' => 'Ahmad Fauzi',
                    'amount' => 600000,
                ],
            ]);

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'person_name' => 'Ahmad Fauzi',
            'amount' => 600000,
        ]);
    }

    public function test_user_can_delete_debt(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Ahmad',
            'amount' => 500000,
            'status' => 'unpaid',
        ]);

        $res = $this->actingAs($this->user)->deleteJson("/api/debts/{$debt->id}");

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
    }

    public function test_user_can_pay_debt_partial_and_full(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Ahmad',
            'amount' => 500000,
            'status' => 'unpaid',
        ]);

        // Cicilan pertama: 200.000 (parsial)
        $res1 = $this->actingAs($this->user)->postJson("/api/debts/{$debt->id}/pay", [
            'amount' => 200000,
            'wallet_id' => $this->wallet->id,
            'payment_date' => '2026-09-08',
            'notes' => 'Cicilan 1',
        ]);

        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'debt' => [
                        'paid_amount' => 200000,
                        'remaining_amount' => 300000,
                        'status' => 'partial',
                    ],
                ],
            ]);

        // Cicilan kedua: 300.000 (lunas)
        $res2 = $this->actingAs($this->user)->postJson("/api/debts/{$debt->id}/pay", [
            'amount' => 300000,
            'wallet_id' => $this->wallet->id,
            'payment_date' => '2026-09-08',
            'notes' => 'Pelunasan',
        ]);

        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'debt' => [
                        'paid_amount' => 500000,
                        'remaining_amount' => 0,
                        'status' => 'paid',
                    ],
                ],
            ]);
    }

    public function test_cannot_pay_more_than_remaining_debt_amount(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Ahmad',
            'amount' => 500000,
            'paid_amount' => 400000,
            'status' => 'partial',
        ]);

        $res = $this->actingAs($this->user)->postJson("/api/debts/{$debt->id}/pay", [
            'amount' => 200000, // Sisa hanya 100.000
        ]);

        $res->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_paying_debt_creates_expense_transaction_and_updates_balance(): void
    {
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'debt',
            'person_name' => 'Pak RT',
            'amount' => 400000,
            'status' => 'unpaid',
        ]);

        $this->actingAs($this->user)->postJson("/api/debts/{$debt->id}/pay", [
            'amount' => 400000,
            'wallet_id' => $this->wallet->id,
            'payment_date' => '2026-09-08',
            'notes' => 'Bayar iuran pinjaman',
        ]);

        $balanceInfo = $this->wallet->getBalanceDetails($this->user);
        // Saldo awal 1.000.000 - 400.000 = 600.000
        $this->assertEquals(600000, $balanceInfo['current_balance']);
        $this->assertEquals(400000, $balanceInfo['total_expense']);
    }

    public function test_receiving_credit_payment_creates_income_transaction_and_updates_balance(): void
    {
        $credit = Debt::create([
            'user_id' => $this->user->id,
            'type' => 'credit',
            'person_name' => 'Dedi',
            'amount' => 750000,
            'status' => 'unpaid',
        ]);

        $this->actingAs($this->user)->postJson("/api/debts/{$credit->id}/pay", [
            'amount' => 750000,
            'wallet_id' => $this->wallet->id,
            'payment_date' => '2026-09-08',
            'notes' => 'Dedi melunasi utangnya',
        ]);

        $balanceInfo = $this->wallet->getBalanceDetails($this->user);
        // Saldo awal 1.000.000 + 750.000 = 1.750.000
        $this->assertEquals(1750000, $balanceInfo['current_balance']);
        $this->assertEquals(750000, $balanceInfo['total_income']);
    }

    public function test_user_cannot_access_or_modify_other_user_debt(): void
    {
        $otherDebt = Debt::create([
            'user_id' => $this->otherUser->id,
            'type' => 'debt',
            'person_name' => 'Stranger',
            'amount' => 999999,
            'status' => 'unpaid',
        ]);

        // Show 404
        $this->actingAs($this->user)->getJson("/api/debts/{$otherDebt->id}")->assertStatus(404);

        // Update 404
        $this->actingAs($this->user)->putJson("/api/debts/{$otherDebt->id}", ['person_name' => 'Hacked'])->assertStatus(404);

        // Pay 404
        $this->actingAs($this->user)->postJson("/api/debts/{$otherDebt->id}/pay", ['amount' => 10000])->assertStatus(404);

        // Delete 404
        $this->actingAs($this->user)->deleteJson("/api/debts/{$otherDebt->id}")->assertStatus(404);
    }
}
