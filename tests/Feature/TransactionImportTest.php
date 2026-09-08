<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Restu Tester',
            'email' => 'tester@dev.local',
            'password' => Hash::make('password123'),
            'theme' => 'dark',
            'currency' => 'IDR',
            'language' => 'id',
        ]);
    }

    /**
     * Test 1: User can import transactions from a valid CSV file.
     */
    public function test_user_can_import_transactions_from_valid_csv(): void
    {
        $csvContent = implode("\n", [
            'No,Tanggal,Tipe,Kategori,Dompet,Deskripsi,Nominal,Metode,Catatan',
            '1,2026-09-01,Income,Gaji,BCA Utama,Gaji Pokok Bulan September,8500000,Transfer,Gaji bulanan',
            '2,2026-09-02,Expense,Makanan,Dompet Tunai,Makan Siang Resto,45000,Tunai,Makan bareng rekan kerja',
            '3,2026-09-03,Expense,Transportasi,BCA Utama,Bensin Motor,30000,E-Wallet,Isi pertalite',
        ]);

        $file = UploadedFile::fake()->createWithContent('transaksi.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'imported_count' => 3,
                ],
            ]);

        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Gaji Pokok Bulan September',
            'amount' => 8500000,
            'type' => 'income',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Makan Siang Resto',
            'amount' => 45000,
            'type' => 'expense',
        ]);
    }

    /**
     * Test 2: Auto-creates missing wallets and categories for the authenticated user.
     */
    public function test_import_auto_creates_missing_wallet_and_category(): void
    {
        $csvContent = implode("\n", [
            'Tanggal,Tipe,Kategori,Dompet,Deskripsi,Nominal,Metode,Catatan',
            '2026-09-05,Expense,Langganan SaaS,Kartu Kredit Jenius,Langganan Hosting Cloud,150000,Kartu Kredit,Biaya server',
        ]);

        $file = UploadedFile::fake()->createWithContent('import_custom.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'imported_count' => 1,
                ],
            ]);

        // Verifikasi wallet baru otomatis dibuat
        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
            'name' => 'Kartu Kredit Jenius',
        ]);

        // Verifikasi kategori baru otomatis dibuat
        $this->assertDatabaseHas('categories', [
            'user_id' => $this->user->id,
            'name' => 'Langganan SaaS',
            'type' => 'expense',
        ]);
    }

    /**
     * Test 3: Rejects missing file or file without proper header columns.
     */
    public function test_import_rejects_missing_file_or_invalid_header(): void
    {
        // 1. Missing file
        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/import', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);

        // 2. CSV without amount column
        $invalidCsv = "Tanggal,Deskripsi,Catatan\n2026-09-01,Test,Catatan saja";
        $file = UploadedFile::fake()->createWithContent('invalid.csv', $invalidCsv);

        $responseInvalid = $this->actingAs($this->user)
            ->postJson('/api/transactions/import', [
                'file' => $file,
            ]);

        $responseInvalid->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test 4: Ensures imported transactions strictly belong to the authenticated user.
     */
    public function test_import_enforces_user_isolation(): void
    {
        $otherUser = User::create([
            'name' => 'Other Person',
            'email' => 'other@dev.local',
            'password' => Hash::make('secret123'),
        ]);

        $csvContent = implode("\n", [
            'Date,Type,Category,Wallet,Description,Amount,Method,Note',
            '2026-09-06,Expense,Belanja,Dompet Tunai,Beli Buku,120000,Cash,Buku Laravel',
        ]);

        $file = UploadedFile::fake()->createWithContent('user_tx.csv', $csvContent);

        $this->actingAs($this->user)
            ->postJson('/api/transactions/import', ['file' => $file])
            ->assertStatus(200);

        // Transaction harus milik user aktif, bukan otherUser
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Beli Buku',
        ]);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $otherUser->id,
            'description' => 'Beli Buku',
        ]);
    }
}