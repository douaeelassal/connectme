<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    // Add a comment to a post
    public function store(Request $request, $postId)
    {
        $post = Post::findOrFail($postId);
        
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = new Comment();
        $comment->user_id = Auth::id();
        $comment->post_id = $post->id;
        $comment->content = $request->content;
        $comment->save();

        // Load the user relationship for the response
        $comment->load('user');

        return response()->json(['message' => 'Comment added successfully', 'comment' => $comment], 201);
    }

    // Update a comment
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        
        // Check if the authenticated user is the owner of the comment
        if (Auth::id() !== $comment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment->content = $request->content;
        $comment->save();

        return response()->json(['message' => 'Comment updated successfully', 'comment' => $comment]);
    }

    // Delete a comment
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        
        // Check if the authenticated user is the owner of the comment or the post
        if (Auth::id() !== $comment->user_id && Auth::id() !== $comment->post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
