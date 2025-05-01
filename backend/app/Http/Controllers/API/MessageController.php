<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MessageController extends Controller
{
    // Send a message
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'content' => 'required_without:media|string',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currentUser = Auth::user();
        $receiver = User::findOrFail($request->receiver_id);
        
        $message = new Message();
        $message->sender_id = $currentUser->id;
        $message->receiver_id = $receiver->id;
        $message->content = $request->content ?? '';

        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('messages', 'public');
            $message->media_url = $mediaPath;
        }

        $message->save();

        return response()->json(['message' => 'Message sent successfully', 'data' => $message], 201);
    }

    // Get conversation with a specific user
    public function getConversation($userId)
    {
        $currentUser = Auth::user();
        $otherUser = User::findOrFail($userId);
        
        $messages = Message::where(function ($query) use ($currentUser, $otherUser) {
                $query->where('sender_id', $currentUser->id)
                    ->where('receiver_id', $otherUser->id);
            })
            ->orWhere(function ($query) use ($currentUser, $otherUser) {
                $query->where('sender_id', $otherUser->id)
                    ->where('receiver_id', $currentUser->id);
            })
            ->orderBy('created_at', 'asc')
            ->get();
            
        // Mark received messages as read
        Message::where('sender_id', $otherUser->id)
            ->where('receiver_id', $currentUser->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
            
        return response()->json(['messages' => $messages]);
    }

    // Get list of conversations
    public function getConversations()
    {
        $currentUser = Auth::user();
        
        // Get unique users that the current user has exchanged messages with
        $sentMessages = Message::where('sender_id', $currentUser->id)
            ->select('receiver_id as user_id')
            ->distinct();
            
        $receivedMessages = Message::where('receiver_id', $currentUser->id)
            ->select('sender_id as user_id')
            ->distinct();
            
        $userIds = $sentMessages->union($receivedMessages)->pluck('user_id');
        
        $conversations = [];
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            // Get the latest message
            $latestMessage = Message::where(function ($query) use ($currentUser, $userId) {
                    $query->where('sender_id', $currentUser->id)
                        ->where('receiver_id', $userId);
                })
                ->orWhere(function ($query) use ($currentUser, $userId) {
                    $query->where('sender_id', $userId)
                        ->where('receiver_id', $currentUser->id);
                })
                ->orderBy('created_at', 'desc')
                ->first();
                
            // Count unread messages
            $unreadCount = Message::where('sender_id', $userId)
                ->where('receiver_id', $currentUser->id)
                ->whereNull('read_at')
                ->count();
                
            $conversations[] = [
                'user' => $user,
                'latest_message' => $latestMessage,
                'unread_count' => $unreadCount
            ];
        }
        
        // Sort conversations by latest message date
        usort($conversations, function ($a, $b) {
            return $b['latest_message']->created_at <=> $a['latest_message']->created_at;
        });
        
        return response()->json(['conversations' => $conversations]);
    }

    // Delete a message
    public function deleteMessage($id)
    {
        $message = Message::findOrFail($id);
        $currentUser = Auth::user();
        
        // Check if the authenticated user is the sender or receiver of the message
        if ($currentUser->id !== $message->sender_id && $currentUser->id !== $message->receiver_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete media file if exists
        if ($message->media_url) {
            Storage::disk('public')->delete($message->media_url);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted successfully']);
    }
}
