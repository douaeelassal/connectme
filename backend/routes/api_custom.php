<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

// Public API routes (no CSRF or authentication)
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::get('/test', function() {
    return response()->json(['message' => 'API is working!']);
});
