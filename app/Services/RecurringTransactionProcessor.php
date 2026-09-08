<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTransactionProcessor
{
    /**
     * Proses semua recurring transaction aktif yang sudah jatuh tempo untuk semua user.
     * Digunakan oleh artisan command.
     */
    public function processAll(): array
    {
        $summary = ['processed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $recurrings = RecurringTransaction::where('is_active', true)
            ->whereDate('next_date', '<=', Carbon::today())
            ->get();

        foreach ($recurrings as $recurring) {
            $result = $this->processSingleRecurring($recurring);
            if ($result['success']) {
                $summary['processed'] += $result['count'];
            } else {
                $summary['errors']++;
            }
            $summary['details'][] = $result;
        }

        return $summary;
    }

    /**
     * Proses semua recurring transaction milik satu user.
     * Digunakan oleh API endpoint POST /api/recurring-transactions/process.
     */
    public function processForUser(User $user): array
    {
        $summary = ['processed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $recurrings = $user->recurringTransactions()
            ->where('is_active', true)
            ->whereDate('next_date', '<=', Carbon::today())
            ->get();

        foreach ($recurrings as $recurring) {
            $result = $this->processSingleRecurring($recurring);
            if ($result['success']) {
                $summary['processed'] += $result['count'];
            } else {
                $summary['errors']++;
            }
            $summary['details'][] = $result;
        }

        return $summary;
    }

    /**
     * Proses satu recurring transaction, mungkin menghasilkan beberapa transaksi
     * jika terjadi beberapa missed runs.
     *
     * Duplicate protection: cek apakah transaksi dengan kombinasi
     * (user_id, wallet, category, type, amount, date) sudah ada di hari yang sama.
     */
    public function processSingleRecurring(RecurringTransaction $recurring): array
    {
        $result = [
            'recurring_id' => $recurring->id,
            'description' => $recurring->description,
            'success' => true,
            'count' => 0,
            'transactions_created' => [],
            'message' => '',
        ];

        try {
            DB::transaction(function () use ($recurring, &$result) {
                $today = Carbon::today();
                $nextDate = Carbon::parse($recurring->next_date);

                // Proses semua run yang sudah jatuh tempo (multiple missed runs)
                while ($nextDate->lte($today)) {
                    // Cek end_date — jika sudah lewat, hentikan dan nonaktifkan
                    if ($recurring->end_date && $nextDate->gt(Carbon::parse($recurring->end_date))) {
                        $recurring->is_active = false;
                        $recurring->save();
                        $result['message'] = 'Nonaktif karena sudah melewati end_date.';
                        break;
                    }

                    $transactionDate = $nextDate->toDateString();

                    // DUPLICATE PROTECTION — Cek berdasarkan recurring_id + tanggal yang sama
                    $alreadyExists = Transaction::where('user_id', $recurring->user_id)
                        ->where('wallet', $recurring->wallet)
                        ->where('category', $recurring->category)
                        ->where('type', $recurring->type)
                        ->where('amount', $recurring->amount)
                        ->whereDate('date', $transactionDate)
                        ->where('description', 'LIKE', '%[RT#' . $recurring->id . ']%')
                        ->exists();

                    if (!$alreadyExists) {
                        $tx = Transaction::create([
                            'user_id' => $recurring->user_id,
                            'wallet' => $recurring->wallet,
                            'category' => $recurring->category,
                            'type' => $recurring->type,
                            'amount' => $recurring->amount,
                            'description' => trim($recurring->description . ' [RT#' . $recurring->id . ']'),
                            'date' => $transactionDate,
                        ]);
                        $result['count']++;
                        $result['transactions_created'][] = $tx->id;
                    }

                    // Hitung next_date berikutnya
                    $nextDate = $recurring->calculateNextDate($nextDate);
                }

                // Update next_date ke jadwal berikutnya (yang belum jatuh tempo)
                $recurring->next_date = $nextDate->toDateString();

                // Jika next_date sudah melewati end_date, nonaktifkan
                if ($recurring->end_date && $nextDate->gt(Carbon::parse($recurring->end_date))) {
                    $recurring->is_active = false;
                    $result['message'] = ($result['message'] ?: '') . ' Nonaktif karena next_date melewati end_date.';
                }

                $recurring->save();
            });
        } catch (\Throwable $e) {
            $result['success'] = false;
            $result['message'] = 'Error: ' . $e->getMessage();
        }

        return $result;
    }
}
