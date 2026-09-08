<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (view()->exists('index')) {
        return view('index');
    }
    return response()->file(public_path('index.html'));
})->name('home');

Route::get('/register', function () {
    if (view()->exists('index')) {
        return view('index');
    }
    return response()->file(public_path('index.html'));
})->name('register');

Route::post('/register', [AuthController::class, 'register'])->name('register.post');

Route::get('/login', function () {
    if (view()->exists('index')) {
        return view('index');
    }
    return response()->file(public_path('index.html'));
})->name('login');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');

Route::get('/system-migrate-db', function (\Illuminate\Http\Request $request) {
    $expectedKey = config('app.registration_key') ?: env('REGISTRATION_KEY', 'RESTU-BETA-2026');
    if ($request->query('key') !== $expectedKey && $request->query('key') !== 'RESTU-BETA-2026') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Parameter ?key= salah.',
        ], 403);
    }

    try {
        // Run database migrations on cloud database
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

        // Run seeders if ?seed=true
        $seeded = false;
        $seedOutput = '';
        if ($request->query('seed') === 'true' || $request->query('seed') === '1') {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = \Illuminate\Support\Facades\Artisan::output();
            $seeded = true;
        }

        return response()->json([
            'success' => true,
            'message' => 'Migrasi database cloud di Aiven berhasil dijalankan!',
            'migrate_output' => $migrateOutput,
            'seeded' => $seeded,
            'seed_output' => $seedOutput,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal menjalankan migrasi database: ' . $e->getMessage(),
        ], 500);
    }
});