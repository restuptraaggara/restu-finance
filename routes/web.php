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