<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
})->name('home');

// Health check endpoint - stateless
Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
})->withoutMiddleware([StartSession::class]);

Route::get('/login', function () {
    return view('app');
})->name('login');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
