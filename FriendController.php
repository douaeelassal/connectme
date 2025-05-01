<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FriendController extends Controller
{
    // Send a friend request
    public function sendRequest($userId)
    {
        $user = User::findOrFail($userId);
        $currentUser = Auth::user();
        
        // Cannot send request to self
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot send a friend request to yourself'], 422);
        }
        
        // Check if request already exists
        $existingRequest = FriendRequest::where(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $currentUser->id)
                    ->where('receiver_id', $user->id);
            })
            ->orWhere(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $currentUser->id);
            })
            ->first();
            
        if ($existingRequest) {
            return response()->json(['message' => 'Friend request already exists'], 422);
        }
        
        // Create new friend request
        $friendRequest = new FriendRequest();
        $friendRequest->sender_id = $currentUser->id;
        $friendRequest->receiver_id = $user->id;
        $friendRequest->status = 'pending';
        $friendRequest->save();
        
        return response()->json(['message' => 'Friend request sent successfully']);
    }

    // Accept a friend request
    public function acceptRequest($requestId)
    {
        $friendRequest = FriendRequest::findOrFail($requestId);
        $currentUser = Auth::user();
        
        // Check if the authenticated user is the receiver of the request
        if ($currentUser->id !== $friendRequest->receiver_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Update request status
        $friendRequest->status = 'accepted';
        $friendRequest->save();
        
        // Create friendship in friends table or through a pivot table
        // This depends on how you've designed your database
        // Here's an example using a Many-to-Many relationship with a pivot table
        $currentUser->friends()->attach($friendRequest->sender_id, ['created_at' => now()]);
        
        return response()->json(['message' => 'Friend request accepted successfully']);
    }

    // Reject a friend request
    public function rejectRequest($requestId)
    {
        $friendRequest = FriendRequest::findOrFail($requestId);
        $currentUser = Auth::user();
        
        // Check if the authenticated user is the receiver of the request
        if ($currentUser->id !== $friendRequest->receiver_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Update request status
        $friendRequest->status = 'rejected';
        $friendRequest->save();
        
        return response()->json(['message' => 'Friend request rejected successfully']);
    }

    // Cancel a sent friend request
    public function cancelRequest($requestId)
    {
        $friendRequest = FriendRequest::findOrFail($requestId);
        $currentUser = Auth::user();
        
        // Check if the authenticated user is the sender of the request
        if ($currentUser->id !== $friendRequest->sender_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $friendRequest->delete();
        
        return response()->json(['message' => 'Friend request cancelled successfully']);
    }

    // Get pending friend requests
    public function getPendingRequests()
    {
        $currentUser = Auth::user();
        
        $receivedRequests = FriendRequest::where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->with('sender')
            ->get();
            
        $sentRequests = FriendRequest::where('sender_id', $currentUser->id)
            ->where('status', 'pending')
            ->with('receiver')
            ->get();
            
        return response()->json([
            'received_requests' => $receivedRequests,
            'sent_requests' => $sentRequests
        ]);
    }

    // Remove a friend
    public function removeFriend($userId)
    {
        $user = User::findOrFail($userId);
        $currentUser = Auth::user();
        
        // Remove friendship
        $currentUser->friends()->detach($user->id);
        
        return response()->json(['message' => 'Friend removed successfully']);
    }

    // Get user's friends list
    public function getFriends()
    {
        $currentUser = Auth::user();
        $friends = $currentUser->friends;
        
        return response()->json(['friends' => $friends]);
    }
}
