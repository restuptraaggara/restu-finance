<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringRequest;
use App\Http\Requests\UpdateRecurringRequest;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Category;
use App\Services\RecurringTransactionProcessor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringTransactionController extends Controller
{
    // =========================================================================
    // Helpers
    // =========================================================================

    protected function getAuthUser(Request $request): User
    {
        $user = $request->user() ?: auth()->user();
        if ($user) {
            return $user;
        }

        // Header X-User-Id hanya diizinkan saat unit testing otomatis (APP_ENV=testing).
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
    // CRUD
    // =========================================================================

    /**
     * GET /api/recurring-transactions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $recurring = $user->recurringTransactions()->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $recurring->map(fn($r) => $r->toArrayForApi()),
        ]);
    }

    /**
     * POST /api/recurring-transactions
     */
    public function store(StoreRecurringRequest $request): JsonResponse
    {
        $user = $this->getAuthUser($request);

        $recurring = new RecurringTransaction();
        $recurring->user_id = $user->id;
        $recurring->wallet = $request->wallet;
        $recurring->category = $request->category;
        $recurring->type = $request->type;
        $recurring->amount = $request->amount;
        $recurring->description = $request->description ?? '';
        $recurring->frequency = $request->frequency;
        $recurring->start_date = $request->start_date ? Carbon::parse($request->start_date)->toDateString() : Carbon::parse($request->next_date)->toDateString();
        $recurring->next_date = Carbon::parse($request->next_date)->toDateString();
        $recurring->end_date = $request->end_date ? Carbon::parse($request->end_date)->toDateString() : null;
        $recurring->is_active = $request->boolean('is_active', true);
        $recurring->save();

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction berhasil dibuat.',
            'data' => $recurring->toArrayForApi(),
        ], 201);
    }

    /**
     * GET /api/recurring-transactions/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $recurring = $user->recurringTransactions()->find($id);

        if (!$recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Recurring transaction tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $recurring->toArrayForApi(),
        ]);
    }

    /**
     * PUT/PATCH /api/recurring-transactions/{id}
     */
    public function update(UpdateRecurringRequest $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $recurring = $user->recurringTransactions()->find($id);

        if (!$recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Recurring transaction tidak ditemukan.',
            ], 404);
        }

        $allowed = ['wallet', 'category', 'type', 'amount', 'description', 'frequency', 'start_date', 'next_date', 'end_date', 'is_active'];
        foreach ($allowed as $field) {
            if ($request->has($field)) {
                if (in_array($field, ['start_date', 'next_date', 'end_date'])) {
                    $recurring->$field = $request->input($field) ? Carbon::parse($request->input($field))->toDateString() : null;
                } elseif ($field === 'is_active') {
                    $recurring->$field = $request->boolean($field);
                } else {
                    $recurring->$field = $request->input($field);
                }
            }
        }
        $recurring->save();

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction berhasil diperbarui.',
            'data' => $recurring->toArrayForApi(),
        ]);
    }

    /**
     * DELETE /api/recurring-transactions/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $recurring = $user->recurringTransactions()->find($id);

        if (!$recurring) {
            return response()->json([
                'success' => false,
                'message' => 'Recurring transaction tidak ditemukan.',
            ], 404);
        }

        $recurring->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recurring transaction berhasil dihapus.',
            'data' => null,
        ]);
    }

    // =========================================================================
    // PROCESS — POST /api/recurring-transactions/process
    // =========================================================================

    /**
     * POST /api/recurring-transactions/process
     * Proses recurring transaction yang sudah jatuh tempo untuk user yang sedang login.
     */
    public function process(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $processor = new RecurringTransactionProcessor();
        $result = $processor->processForUser($user);

        return response()->json([
            'success' => true,
            'message' => "Processed {$result['processed']} recurring transaction(s).",
            'data' => $result,
        ]);
    }
}
