<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ImportTransactionRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Goal;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    /**
     * Mendapatkan user yang sedang aktif / terautentikasi.
     * Prioritas:
     * 1. Auth bawaan Laravel ($request->user() / Auth::user())
     * 2. Header custom X-User-Id
     * 3. Jika tidak ada user terautentikasi -> abort HTTP 401
     */
    protected function getAuthUser(Request $request): User
    {
        $user = $request->user() ?: auth()->user();
        if ($user) {
            return $user;
        }

        // Header X-User-Id hanya diizinkan saat unit testing otomatis (APP_ENV=testing).
        // Pada environment lokal / LAN / produksi, request wajib menggunakan sesi autentikasi yang valid.
        if (app()->environment('testing')) {
            $userId = $request->header('X-User-Id');
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    return $user;
                }
            }
        }

        abort(response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            'data' => null,
        ], 401));
    }

    // =========================================================================
    // INIT / BOOTSTRAP API (Tahap 3F)
    // =========================================================================

    /**
     * GET /api/init
     * Mengambil seluruh initial data (state) aplikasi dalam satu payload terpadu:
     * - user: profil dan preferensi (theme, currency, language)
     * - wallets: daftar dompet beserta kalkulasi saldo dinamis
     * - categories: daftar kategori
     * - transactions: daftar seluruh transaksi
     * - budgets: target anggaran dan kalkulasi pemakaian bulan berjalan
     * - goals: target tabungan dan progres capaiannya
     * - summary: ringkasan saldo total, income & expense bulan berjalan
     */
    public function init(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $currentMonth = $request->input('month', now()->format('Y-m'));

        // 1. Wallets dengan saldo terkini
        $wallets = $user->wallets()->orderBy('id', 'asc')->get()
            ->map(fn (Wallet $w) => $w->toArrayWithBalance($user));

        // 2. Categories
        $categories = $user->categories()->orderBy('id', 'asc')->get();

        // 3. Transactions (diurutkan tanggal terbaru)
        $transactions = $user->transactions()
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // 4. Budgets dengan pemakaian bulan berjalan
        $budgets = $user->budgets()->orderBy('id', 'asc')->get()
            ->map(fn (Budget $b) => $b->toArrayWithUsage($user, $currentMonth));

        // 5. Goals dengan progres tabungan
        $goals = $user->goals()->orderBy('id', 'asc')->get()
            ->map(fn (Goal $g) => $g->toArrayWithProgress($user));

        // 6. Recurring Transactions (Tahap 6)
        $recurringTransactions = $user->recurringTransactions()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (RecurringTransaction $r) => $r->toArrayForApi());

        // 7. Data Utang & Piutang (v1.4.0)
        $debts = $user->debts()
            ->with(['payments', 'wallet'])
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        // 8. Ringkasan statistik awal
        $totalBalance = (float) $wallets->sum('current_balance');
        $monthTransactions = $transactions->filter(fn ($t) => str_starts_with($t->date, $currentMonth));
        $monthIncome = (float) $monthTransactions->where('type', 'income')->sum('amount');
        $monthExpense = (float) $monthTransactions->where('type', 'expense')->sum('amount');

        // 9. LAN Share Info (Tahap 7)
        $lanService = new LanService();
        $lanInfo = $lanService->getLanInfo($request);

        return response()->json([
            'success' => true,
            'message' => 'Initial data retrieved successfully',
            'data' => [
                'user' => $user,
                'wallets' => $wallets,
                'categories' => $categories,
                'transactions' => $transactions,
                'budgets' => $budgets,
                'goals' => $goals,
                'recurring_transactions' => $recurringTransactions,
                'debts' => $debts,
                'lan' => $lanInfo,
                'summary' => [
                    'total_balance' => $totalBalance,
                    'month_income' => $monthIncome,
                    'month_expense' => $monthExpense,
                    'period' => $currentMonth,
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/lan-info
     * Mendapatkan informasi status jaringan lokal (LAN) server untuk fitur Share Access.
     */
    public function getLanInfo(Request $request): JsonResponse
    {
        $this->getAuthUser($request);
        $lanService = new LanService();
        $lanInfo = $lanService->getLanInfo($request);

        return response()->json([
            'success' => true,
            'message' => 'LAN info retrieved successfully',
            'data' => $lanInfo,
        ], 200);
    }

    // =========================================================================
    // TRANSAKSI API (Tahap 1)
    // =========================================================================

    /**
     * GET /api/transactions
     * Menampilkan daftar transaksi milik user (dengan filter & sorting).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $query = $user->transactions();

        // Filter berdasarkan type (income / expense)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter berdasarkan category (nama atau category_id)
        if ($request->filled('category_id')) {
            $category = $user->categories()->find($request->category_id);
            if ($category) {
                $query->where('category', $category->name);
            }
        } elseif ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter berdasarkan wallet (nama atau wallet_id)
        if ($request->filled('wallet_id')) {
            $wallet = $user->wallets()->find($request->wallet_id);
            if ($wallet) {
                $query->where('wallet', $wallet->name);
            }
        } elseif ($request->filled('wallet')) {
            $query->where('wallet', $request->wallet);
        }

        // Filter pencarian teks (search / q)
        if ($request->filled('search') || $request->filled('q')) {
            $keyword = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                  ->orWhere('note', 'like', "%{$keyword}%")
                  ->orWhere('category', 'like', "%{$keyword}%")
                  ->orWhere('wallet', 'like', "%{$keyword}%");
            });
        }

        // Filter tanggal
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        if ($request->filled('month')) {
            $query->where('date', 'like', $request->month . '%');
        }

        // Sorting
        $sort = $request->input('sort', 'date');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'amount') {
            $query->orderBy('amount', $direction);
        } else {
            $query->orderBy('date', $direction)->orderBy('id', $direction);
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar transaksi berhasil dimuat.',
            'total' => $transactions->count(),
            'data' => $transactions,
        ], 200);
    }

    /**
     * GET /api/transactions/export
     * Mengunduh riwayat transaksi dalam format CSV (RFC 4180 + UTF-8 BOM) sesuai filter aktif.
     */
    public function exportCsv(Request $request)
    {
        $user = $this->getAuthUser($request);
        $query = $user->transactions();

        // Filter berdasarkan type (income / expense)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter berdasarkan category (nama atau category_id)
        if ($request->filled('category_id')) {
            $category = $user->categories()->find($request->category_id);
            if ($category) {
                $query->where('category', $category->name);
            }
        } elseif ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter berdasarkan wallet (nama atau wallet_id)
        if ($request->filled('wallet_id')) {
            $wallet = $user->wallets()->find($request->wallet_id);
            if ($wallet) {
                $query->where('wallet', $wallet->name);
            }
        } elseif ($request->filled('wallet')) {
            $query->where('wallet', $request->wallet);
        }

        // Filter pencarian teks (search / q)
        if ($request->filled('search') || $request->filled('q')) {
            $keyword = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                  ->orWhere('note', 'like', "%{$keyword}%")
                  ->orWhere('category', 'like', "%{$keyword}%")
                  ->orWhere('wallet', 'like', "%{$keyword}%");
            });
        }

        // Filter tanggal
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        if ($request->filled('month')) {
            $query->where('date', 'like', $request->month . '%');
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        $handle = fopen('php://temp', 'r+');
        // UTF-8 BOM untuk kompatibilitas penuh Microsoft Excel
        fwrite($handle, "\xEF\xBB\xBF");
        // Header kolom CSV standar RFC 4180
        fputcsv($handle, ['No', 'Tanggal', 'Tipe', 'Kategori', 'Dompet', 'Deskripsi', 'Nominal', 'Metode', 'Catatan']);

        $no = 1;
        foreach ($transactions as $tx) {
            $formattedDate = $tx->date;
            if ($formattedDate instanceof \DateTimeInterface) {
                $formattedDate = $formattedDate->format('Y-m-d');
            } elseif (is_string($formattedDate)) {
                $formattedDate = substr($formattedDate, 0, 10);
            }
            fputcsv($handle, [
                $no++,
                $formattedDate ?: '',
                $tx->type === 'income' ? 'Income' : 'Expense',
                $tx->category ?? 'Lainnya',
                $tx->wallet ?? 'Dompet Utama',
                $tx->description ?? '',
                (float) $tx->amount,
                $tx->method ?? 'Tunai',
                $tx->note ?? '',
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $filename = 'transaksi_restu_finance_' . now()->format('Ymd_His') . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * POST /api/transactions/import
     * Mengimpor riwayat transaksi dari file CSV dan otomatis menyesuaikan dompet serta kategori.
     */
    public function importCsv(ImportTransactionRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $file = $request->file('file');

        if (!$file || !$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'File CSV tidak valid atau gagal diunggah.',
            ], 422);
        }

        $rawContent = file_get_contents($file->getRealPath());
        if ($rawContent === false || strlen(trim($rawContent)) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'File CSV kosong.',
            ], 422);
        }

        // Hapus UTF-8 BOM jika ada
        if (str_starts_with($rawContent, "\xEF\xBB\xBF")) {
            $rawContent = substr($rawContent, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent));
        if (count($lines) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File CSV harus memiliki baris header dan minimal 1 baris data transaksi.',
            ], 422);
        }

        // Deteksi delimiter (koma atau titik koma)
        $firstLine = $lines[0];
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        $delimiter = $semicolonCount > $commaCount ? ';' : ',';

        // Baca header
        $headers = str_getcsv($firstLine, $delimiter);
        $headerMap = [];
        foreach ($headers as $idx => $header) {
            $cleaned = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));
            if (in_array($cleaned, ['tanggal', 'date', 'tgl', 'time'])) {
                $headerMap['date'] = $idx;
            } elseif (in_array($cleaned, ['tipe', 'type', 'jenis'])) {
                $headerMap['type'] = $idx;
            } elseif (in_array($cleaned, ['kategori', 'category', 'pos'])) {
                $headerMap['category'] = $idx;
            } elseif (in_array($cleaned, ['dompet', 'wallet', 'rekening', 'akun'])) {
                $headerMap['wallet'] = $idx;
            } elseif (in_array($cleaned, ['deskripsi', 'description', 'keterangan', 'judul', 'desc'])) {
                $headerMap['description'] = $idx;
            } elseif (in_array($cleaned, ['nominal', 'amount', 'jumlah', 'nilai', 'total', 'jumlahidr'])) {
                $headerMap['amount'] = $idx;
            } elseif (in_array($cleaned, ['metode', 'method', 'cara'])) {
                $headerMap['method'] = $idx;
            } elseif (in_array($cleaned, ['catatan', 'note', 'notes', 'memo'])) {
                $headerMap['note'] = $idx;
            }
        }

        // Validasi minimal kolom amount
        if (!isset($headerMap['amount'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kolom Nominal/Amount tidak ditemukan pada header file CSV.',
            ], 422);
        }

        $importedCount = 0;
        $skippedCount = 0;

        try {
            DB::transaction(function () use ($lines, $delimiter, $headerMap, $user, &$importedCount, &$skippedCount) {
                for ($i = 1; $i < count($lines); $i++) {
                    $line = trim($lines[$i]);
                    if ($line === '') continue;

                    $row = str_getcsv($line, $delimiter);
                    if (empty($row) || (count($row) <= 1 && empty($row[0]))) continue;

                    // Parse amount
                    $rawAmount = $row[$headerMap['amount']] ?? '0';
                    $cleanAmount = preg_replace('/[^0-9.]/', '', str_replace(',', '.', preg_replace('/[^\d,.]/', '', $rawAmount)));
                    $amount = (float) $cleanAmount;
                    if ($amount <= 0) {
                        $skippedCount++;
                        continue;
                    }

                    // Parse type
                    $rawType = strtolower(trim($row[$headerMap['type'] ?? -1] ?? ''));
                    $type = (str_starts_with($rawType, 'inc') || str_starts_with($rawType, 'pem') || $rawType === 'masuk') ? 'income' : 'expense';

                    // Parse date
                    $rawDate = trim($row[$headerMap['date'] ?? -1] ?? '');
                    try {
                        $date = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : now()->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $date = now()->format('Y-m-d');
                    }

                    // Parse category
                    $categoryName = trim($row[$headerMap['category'] ?? -1] ?? '');
                    if ($categoryName === '') {
                        $categoryName = $type === 'income' ? 'Gaji' : 'Lainnya';
                    }
                    $user->categories()->firstOrCreate(
                        ['name' => $categoryName],
                        ['type' => $type]
                    );

                    // Parse wallet
                    $walletName = trim($row[$headerMap['wallet'] ?? -1] ?? '');
                    if ($walletName === '') {
                        $walletName = 'Dompet Utama';
                    }
                    $user->wallets()->firstOrCreate(
                        ['name' => $walletName],
                        ['opening_balance' => 0, 'is_active' => true]
                    );

                    // Parse description, method, note
                    $description = trim($row[$headerMap['description'] ?? -1] ?? '') ?: $categoryName;
                    $method = trim($row[$headerMap['method'] ?? -1] ?? '') ?: 'Tunai';
                    $note = trim($row[$headerMap['note'] ?? -1] ?? '');

                    $user->transactions()->create([
                        'date' => $date,
                        'type' => $type,
                        'category' => $categoryName,
                        'wallet' => $walletName,
                        'description' => $description,
                        'amount' => $amount,
                        'method' => $method,
                        'time' => '12:00',
                        'note' => $note,
                    ]);

                    $importedCount++;
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file CSV: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$importedCount} transaksi.",
            'data' => [
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
            ],
        ], 200);
    }

    /**
     * GET /api/transactions/{id}
     * Menampilkan detail satu transaksi milik user.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $transaction = $user->transactions()->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi berhasil dimuat.',
            'data' => $transaction,
        ], 200);
    }

    /**
     * POST /api/transactions
     * Membuat transaksi baru milik user.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        // Resolusi nama Dompet (wallet)
        $walletName = null;
        if ($request->filled('wallet_id')) {
            $walletObj = $user->wallets()->find($request->wallet_id);
            $walletName = $walletObj ? $walletObj->name : null;
        } elseif ($request->filled('wallet')) {
            $walletName = trim($request->wallet);
        }

        if (empty($walletName)) {
            $firstWallet = $user->wallets()->where('is_active', true)->first() ?: $user->wallets()->first();
            $walletName = $firstWallet ? $firstWallet->name : 'Utama';
        }

        // Resolusi nama Kategori (category)
        $categoryName = null;
        if ($request->filled('category_id')) {
            $catObj = $user->categories()->find($request->category_id);
            $categoryName = $catObj ? $catObj->name : null;
        } elseif ($request->filled('category')) {
            $categoryName = trim($request->category);
        }

        if (empty($categoryName)) {
            $categoryName = $request->type === 'income' ? 'Pemasukan' : 'Lainnya';
        }

        // Resolusi Tanggal Transaksi (transaction_date / date)
        $date = $request->input('transaction_date') ?? $request->input('date') ?? now()->toDateString();

        // Resolusi Deskripsi
        $description = $request->input('description');
        if (empty($description)) {
            $description = $request->input('note') ?: $categoryName;
        }

        $transaction = $user->transactions()->create([
            'wallet' => $walletName,
            'category' => $categoryName,
            'type' => $request->type,
            'amount' => (float) $request->amount,
            'description' => $description,
            'method' => $request->input('method', 'E-wallet'),
            'date' => $date,
            'time' => $request->input('time', now()->format('H:i')),
            'note' => $request->input('note'),
            'goal' => $request->input('goal'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * PUT/PATCH /api/transactions/{id}
     * Memperbarui transaksi milik user.
     */
    public function update(UpdateTransactionRequest $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $transaction = $user->transactions()->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        $payload = [];

        if ($request->has('type')) {
            $payload['type'] = $request->type;
        }

        if ($request->has('amount')) {
            $payload['amount'] = (float) $request->amount;
        }

        if ($request->filled('wallet_id')) {
            $walletObj = $user->wallets()->find($request->wallet_id);
            if ($walletObj) {
                $payload['wallet'] = $walletObj->name;
            }
        } elseif ($request->has('wallet')) {
            $payload['wallet'] = trim($request->wallet);
        }

        if ($request->filled('category_id')) {
            $catObj = $user->categories()->find($request->category_id);
            if ($catObj) {
                $payload['category'] = $catObj->name;
            }
        } elseif ($request->has('category')) {
            $payload['category'] = trim($request->category);
        }

        if ($request->has('transaction_date')) {
            $payload['date'] = $request->transaction_date;
        } elseif ($request->has('date')) {
            $payload['date'] = $request->date;
        }

        if ($request->has('description')) {
            $payload['description'] = $request->description;
        }

        if ($request->has('method')) {
            $payload['method'] = $request->method;
        }

        if ($request->has('time')) {
            $payload['time'] = $request->time;
        }

        if ($request->has('note')) {
            $payload['note'] = $request->note;
        }

        if ($request->has('goal')) {
            $payload['goal'] = $request->goal;
        }

        $transaction->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil diperbarui.',
            'data' => $transaction->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/transactions/{id}
     * Menghapus satu transaksi milik user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $transaction = $user->transactions()->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    // =========================================================================
    // WALLET / DOMPET API (Tahap 2)
    // =========================================================================

    /**
     * GET /api/wallets
     * Menampilkan daftar dompet milik user beserta kalkulasi saldo dinamis.
     */
    public function indexWallets(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $wallets = $user->wallets()->orderBy('id', 'asc')->get();

        $data = $wallets->map(fn (Wallet $w) => $w->toArrayWithBalance($user));

        return response()->json([
            'success' => true,
            'message' => 'Daftar dompet berhasil dimuat.',
            'total' => $wallets->count(),
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/wallets/{id}
     * Menampilkan rincian satu dompet lengkap dengan total income, expense, dan current balance.
     */
    public function showWallet(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $wallet = $user->wallets()->find($id);

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Dompet tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail dompet berhasil dimuat.',
            'data' => $wallet->toArrayWithBalance($user),
        ], 200);
    }

    /**
     * POST /api/wallets
     * Membuat dompet baru milik user.
     */
    public function storeWallet(StoreWalletRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $initialBalance = $request->input('initial_balance', $request->input('opening_balance', 0));

        $wallet = $user->wallets()->create([
            'name' => trim($request->name),
            'opening_balance' => (float) $initialBalance,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dompet berhasil dibuat.',
            'data' => $wallet->toArrayWithBalance($user),
        ], 201);
    }

    /**
     * PUT/PATCH /api/wallets/{id}
     * Memperbarui nama atau saldo awal dompet.
     */
    public function updateWallet(UpdateWalletRequest $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $wallet = $user->wallets()->find($id);

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Dompet tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Jika nama dompet diubah, perbarui riwayat transaksi terkait agar tetap tersinkron
        if ($request->filled('name')) {
            $oldName = $wallet->name;
            $newName = trim($request->name);

            if ($oldName !== $newName) {
                $wallet->name = $newName;
                $user->transactions()->where('wallet', $oldName)->update(['wallet' => $newName]);
            }
        }

        if ($request->has('initial_balance') || $request->has('opening_balance')) {
            $wallet->opening_balance = (float) $request->input('initial_balance', $request->input('opening_balance'));
        }

        if ($request->has('is_active')) {
            $wallet->is_active = $request->boolean('is_active');
        }

        $wallet->save();

        return response()->json([
            'success' => true,
            'message' => 'Dompet berhasil diperbarui.',
            'data' => $wallet->fresh()->toArrayWithBalance($user),
        ], 200);
    }

    /**
     * DELETE /api/wallets/{id}
     * Menghapus dompet jika tidak memiliki riwayat transaksi terkait.
     */
    public function destroyWallet(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $wallet = $user->wallets()->find($id);

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Dompet tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Cegah penghapusan sembarangan jika dompet masih memiliki transaksi terkait
        $transactionCount = $user->transactions()->where('wallet', $wallet->name)->count();
        if ($transactionCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Dompet \"{$wallet->name}\" tidak dapat dihapus karena masih digunakan oleh {$transactionCount} transaksi terkait.",
                'data' => [
                    'transactions_count' => $transactionCount,
                ],
            ], 422);
        }

        $wallet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dompet berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    // =========================================================================
    // CATEGORY / KATEGORI API (Tahap 3B)
    // =========================================================================

    /**
     * GET /api/categories
     * Menampilkan daftar kategori milik user (dengan opsi filter type & search).
     */
    public function indexCategories(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $query = $user->categories();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search') || $request->filled('q')) {
            $keyword = $request->input('search', $request->input('q'));
            $query->where('name', 'like', "%{$keyword}%");
        }

        $categories = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori berhasil dimuat.',
            'total' => $categories->count(),
            'data' => $categories,
        ], 200);
    }

    /**
     * GET /api/categories/{id}
     * Menampilkan detail satu kategori milik user.
     */
    public function showCategory(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $category = $user->categories()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil dimuat.',
            'data' => $category,
        ], 200);
    }

    /**
     * POST /api/categories
     * Menambahkan kategori baru milik user.
     */
    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $category = $user->categories()->create([
            'name' => trim($request->name),
            'type' => strtolower($request->type),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    /**
     * PUT/PATCH /api/categories/{id}
     * Mengedit nama atau tipe kategori milik user.
     */
    public function updateCategory(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $category = $user->categories()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Jika nama kategori diubah, sinkronkan ke transaksi dan budget terkait
        if ($request->filled('name')) {
            $oldName = $category->name;
            $newName = trim($request->name);

            if ($oldName !== $newName) {
                $category->name = $newName;
                $user->transactions()->where('category', $oldName)->update(['category' => $newName]);
                $user->budgets()->where('category', $oldName)->update(['category' => $newName]);
            }
        }

        if ($request->filled('type')) {
            $category->type = strtolower($request->type);
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/categories/{id}
     * Menghapus kategori jika tidak digunakan oleh transaksi atau budget.
     */
    public function destroyCategory(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $category = $user->categories()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Periksa apakah kategori masih digunakan dalam riwayat transaksi
        $txCount = $user->transactions()->where('category', $category->name)->count();
        if ($txCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kategori \"{$category->name}\" tidak dapat dihapus karena masih digunakan oleh {$txCount} transaksi terkait.",
                'data' => [
                    'transactions_count' => $txCount,
                ],
            ], 422);
        }

        // Periksa apakah kategori masih digunakan dalam alokasi budget
        $budgetCount = $user->budgets()->where('category', $category->name)->count();
        if ($budgetCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kategori \"{$category->name}\" tidak dapat dihapus karena masih digunakan dalam alokasi anggaran (budget).",
                'data' => [
                    'budgets_count' => $budgetCount,
                ],
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    // =========================================================================
    // BUDGET / ANGGARAN API (Tahap 3C)
    // =========================================================================

    /**
     * GET /api/budgets
     * Menampilkan daftar target anggaran milik user beserta kalkulasi usage & remaining.
     */
    public function indexBudgets(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $month = $request->input('month', now()->format('Y-m'));

        $budgets = $user->budgets()->orderBy('id', 'asc')->get();
        $data = $budgets->map(fn (Budget $b) => $b->toArrayWithUsage($user, $month));

        $totalBudget = (float) $budgets->sum('limit_amount');
        $totalSpent = (float) $data->sum('used');
        $totalRemaining = max(0, $totalBudget - $totalSpent);
        $overallPercentage = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0.0;

        return response()->json([
            'success' => true,
            'message' => 'Daftar anggaran berhasil dimuat.',
            'total' => $budgets->count(),
            'period' => $month,
            'summary' => [
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'total_remaining' => $totalRemaining,
                'overall_percentage' => $overallPercentage,
            ],
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/budgets/{id}
     * Menampilkan detail satu target anggaran beserta kalkulasi usage & remaining.
     */
    public function showBudget(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $budget = $user->budgets()->find($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Anggaran tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        $month = $request->input('month', now()->format('Y-m'));

        return response()->json([
            'success' => true,
            'message' => 'Detail anggaran berhasil dimuat.',
            'data' => $budget->toArrayWithUsage($user, $month),
        ], 200);
    }

    /**
     * POST /api/budgets
     * Menambahkan atau mengatur target anggaran kategori baru.
     */
    public function storeBudget(StoreBudgetRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        // Resolusi nama kategori
        $categoryName = null;
        if ($request->filled('category_id')) {
            $cat = $user->categories()->find($request->category_id);
            $categoryName = $cat ? $cat->name : null;
        } elseif ($request->filled('category')) {
            $categoryName = trim($request->category);
        }

        if (empty($categoryName)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori anggaran (category_id atau category) wajib dipilih atau diisi.',
                'errors' => ['category' => ['Kategori anggaran wajib dipilih atau diisi.']],
            ], 422);
        }

        // Resolusi nominal anggaran
        $limitAmount = $request->input('amount', $request->input('limit', $request->input('limit_amount')));

        if ($limitAmount === null || !is_numeric($limitAmount) || $limitAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nominal anggaran (amount / limit) wajib diisi dan harus lebih dari 0.',
                'errors' => ['amount' => ['Nominal anggaran wajib diisi dan harus lebih dari 0.']],
            ], 422);
        }

        // Simpan / update jika anggaran kategori sudah ada
        $budget = $user->budgets()->updateOrCreate(
            ['category' => $categoryName],
            ['limit_amount' => (float) $limitAmount]
        );

        $month = $request->input('month', now()->format('Y-m'));

        return response()->json([
            'success' => true,
            'message' => 'Anggaran berhasil disimpan.',
            'data' => $budget->toArrayWithUsage($user, $month),
        ], 201);
    }

    /**
     * PUT/PATCH /api/budgets/{id}
     * Memperbarui nominal atau kategori target anggaran.
     */
    public function updateBudget(UpdateBudgetRequest $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $budget = $user->budgets()->find($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Anggaran tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        if ($request->filled('category_id')) {
            $cat = $user->categories()->find($request->category_id);
            if ($cat) {
                $newCat = $cat->name;
                if ($newCat !== $budget->category && $user->budgets()->where('category', $newCat)->where('id', '!=', $budget->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Anggaran untuk kategori \"{$newCat}\" sudah ada.",
                        'errors' => ['category' => ["Anggaran untuk kategori \"{$newCat}\" sudah ada."]],
                    ], 422);
                }
                $budget->category = $newCat;
            }
        } elseif ($request->filled('category')) {
            $newCat = trim($request->category);
            if ($newCat !== $budget->category && $user->budgets()->where('category', $newCat)->where('id', '!=', $budget->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Anggaran untuk kategori \"{$newCat}\" sudah ada.",
                    'errors' => ['category' => ["Anggaran untuk kategori \"{$newCat}\" sudah ada."]],
                ], 422);
            }
            $budget->category = $newCat;
        }

        if ($request->has('amount') || $request->has('limit') || $request->has('limit_amount')) {
            $limitAmount = $request->input('amount', $request->input('limit', $request->input('limit_amount')));
            if (is_numeric($limitAmount) && $limitAmount > 0) {
                $budget->limit_amount = (float) $limitAmount;
            }
        }

        $budget->save();

        $month = $request->input('month', now()->format('Y-m'));

        return response()->json([
            'success' => true,
            'message' => 'Anggaran berhasil diperbarui.',
            'data' => $budget->fresh()->toArrayWithUsage($user, $month),
        ], 200);
    }

    /**
     * DELETE /api/budgets/{id}
     * Menghapus target anggaran (tanpa menghapus transaksi).
     */
    public function destroyBudget(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $budget = $user->budgets()->find($id);

        if (!$budget) {
            return response()->json([
                'success' => false,
                'message' => 'Anggaran tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Hapus budget saja, riwayat transaksi tetap aman
        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anggaran berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    // =========================================================================
    // GOAL / TARGET TABUNGAN API (Tahap 3D)
    // =========================================================================

    /**
     * GET /api/goals
     * Menampilkan daftar target tabungan milik user beserta kalkulasi progres capaian.
     */
    public function indexGoals(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $goals = $user->goals()->orderBy('id', 'asc')->get();

        $data = $goals->map(fn (Goal $g) => $g->toArrayWithProgress($user));

        return response()->json([
            'success' => true,
            'message' => 'Daftar target tabungan berhasil dimuat.',
            'total' => $goals->count(),
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/goals/{id}
     * Menampilkan detail satu target tabungan beserta progres capaian.
     */
    public function showGoal(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $goal = $user->goals()->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Target tabungan tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail target tabungan berhasil dimuat.',
            'data' => $goal->toArrayWithProgress($user),
        ], 200);
    }

    /**
     * POST /api/goals
     * Membuat target tabungan baru milik user.
     */
    public function storeGoal(StoreGoalRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        // Resolusi Nama Goal
        $name = trim($request->input('name', $request->input('title', '')));
        $targetAmount = (float) $request->input('target_amount', $request->input('target'));
        $savedAmount = (float) $request->input('saved_amount', $request->input('saved', 0));

        $goal = $user->goals()->create([
            'name' => $name,
            'target_amount' => $targetAmount,
            'saved_amount' => $savedAmount,
            'deadline' => $request->deadline,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Target tabungan berhasil dibuat.',
            'data' => $goal->toArrayWithProgress($user),
        ], 201);
    }

    /**
     * PUT/PATCH /api/goals/{id}
     * Memperbarui target tabungan milik user.
     */
    public function updateGoal(UpdateGoalRequest $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $goal = $user->goals()->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Target tabungan tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Jika nama diubah, sinkronkan nama di transaksi terkait
        if ($request->filled('name') || $request->filled('title')) {
            $oldName = $goal->name;
            $newName = trim($request->input('name', $request->input('title')));

            if ($oldName !== $newName) {
                $goal->name = $newName;
                $user->transactions()->where('goal', $oldName)->update(['goal' => $newName]);
            }
        }

        if ($request->has('target_amount') || $request->has('target')) {
            $t = $request->input('target_amount', $request->input('target'));
            if (is_numeric($t) && $t > 0) {
                $goal->target_amount = (float) $t;
            }
        }

        if ($request->has('saved_amount') || $request->has('saved')) {
            $s = $request->input('saved_amount', $request->input('saved'));
            if (is_numeric($s) && $s >= 0) {
                $goal->saved_amount = (float) $s;
            }
        }

        if ($request->filled('deadline')) {
            $goal->deadline = $request->deadline;
        }

        $goal->save();

        return response()->json([
            'success' => true,
            'message' => 'Target tabungan berhasil diperbarui.',
            'data' => $goal->fresh()->toArrayWithProgress($user),
        ], 200);
    }

    /**
     * DELETE /api/goals/{id}
     * Menghapus target tabungan (transaksi terkait tetap aman dan goal-nya di-unlink).
     */
    public function destroyGoal(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $goal = $user->goals()->find($id);

        if (!$goal) {
            return response()->json([
                'success' => false,
                'message' => 'Target tabungan tidak ditemukan atau Anda tidak memiliki akses.',
                'data' => null,
            ], 404);
        }

        // Unlink transaksi yang merujuk pada goal ini agar data transaksi tidak terhapus
        $user->transactions()->where('goal', $goal->name)->update(['goal' => null]);

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Target tabungan berhasil dihapus.',
            'data' => null,
        ], 200);
    }

    // =========================================================================
    // PROFILE / PENGATURAN API (Tahap 3E)
    // =========================================================================

    /**
     * GET /api/profile
     * Menampilkan profil pengguna dan preferensi tampilan/bahasa yang sedang aktif.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna berhasil dimuat.',
            'data' => $user,
        ], 200);
    }

    /**
     * PUT/PATCH /api/profile
     * Memperbarui profil, preferensi tema, mata uang, dan bahasa pengguna.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->filled('name')) {
            $user->name = trim($request->name);
        }

        if ($request->filled('email')) {
            $user->email = strtolower(trim($request->email));
        }

        if ($request->filled('theme')) {
            $user->theme = strtolower(trim($request->theme));
        }

        if ($request->filled('currency')) {
            $user->currency = strtoupper(trim($request->currency));
        }

        if ($request->filled('language')) {
            $user->language = strtolower(trim($request->language));
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna berhasil diperbarui.',
            'data' => $user->fresh(),
        ], 200);
    }

    /**
     * PUT/PATCH /api/profile/password
     * Memperbarui kata sandi pengguna dengan validasi kata sandi saat ini.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui.',
            'data' => null,
        ], 200);
    }
}
