<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
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
                'password' => bcrypt('password123'),
                'theme' => 'dark',
                'currency' => 'IDR',
                'language' => 'id',
            ]
        );
    }

    /**
     * 1. User dapat mengambil profile sendiri.
     */
    public function test_user_can_get_own_profile(): void
    {
        $response = $this->getJson('/api/profile', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Profil pengguna berhasil dimuat.',
                     'data' => [
                         'id' => $this->user->id,
                         'name' => 'Restu Putra Anggara',
                         'email' => 'restu@dev.local',
                         'theme' => 'dark',
                         'currency' => 'IDR',
                         'language' => 'id',
                     ],
                 ]);
    }

    /**
     * 2. User dapat mengubah nama.
     */
    public function test_user_can_update_name(): void
    {
        $payload = [
            'name' => 'Restu Putra Anggara, S.Kom',
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Profil pengguna berhasil diperbarui.',
                     'data' => [
                         'id' => $this->user->id,
                         'name' => 'Restu Putra Anggara, S.Kom',
                     ],
                 ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Restu Putra Anggara, S.Kom',
        ]);
    }

    /**
     * 3. User dapat mengubah preference yang didukung (theme, currency, language).
     */
    public function test_user_can_update_preferences(): void
    {
        $payload = [
            'theme' => 'light',
            'currency' => 'USD',
            'language' => 'en',
        ];

        $response = $this->patchJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'theme' => 'light',
                         'currency' => 'USD',
                         'language' => 'en',
                     ],
                 ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'theme' => 'light',
            'currency' => 'USD',
            'language' => 'en',
        ]);
    }

    /**
     * 4. User tidak dapat mengubah user lain (ownership terisolasi per auth header / session).
     */
    public function test_user_updates_only_own_profile(): void
    {
        $otherUser = User::create([
            'name' => 'Original Other User',
            'email' => 'other_' . uniqid() . '@dev.local',
            'password' => bcrypt('secret'),
            'theme' => 'dark',
        ]);

        // User A mengupdate profile
        $this->putJson('/api/profile', ['name' => 'New User A Name'], ['X-User-Id' => $this->user->id]);

        // Pastikan User B tidak berubah
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'Original Other User',
        ]);
    }

    /**
     * 5. Validation name (tidak boleh kosong atau whitespace saja).
     */
    public function test_name_validation(): void
    {
        $payload = [
            'name' => '   ',
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => ['name'],
                 ]);
    }

    /**
     * 6. Validation email (format email).
     */
    public function test_email_format_validation(): void
    {
        $payload = [
            'email' => 'bukan-email-valid',
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => ['email'],
                 ]);
    }

    /**
     * 7. Email tidak boleh duplicate dengan user lain.
     */
    public function test_email_cannot_duplicate_other_user(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'existing_other@dev.local',
            'password' => bcrypt('password'),
        ]);

        $payload = [
            'email' => 'existing_other@dev.local',
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => ['email'],
                 ]);
    }

    /**
     * 8. User tetap dapat mempertahankan email miliknya sendiri saat update.
     */
    public function test_user_can_keep_own_email(): void
    {
        $payload = [
            'name' => 'Restu Putra',
            'email' => 'restu@dev.local', // Email sendiri
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'email' => 'restu@dev.local',
                     ],
                 ]);
    }

    /**
     * 9. Theme hanya menerima nilai valid ('dark', 'light', 'system').
     */
    public function test_theme_validation(): void
    {
        $payload = [
            'theme' => 'neon-cyberpunk',
        ];

        $response = $this->putJson('/api/profile', $payload, ['X-User-Id' => $this->user->id]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => ['theme'],
                 ]);
    }

    /**
     * 10. Field sensitif (password & remember_token) tidak bocor dalam response API.
     */
    public function test_sensitive_fields_not_exposed_in_response(): void
    {
        $response = $this->getJson('/api/profile', ['X-User-Id' => $this->user->id]);

        $response->assertStatus(200)
                 ->assertJsonMissingPath('data.password')
                 ->assertJsonMissingPath('data.remember_token');
    }
}
