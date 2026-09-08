<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetOtpMail extends Mailable
{

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $otp
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Reset Kata Sandi - Restu Finance',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; background: #0d1417; color: #edf5ee; border-radius: 14px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #d7f56f; margin: 0; font-size: 24px; letter-spacing: 0.5px;'>Restu Finance</h2>
                        <p style='color: #8fa2a4; font-size: 13px; margin-top: 4px;'>Pengatur Keuangan Modern & Terencana</p>
                    </div>
                    <div style='background: #172126; border: 1px solid #304149; border-radius: 10px; padding: 20px;'>
                        <p style='margin-top: 0; font-size: 15px;'>Halo <strong>" . htmlspecialchars($this->user->name) . "</strong>,</p>
                        <p style='color: #edf5ee; line-height: 1.5; font-size: 14px;'>
                            Kami menerima permintaan untuk mengatur ulang kata sandi akun Restu Finance Anda.
                            Gunakan 6-digit kode OTP berikut untuk melanjutkan proses verifikasi:
                        </p>
                        <div style='text-align: center; margin: 24px 0;'>
                            <div style='display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #d7f56f; background: #0d1417; padding: 14px 28px; border-radius: 10px; border: 2px dashed #d7f56f; font-family: monospace;'>{$this->otp}</div>
                        </div>
                        <p style='font-size: 13px; color: #ffad70; margin-bottom: 0;'>
                            Kode ini berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapapun demi keamanan akun Anda.
                        </p>
                    </div>
                    <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #8fa2a4;'>
                        Jika Anda tidak merasa meminta reset kata sandi, abaikan email ini. Akun Anda tetap aman.
                    </div>
                </div>
            ",
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
