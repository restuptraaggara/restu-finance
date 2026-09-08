<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\RecurringTransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Pengatur Uang (Restu Finance)
|--------------------------------------------------------------------------
*/

// Endpoint Autentikasi Publik
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.login');
Route::post('register', [AuthController::class, 'register'])->name('api.register');
Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');
Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:login')->name('api.password.forgot');
Route::post('password/reset-otp', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:login')->name('api.password.reset_otp');
Route::get('me', [AuthController::class, 'me'])->name('api.me');

// Endpoint Inisialisasi State Aplikasi (Tahap 3F)
Route::get('init', [FinanceController::class, 'init'])->name('api.init');
Route::get('lan-info', [FinanceController::class, 'getLanInfo'])->name('api.lan.info');

// Endpoint Transaksi (Tahap 1)
Route::prefix('transactions')->group(function () {
    Route::get('/', [FinanceController::class, 'index'])->name('api.transactions.index');
    Route::get('/export', [FinanceController::class, 'exportCsv'])->name('api.transactions.export');
    Route::post('/import', [FinanceController::class, 'importCsv'])->name('api.transactions.import');
    Route::post('/', [FinanceController::class, 'store'])->name('api.transactions.store');
    Route::get('/{id}', [FinanceController::class, 'show'])->name('api.transactions.show');
    Route::match(['put', 'patch'], '/{id}', [FinanceController::class, 'update'])->name('api.transactions.update');
    Route::delete('/{id}', [FinanceController::class, 'destroy'])->name('api.transactions.destroy');
});

// Endpoint Wallet / Dompet (Tahap 2)
Route::prefix('wallets')->group(function () {
    Route::get('/', [FinanceController::class, 'indexWallets'])->name('api.wallets.index');
    Route::post('/', [FinanceController::class, 'storeWallet'])->name('api.wallets.store');
    Route::get('/{id}', [FinanceController::class, 'showWallet'])->name('api.wallets.show');
    Route::match(['put', 'patch'], '/{id}', [FinanceController::class, 'updateWallet'])->name('api.wallets.update');
    Route::delete('/{id}', [FinanceController::class, 'destroyWallet'])->name('api.wallets.destroy');
});

// Endpoint Kategori (Tahap 3B)
Route::prefix('categories')->group(function () {
    Route::get('/', [FinanceController::class, 'indexCategories'])->name('api.categories.index');
    Route::post('/', [FinanceController::class, 'storeCategory'])->name('api.categories.store');
    Route::get('/{id}', [FinanceController::class, 'showCategory'])->name('api.categories.show');
    Route::match(['put', 'patch'], '/{id}', [FinanceController::class, 'updateCategory'])->name('api.categories.update');
    Route::delete('/{id}', [FinanceController::class, 'destroyCategory'])->name('api.categories.destroy');
});

// Endpoint Budget / Anggaran (Tahap 3C)
Route::prefix('budgets')->group(function () {
    Route::get('/', [FinanceController::class, 'indexBudgets'])->name('api.budgets.index');
    Route::post('/', [FinanceController::class, 'storeBudget'])->name('api.budgets.store');
    Route::get('/{id}', [FinanceController::class, 'showBudget'])->name('api.budgets.show');
    Route::match(['put', 'patch'], '/{id}', [FinanceController::class, 'updateBudget'])->name('api.budgets.update');
    Route::delete('/{id}', [FinanceController::class, 'destroyBudget'])->name('api.budgets.destroy');
});

// Endpoint Goal / Target Tabungan (Tahap 3D)
Route::prefix('goals')->group(function () {
    Route::get('/', [FinanceController::class, 'indexGoals'])->name('api.goals.index');
    Route::post('/', [FinanceController::class, 'storeGoal'])->name('api.goals.store');
    Route::get('/{id}', [FinanceController::class, 'showGoal'])->name('api.goals.show');
    Route::match(['put', 'patch'], '/{id}', [FinanceController::class, 'updateGoal'])->name('api.goals.update');
    Route::delete('/{id}', [FinanceController::class, 'destroyGoal'])->name('api.goals.destroy');
});

// Endpoint Profil & Pengaturan User (Tahap 3E)
Route::prefix('profile')->group(function () {
    Route::get('/', [FinanceController::class, 'getProfile'])->name('api.profile.show');
    Route::match(['put', 'patch'], '/', [FinanceController::class, 'updateProfile'])->name('api.profile.update');
    Route::match(['put', 'patch'], '/password', [FinanceController::class, 'changePassword'])->name('api.profile.password');
});

// Endpoint Recurring Transactions (Tahap 6)
Route::prefix('recurring-transactions')->group(function () {
    Route::get('/', [RecurringTransactionController::class, 'index'])->name('api.recurring.index');
    Route::post('/process', [RecurringTransactionController::class, 'process'])->name('api.recurring.process');
    Route::post('/', [RecurringTransactionController::class, 'store'])->name('api.recurring.store');
    Route::get('/{id}', [RecurringTransactionController::class, 'show'])->name('api.recurring.show');
    Route::match(['put', 'patch'], '/{id}', [RecurringTransactionController::class, 'update'])->name('api.recurring.update');
    Route::delete('/{id}', [RecurringTransactionController::class, 'destroy'])->name('api.recurring.destroy');
});

// Endpoint Utang & Piutang (v1.4.0)
Route::prefix('debts')->group(function () {
    Route::get('/', [DebtController::class, 'index'])->name('api.debts.index');
    Route::post('/', [DebtController::class, 'store'])->name('api.debts.store');
    Route::get('/{id}', [DebtController::class, 'show'])->name('api.debts.show');
    Route::match(['put', 'patch'], '/{id}', [DebtController::class, 'update'])->name('api.debts.update');
    Route::delete('/{id}', [DebtController::class, 'destroy'])->name('api.debts.destroy');
    Route::post('/{id}/pay', [DebtController::class, 'pay'])->name('api.debts.pay');
});

// Endpoint Kritik & Saran Pengguna (v1.4.0)
Route::post('feedback', [FeedbackController::class, 'store'])->name('api.feedback.store');

// Endpoint Live Chat Pengguna (v1.5.0)
Route::prefix('chat')->group(function () {
    Route::get('/messages', [ChatController::class, 'getMessages'])->name('api.chat.messages');
    Route::post('/messages', [ChatController::class, 'sendMessage'])->name('api.chat.send');
    Route::post('/read', [ChatController::class, 'markAsRead'])->name('api.chat.read');
});

// Endpoint Admin Live Chat & Support Center (v1.5.0)
Route::prefix('admin/chat')->middleware('admin')->group(function () {
    Route::get('/conversations', [ChatController::class, 'adminGetConversations'])->name('api.admin.chat.conversations');
    Route::get('/{userId}/messages', [ChatController::class, 'adminGetMessages'])->name('api.admin.chat.messages');
    Route::post('/{userId}/reply', [ChatController::class, 'adminReply'])->name('api.admin.chat.reply');
    Route::post('/{userId}/read', [ChatController::class, 'adminMarkAsRead'])->name('api.admin.chat.read');
});

