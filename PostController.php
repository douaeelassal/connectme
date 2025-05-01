<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    // Get all posts (feed)
    public function index()
    {
        $user = Auth::user();
        
        // Get IDs of the user's friends
        $friendIds = $user->friends()->pluck('users.id')->toArray();
        
        // Include user's own posts
        $friendIds[] = $user->id;
        
        // Get posts from friends and user, ordered by created_at
        $posts = Post::whereIn('user_id', $friendIds)
            ->with(['user', 'comments.user', 'likes'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json(['posts' => $posts]);
    }

    // Create a new post
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4|max:10240',
            'media_type' => 'required|in:image,video,text',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $post = new Post();
        $post->user_id = Auth::id();
        $post->content = $request->content;
        $post->media_type = $request->media_type;

        if ($request->hasFile('media') && $request->media_type != 'text') {
            $mediaPath = $request->file('media')->store('posts', 'public');
            $post->media_url = $mediaPath;
        }

        $post->save();

        return response()->json(['message' => 'Post created successfully', 'post' => $post], 201);
    }

    // Get a specific post
    public function show($id)
    {
        $post = Post::with(['user', 'comments.user', 'likes.user'])
            ->findOrFail($id);
            
        return response()->json(['post' => $post]);
    }

    // Update a post
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        // Check if the authenticated user is the owner of the post
        if (Auth::id() !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('content')) {
            $post->content = $request->content;
        }

        $post->save();

        return response()->json(['message' => 'Post updated successfully', 'post' => $post]);
    }

    // Delete a post
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        
        // Check if the authenticated user is the owner of the post
        if (Auth::id() !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete media file if exists
        if ($post->media_url) {
            Storage::disk('public')->delete($post->media_url);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    // Like a post
    public function like($id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();
        
        // Check if already liked
        $existingLike = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();
            
        if ($existingLike) {
            return response()->json(['message' => 'Post already liked'], 422);
        }
        
        // Create new like
        $like = new Like();
        $like->user_id = $user->id;
        $like->post_id = $post->id;
        $like->save();
        
        return response()->json(['message' => 'Post liked successfully']);
    }

    // Unlike a post
    public function unlike($id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();
        
        $like = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();
            
        if (!$like) {
            return response()->json(['message' => 'Post not liked'], 422);
        }
        
        $like->delete();
        
        return response()->json(['message' => 'Post unliked successfully']);
    }
}
