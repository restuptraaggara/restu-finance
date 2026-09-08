<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Restu Developer',
            'email' => 'restu_otp@dev.local',
            'password' => Hash::make('old_password123'),
            'theme' => 'dark',
            'currency' => 'IDR',
            'language' => 'id',
        ]);
    }

    /**
     * 1. Login fails with unregistered email (HTTP 401 & specific message).
     */
    public function test_login_fails_with_unregistered_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'unknown_user@dev.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Email belum terdaftar. Silakan buat akun baru terlebih dahulu.',
                 ]);
    }

    /**
     * 2. Login fails with wrong password (HTTP 401 & specific message).
     */
    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'restu_otp@dev.local',
            'password' => 'wrong_password123',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Kata sandi salah. Silakan periksa kembali kata sandi Anda.',
                 ]);
    }

    /**
     * 3. Forgot password fails if email is not registered (HTTP 404).
     */
    public function test_forgot_password_fails_if_email_not_registered(): void
    {
        $response = $this->postJson('/api/password/forgot', [
            'email' => 'not_found@dev.local',
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Email belum terdaftar. Silakan buat akun baru terlebih dahulu.',
                 ]);
    }

    /**
     * 4. Forgot password generates 6-digit OTP and sends email via Mail facade.
     */
    public function test_forgot_password_sends_otp_email_successfully(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'restu_otp@dev.local',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Kode OTP berhasil dikirim ke email Anda. Silakan cek kotak masuk atau spam.',
                     'data' => [
                         'email' => 'restu_otp@dev.local',
                         'expires_in_minutes' => 10,
                     ],
                 ]);

        $otpRecord = PasswordResetOtp::where('email', 'restu_otp@dev.local')->first();
        $this->assertNotNull($otpRecord);
        $this->assertEquals(6, strlen($otpRecord->otp));
        $this->assertTrue($otpRecord->expires_at->isFuture());

        Mail::assertSent(PasswordResetOtpMail::class, function ($mail) {
            return $mail->hasTo('restu_otp@dev.local') && strlen($mail->otp) === 6;
        });
    }

    /**
     * 5. Reset password fails if OTP is invalid or expired.
     */
    public function test_reset_password_fails_with_invalid_or_expired_otp(): void
    {
        PasswordResetOtp::create([
            'email' => 'restu_otp@dev.local',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Wrong OTP
        $resWrong = $this->postJson('/api/password/reset-otp', [
            'email' => 'restu_otp@dev.local',
            'otp' => '999999',
            'password' => 'new_secret123',
            'password_confirmation' => 'new_secret123',
        ]);
        $resWrong->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Kode OTP salah. Periksa kembali 6 digit kode yang diterima.',
                 ]);

        // Expired OTP (> 10 minutes)
        PasswordResetOtp::where('email', 'restu_otp@dev.local')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $resExpired = $this->postJson('/api/password/reset-otp', [
            'email' => 'restu_otp@dev.local',
            'otp' => '123456',
            'password' => 'new_secret123',
            'password_confirmation' => 'new_secret123',
        ]);
        $resExpired->assertStatus(422)
                   ->assertJson([
                       'success' => false,
                       'message' => 'Kode OTP telah kedaluwarsa (> 10 menit). Silakan minta kode OTP baru.',
                   ]);
    }

    /**
     * 6. Reset password succeeds with valid OTP, updates password, and deletes OTP.
     */
    public function test_reset_password_succeeds_with_valid_otp(): void
    {
        PasswordResetOtp::create([
            'email' => 'restu_otp@dev.local',
            'otp' => '654321',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/password/reset-otp', [
            'email' => 'restu_otp@dev.local',
            'otp' => '654321',
            'password' => 'brand_new_pass123',
            'password_confirmation' => 'brand_new_pass123',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Kata sandi berhasil diperbarui. Silakan login dengan kata sandi baru Anda.',
                 ]);

        // Pastikan OTP dihapus setelah digunakan
        $this->assertDatabaseMissing('password_reset_otps', [
            'email' => 'restu_otp@dev.local',
        ]);

        // Pastikan user sekarang bisa login dengan password baru
        $this->user->refresh();
        $this->assertTrue(Hash::check('brand_new_pass123', $this->user->password));

        $loginRes = $this->postJson('/api/login', [
            'email' => 'restu_otp@dev.local',
            'password' => 'brand_new_pass123',
        ]);
        $loginRes->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /**
     * 7. Logout returns csrf_token in JSON response.
     */
    public function test_logout_returns_fresh_csrf_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logout berhasil.',
                 ])
                 ->assertJsonStructure(['csrf_token']);
    }
}

