<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of a tenant blog. Only published posts are ever visible here.
 */
class BlogController extends Controller
{
    public function home(): Response
    {
        $posts = Post::published()
            ->with('user:id,name')
            ->latest('published_at')
            ->latest('id')
            ->paginate(10)
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => Str::limit($post->body, 240),
                'author' => $post->user?->name,
                'published_at' => $post->published_at?->toIso8601String(),
            ]);

        return Inertia::render('Blog/Home', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): Response
    {
        $post = Post::published()->with('user:id,name')->where('slug', $slug)->firstOrFail();

        return Inertia::render('Blog/Show', [
            'post' => [
                'title' => $post->title,
                'body' => $post->body,
                'author' => $post->user?->name,
                'published_at' => $post->published_at?->toIso8601String(),
            ],
        ]);
    }
}
