<?php

namespace App\Console\Commands;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestOtpMail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mail:test-otp {email : Alamat email tujuan pengiriman OTP test}';

    /**
     * The console command description.
     */
    protected $description = 'Mengirim email test OTP ke alamat email yang ditentukan untuk memverifikasi konfigurasi SMTP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $this->info("========================================");
        $this->info("  Restu Finance - Test Kirim Email OTP  ");
        $this->info("========================================");
        $this->newLine();

        // Tampilkan konfigurasi SMTP aktif
        $this->info("Konfigurasi SMTP aktif:");
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['MAIL_MAILER', config('mail.default')],
                ['MAIL_HOST', config('mail.mailers.smtp.host')],
                ['MAIL_PORT', config('mail.mailers.smtp.port')],
                ['MAIL_SCHEME', config('mail.mailers.smtp.scheme') ?: '(null)'],
                ['MAIL_USERNAME', config('mail.mailers.smtp.username') ?: '(null)'],
                ['MAIL_PASSWORD', config('mail.mailers.smtp.password') ? '********' : '(null)'],
                ['MAIL_FROM_ADDRESS', config('mail.from.address')],
                ['MAIL_FROM_NAME', config('mail.from.name')],
            ]
        );
        $this->newLine();

        // Cari user di database, atau buat dummy untuk testing
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->warn("User dengan email '{$email}' tidak ditemukan di database.");
            $this->info("Menggunakan data dummy untuk testing...");
            $user = new User([
                'name' => 'Test User',
                'email' => $email,
            ]);
        } else {
            $this->info("User ditemukan: {$user->name} ({$user->email})");
        }

        // Generate kode OTP test
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $this->info("Kode OTP yang akan dikirim: {$otp}");
        $this->newLine();

        $this->info("Mengirim email ke: {$email}...");
        $this->newLine();

        try {
            $startTime = microtime(true);
            Mail::to($email)->send(new PasswordResetOtpMail($user, $otp));
            $duration = round((microtime(true) - $startTime) * 1000);

            $this->newLine();
            $this->info("✅  EMAIL BERHASIL DIKIRIM!");
            $this->info("    Tujuan      : {$email}");
            $this->info("    Kode OTP    : {$otp}");
            $this->info("    Durasi kirim: {$duration} ms");
            $this->newLine();
            $this->info("Silakan cek kotak masuk (Inbox) atau folder Spam di {$email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("❌  GAGAL MENGIRIM EMAIL!");
            $this->newLine();
            $this->error("Error Class  : " . get_class($e));
            $this->error("Error Message: " . $e->getMessage());
            $this->newLine();
            $this->error("Stack Trace:");
            $this->line($e->getTraceAsString());
            $this->newLine();
            $this->warn("Kemungkinan penyebab:");
            $this->warn("  1. MAIL_PASSWORD di .env belum diisi dengan Gmail App Password yang valid.");
            $this->warn("  2. 2-Step Verification belum diaktifkan di Google Account.");
            $this->warn("  3. Port 587 (TLS) diblokir oleh firewall/antivirus.");
            $this->warn("  4. Sertifikat SSL tidak valid (sudah ditangani oleh stream config).");
            $this->newLine();
            $this->info("Cara membuat Gmail App Password:");
            $this->info("  1. Buka https://myaccount.google.com/security");
            $this->info("  2. Aktifkan '2-Step Verification' jika belum aktif.");
            $this->info("  3. Cari 'App passwords' > buat password baru untuk 'Mail'.");
            $this->info("  4. Salin 16-karakter App Password ke .env MAIL_PASSWORD.");

            return self::FAILURE;
        }
    }
}
