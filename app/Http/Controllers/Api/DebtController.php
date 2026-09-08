<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayDebtRequest;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DebtController extends Controller
{
    protected function getAuthUser(Request $request): User
    {
        $user = $request->user() ?: Auth::user();

        if (!$user && app()->environment('testing')) {
            $userId = $request->header('X-User-Id');
            if ($userId) {
                $user = User::find($userId);
            }
        }

        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
                'data' => null,
            ], 401));
        }

        return $user;
    }

    /**
     * GET /api/debts
     * Daftar seluruh utang dan piutang user aktif beserta ringkasan.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $query = $user->debts()->with(['payments', 'wallet']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $debts = $query->orderBy('due_date', 'asc')->orderBy('id', 'desc')->get();

        // Kalkulasi ringkasan statistik
        $allUserDebts = $user->debts()->get();
        $totalDebtRemaining = (float) $allUserDebts->where('type', 'debt')->where('status', '!=', 'paid')->sum(fn ($d) => $d->remaining_amount);
        $totalCreditRemaining = (float) $allUserDebts->where('type', 'credit')->where('status', '!=', 'paid')->sum(fn ($d) => $d->remaining_amount);
        $totalDebtPaid = (float) $allUserDebts->where('type', 'debt')->sum('paid_amount');
        $totalCreditPaid = (float) $allUserDebts->where('type', 'credit')->sum('paid_amount');
        $overdueCount = $allUserDebts->filter(fn ($d) => $d->is_overdue)->count();

        return response()->json([
            'success' => true,
            'message' => 'Daftar utang dan piutang berhasil dimuat.',
            'total' => $debts->count(),
            'summary' => [
                'total_debt_remaining' => $totalDebtRemaining,
                'total_credit_remaining' => $totalCreditRemaining,
                'total_debt_paid' => $totalDebtPaid,
                'total_credit_paid' => $totalCreditPaid,
                'overdue_count' => $overdueCount,
            ],
            'data' => $debts,
        ], 200);
    }

    /**
     * POST /api/debts
     * Mencatat utang / piutang baru.
     */
    public function store(StoreDebtRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $debt = $user->debts()->create([
            'wallet_id' => $request->wallet_id,
            'type' => $request->type,
            'person_name' => trim($request->person_name),
            'amount' => (float) $request->amount,
            'paid_amount' => 0,
            'due_date' => $request->due_date,
            'status' => 'unpaid',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => $debt->type === 'debt' ? 'Catatan utang berhasil ditambahkan.' : 'Catatan piutang berhasil ditambahkan.',
            'data' => $debt->load(['payments', 'wallet']),
        ], 201);
    }

    /**
     * GET /api/debts/{id}
     * Menampilkan detail utang/piutang beserta riwayat cicilan.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $debt = $user->debts()->with(['payments.wallet', 'wallet'])->find($id);

        if (!$debt) {
            return response()->json([
                'success' => false,
                'message' => 'Catatan utang/piutang tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail utang/piutang berhasil dimuat.',
            'data' => $debt,
        ], 200);
    }

    /**
     * PUT/PATCH /api/debts/{id}
     * Mengubah rincian utang/piutang.
     */
    public function update(UpdateDebtRequest $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $debt = $user->debts()->find($id);

        if (!$debt) {
            return response()->json([
                'success' => false,
                'message' => 'Catatan utang/piutang tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        if ($request->has('person_name')) {
            $debt->person_name = trim($request->person_name);
        }
        if ($request->has('type')) {
            $debt->type = $request->type;
        }
        if ($request->has('wallet_id')) {
            $debt->wallet_id = $request->wallet_id;
        }
        if ($request->has('due_date')) {
            $debt->due_date = $request->due_date;
        }
        if ($request->has('notes')) {
            $debt->notes = $request->notes;
        }
        if ($request->has('amount')) {
            $debt->amount = (float) $request->amount;
            // Evaluasi ulang status berdasarkan amount baru
            if ($debt->paid_amount >= $debt->amount) {
                $debt->status = 'paid';
            } elseif ($debt->paid_amount > 0) {
                $debt->status = 'partial';
            } else {
                $debt->status = 'unpaid';
            }
        }

        $debt->save();

        return response()->json([
            'success' => true,
            'message' => 'Catatan utang/piutang berhasil diperbarui.',
            'data' => $debt->load(['payments', 'wallet']),
        ], 200);
    }

    /**
     * DELETE /api/debts/{id}
     * Menghapus utang/piutang.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $debt = $user->debts()->find($id);

        if (!$debt) {
            return response()->json([
                'success' => false,
                'message' => 'Catatan utang/piutang tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $debt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan utang/piutang berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    /**
     * POST /api/debts/{id}/pay
     * Mencatat cicilan atau pelunasan utang/piutang serta sinkronisasi saldo wallet.
     */
    public function pay(PayDebtRequest $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $debt = $user->debts()->find($id);

        if (!$debt) {
            return response()->json([
                'success' => false,
                'message' => 'Catatan utang/piutang tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        if ($debt->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Catatan utang/piutang ini sudah lunas.',
                'data' => null,
            ], 422);
        }

        $payAmount = (float) $request->amount;
        $remaining = $debt->remaining_amount;

        if ($payAmount > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Nominal pembayaran melebihi sisa tagihan. Maksimal pembayaran adalah Rp " . number_format($remaining, 0, ',', '.'),
                'data' => null,
            ], 422);
        }

        // Jalankan transaksi database untuk konsistensi data
        return DB::transaction(function () use ($user, $debt, $request, $payAmount) {
            $paymentDate = $request->payment_date ?: now()->toDateString();

            // 1. Simpan cicilan di debt_payments
            $payment = DebtPayment::create([
                'debt_id' => $debt->id,
                'user_id' => $user->id,
                'wallet_id' => $request->wallet_id,
                'amount' => $payAmount,
                'payment_date' => $paymentDate,
                'notes' => $request->notes,
            ]);

            // 2. Perbarui status debt
            $debt->paid_amount = (float) ($debt->paid_amount + $payAmount);
            if ($debt->paid_amount >= $debt->amount) {
                $debt->status = 'paid';
            } else {
                $debt->status = 'partial';
            }
            $debt->save();

            // 3. Sinkronisasi saldo dompet jika wallet_id disertakan
            if ($request->filled('wallet_id')) {
                $wallet = $user->wallets()->find($request->wallet_id);
                if ($wallet) {
                    if ($debt->type === 'debt') {
                        // Saya membayar utang -> pengeluaran (outflow)
                        Transaction::create([
                            'user_id' => $user->id,
                            'type' => 'expense',
                            'amount' => $payAmount,
                            'category' => 'Pelunasan Utang',
                            'wallet' => $wallet->name,
                            'description' => $request->notes ?: "Pembayaran utang ke {$debt->person_name}",
                            'date' => $paymentDate,
                            'time' => now()->format('H:i'),
                            'method' => 'Bank transfer',
                        ]);
                    } else {
                        // Orang membayar piutang ke saya -> pemasukan (inflow)
                        Transaction::create([
                            'user_id' => $user->id,
                            'type' => 'income',
                            'amount' => $payAmount,
                            'category' => 'Pelunasan Piutang',
                            'wallet' => $wallet->name,
                            'description' => $request->notes ?: "Penerimaan piutang dari {$debt->person_name}",
                            'date' => $paymentDate,
                            'time' => now()->format('H:i'),
                            'method' => 'Bank transfer',
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => $debt->status === 'paid'
                    ? 'Pembayaran berhasil dicatat. Utang/piutang telah LUNAS!'
                    : 'Pembayaran cicilan berhasil dicatat.',
                'data' => [
                    'debt' => $debt->fresh()->load(['payments.wallet', 'wallet']),
                    'payment' => $payment,
                ],
            ], 200);
        });
    }
}
