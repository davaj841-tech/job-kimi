<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    protected $fillable = ['blog_post_id', 'user_id', 'content', 'status'];

    /** @return BelongsTo<BlogPost, $this> */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
