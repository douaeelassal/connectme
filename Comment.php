<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commentt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
        'content',
    ];

    // Comment belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Comment belongs to a post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
