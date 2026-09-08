<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (view()->exists('index')) {
        return view('index');
    }
    return response()->file(public_path('index.html'));
});