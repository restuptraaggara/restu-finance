<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthEnhancementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. User can register a new account and is automatically logged in.
     */
    public function test_user_can_register_new_account(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@dev.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration_key' => 'RESTU-BETA-2026',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Registrasi berhasil. Selamat datang di Restu Finance!',
                     'data' => [
                         'user' => [
                             'name' => 'Budi Santoso',
                             'email' => 'budi@dev.local',
                         ],
                     ],
                 ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'email' => 'budi@dev.local',
            'is_admin' => false,
        ]);

        $user = User::where('email', 'budi@dev.local')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_admin);

        // Pastikan starter wallet & starter categories otomatis dibuat
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'name' => 'Dompet Utama',
        ]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Makanan',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Gaji',
            'type' => 'income',
        ]);
    }

    /**
     * 2. Registration validation checks: duplicate email, password confirmation mismatch, short password.
     */
    public function test_registration_validation_checks(): void
    {
        // Password confirmation mismatch
        $resMismatch = $this->postJson('/api/register', [
            'name' => 'Andi',
            'email' => 'andi@dev.local',
            'password' => 'password123',
            'password_confirmation' => 'berbeda123',
            'registration_key' => 'RESTU-BETA-2026',
        ]);
        $resMismatch->assertStatus(422)
                   ->assertJsonValidationErrors(['password']);

        // Password too short (< 8 chars)
        $resShort = $this->postJson('/api/register', [
            'name' => 'Andi',
            'email' => 'andi@dev.local',
            'password' => '12345',
            'password_confirmation' => '12345',
            'registration_key' => 'RESTU-BETA-2026',
        ]);
        $resShort->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);

        // Duplicate email
        User::create([
            'name' => 'User Existing',
            'email' => 'existing@dev.local',
            'password' => bcrypt('password123'),
        ]);

        $resDup = $this->postJson('/api/register', [
            'name' => 'Andi',
            'email' => 'existing@dev.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration_key' => 'RESTU-BETA-2026',
        ]);
        $resDup->assertStatus(422)
               ->assertJsonValidationErrors(['email']);
    }

    /**
     * 2b. Registration fails when registration_key is missing.
     */
    public function test_registration_fails_when_registration_key_is_missing(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Doni',
            'email' => 'doni@dev.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['registration_key']);
    }

    /**
     * 2c. Registration fails when registration_key is incorrect.
     */
    public function test_registration_fails_when_registration_key_is_incorrect(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Doni',
            'email' => 'doni@dev.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration_key' => 'WRONG-KEY-XYZ',
        ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Kode akses pendaftaran salah! Aplikasi saat ini masih dalam tahap uji coba tertutup.',
                     'errors' => [
                         'registration_key' => ['Kode akses pendaftaran salah! Aplikasi saat ini masih dalam tahap uji coba tertutup.'],
                     ],
                 ]);
    }

    /**
     * 3. User can change password with correct current_password.
     */
    public function test_user_can_change_password(): void
    {
        $user = User::create([
            'name' => 'Restu Test',
            'email' => 'restu_pwd@dev.local',
            'password' => Hash::make('old_password123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'old_password123',
            'password' => 'new_password456',
            'password_confirmation' => 'new_password456',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Kata sandi berhasil diperbarui.',
                 ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new_password456', $user->password));
    }

    /**
     * 4. Change password fails with wrong current_password.
     */
    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::create([
            'name' => 'Restu Test',
            'email' => 'restu_pwd2@dev.local',
            'password' => Hash::make('real_password123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'wrong_password',
            'password' => 'new_password456',
            'password_confirmation' => 'new_password456',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * 5. Change password requires different password from current.
     */
    public function test_change_password_requires_different_password(): void
    {
        $user = User::create([
            'name' => 'Restu Test',
            'email' => 'restu_pwd3@dev.local',
            'password' => Hash::make('same_password123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'same_password123',
            'password' => 'same_password123',
            'password_confirmation' => 'same_password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }
}
