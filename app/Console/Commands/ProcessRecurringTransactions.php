<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionProcessor;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'recurring:process {--user= : Process only for a specific user ID}';

    protected $description = 'Process all due recurring transactions and generate real transactions.';

    public function handle(): int
    {
        $this->info('=== Recurring Transaction Processor ===');
        $this->info('Run at: ' . Carbon::now()->toDateTimeString());
        $this->newLine();

        $processor = new RecurringTransactionProcessor();

        $userId = $this->option('user');
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                $this->error("User dengan ID {$userId} tidak ditemukan.");
                return self::FAILURE;
            }
            $this->info("Processing untuk User: {$user->name} (ID: {$user->id})");
            $result = $processor->processForUser($user);
        } else {
            $this->info('Processing untuk SEMUA user...');
            $result = $processor->processAll();
        }

        $this->newLine();
        $this->info('--- Hasil ---');
        $this->line("Transaksi berhasil dibuat : {$result['processed']}");
        $this->line("Error                     : {$result['errors']}");

        if (!empty($result['details'])) {
            $this->newLine();
            $this->table(
                ['Recurring ID', 'Deskripsi', 'Tx Dibuat', 'Pesan'],
                collect($result['details'])->map(fn($d) => [
                    $d['recurring_id'],
                    $d['description'],
                    $d['count'],
                    $d['message'] ?: ($d['success'] ? 'OK' : 'GAGAL'),
                ])->toArray()
            );
        }

        $this->newLine();
        if ($result['errors'] > 0) {
            $this->warn('Selesai dengan beberapa error.');
            return self::FAILURE;
        }
        $this->info('Selesai. ✓');
        return self::SUCCESS;
    }
}
