<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function() {
    return 'Hello World';
});

// Simple test endpoint that bypasses most middleware
Route::post('/no-csrf-test', function() {
    return response()->json(['message' => 'This works without CSRF!']);
});

// Your actual API endpoint
Route::post('/api/register', [UserController::class, 'register']);
