<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\FriendController;
use App\Http\Controllers\API\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/search', [UserController::class, 'search']);
    
    // Post routes
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::post('/posts/{id}/like', [PostController::class, 'like']);
    Route::delete('/posts/{id}/like', [PostController::class, 'unlike']);
    
    // Comment routes
    Route::post('/posts/{postId}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
    
    // Friend routes
    Route::post('/friends/request/{userId}', [FriendController::class, 'sendRequest']);
    Route::put('/friends/request/{requestId}/accept', [FriendController::class, 'acceptRequest']);
    Route::put('/friends/request/{requestId}/reject', [FriendController::class, 'rejectRequest']);
    Route::delete('/friends/request/{requestId}', [FriendController::class, 'cancelRequest']);
    Route::get('/friends/requests', [FriendController::class, 'getPendingRequests']);
    Route::delete('/friends/{userId}', [FriendController::class, 'removeFriend']);
    Route::get('/friends', [FriendController::class, 'getFriends']);
    
    // Message routes
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::get('/messages/{userId}', [MessageController::class, 'getConversation']);
    Route::get('/conversations', [MessageController::class, 'getConversations']);
    Route::delete('/messages/{id}', [MessageController::class, 'deleteMessage']);
});
