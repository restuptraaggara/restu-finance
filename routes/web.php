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
    $validKeys = array_unique([$expectedKey, 'RESTU-BETA-2026', 'RESTUBETA2026']);
    if (!in_array($request->query('key'), $validKeys)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Parameter ?key= salah.',
        ], 403);
    }

    if ($request->query('diag') === '1' || $request->query('diag') === 'true') {
        $diag = [
            'php_version' => PHP_VERSION,
            'hashing_config' => config('hashing'),
            'env_bcrypt_rounds' => env('BCRYPT_ROUNDS'),
            'password_algos' => function_exists('password_algos') ? password_algos() : [],
        ];
        try {
            $diag['laravel_hash'] = \Illuminate\Support\Facades\Hash::make('test123456');
        } catch (\Throwable $e) {
            $diag['laravel_hash_error'] = get_class($e) . ': ' . $e->getMessage();
        }
        try {
            $diag['native_password_hash'] = password_hash('test123456', PASSWORD_DEFAULT);
        } catch (\Throwable $e) {
            $diag['native_password_hash_error'] = get_class($e) . ': ' . $e->getMessage();
        }
        return response()->json([
            'success' => true,
            'diagnostic' => $diag,
        ]);
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