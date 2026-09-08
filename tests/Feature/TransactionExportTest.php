<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::create([
            'name' => 'Restu User A',
            'email' => 'usera@dev.local',
            'password' => bcrypt('password123'),
        ]);

        $this->userB = User::create([
            'name' => 'User B',
            'email' => 'userb@dev.local',
            'password' => bcrypt('password123'),
        ]);

        // Transaksi User A
        Transaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA',
            'category' => 'Gaji',
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Gaji Bulanan',
            'date' => '2026-09-01',
        ]);

        Transaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'BCA',
            'category' => 'Makanan',
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Nasi Padang "Spesial"',
            'date' => '2026-09-02',
            'note' => 'Enak sekali, mantap',
        ]);

        // Transaksi User B
        Transaction::create([
            'user_id' => $this->userB->id,
            'wallet' => 'Mandiri',
            'category' => 'Bonus',
            'type' => 'income',
            'amount' => 10000000,
            'description' => 'Bonus Rahasia B',
            'date' => '2026-09-01',
        ]);
    }

    public function test_unauthenticated_export_is_rejected(): void
    {
        $response = $this->getJson('/api/transactions/export');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_export_csv_with_utf8_bom(): void
    {
        $response = $this->actingAs($this->userA)->get('/api/transactions/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();

        // Cek UTF-8 BOM di awal output
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Cek header CSV standar v1.5.0
        $this->assertStringContainsString('No,Tanggal,Tipe,Kategori,Dompet,Deskripsi,Nominal,Metode,Catatan', $content);

        // Cek data User A ada
        $this->assertStringContainsString('Gaji Bulanan', $content);
        $this->assertStringContainsString('Income', $content);
        $this->assertStringContainsString('Nasi Padang ""Spesial""', $content); // Escaped RFC 4180

        // Cek data User B TIDAK bocor ke User A
        $this->assertStringNotContainsString('Bonus Rahasia B', $content);
    }

    public function test_export_csv_respects_type_filter(): void
    {
        $response = $this->actingAs($this->userA)->get('/api/transactions/export?type=expense');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Nasi Padang', $content);
        $this->assertStringNotContainsString('Gaji Bulanan', $content);
    }

    public function test_export_csv_respects_wallet_filter(): void
    {
        // Tambah dompet lain untuk User A
        Transaction::create([
            'user_id' => $this->userA->id,
            'wallet' => 'GoPay',
            'category' => 'Transport',
            'type' => 'expense',
            'amount' => 20000,
            'description' => 'Ojek Online',
            'date' => '2026-09-03',
        ]);

        $response = $this->actingAs($this->userA)->get('/api/transactions/export?wallet=GoPay');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Ojek Online', $content);
        $this->assertStringNotContainsString('Gaji Bulanan', $content);
    }
}
