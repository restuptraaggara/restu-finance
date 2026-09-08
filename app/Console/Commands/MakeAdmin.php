<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:make-admin {email : Email pengguna yang akan dijadikan admin} {--revoke : Cabut status administrator}';

    /**
     * The console command description.
     */
    protected $description = 'Menetapkan atau mencabut hak akses administrator untuk pengguna.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Pengguna dengan email '{$email}' tidak ditemukan.");
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->update(['is_admin' => false]);
            $this->info("Hak akses administrator untuk {$user->name} ({$user->email}) berhasil dicabut.");
            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);
        $this->info("Pengguna {$user->name} ({$user->email}) sekarang telah menjadi Administrator.");
        return self::SUCCESS;
    }
}

