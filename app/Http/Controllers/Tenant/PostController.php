<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePostRequest;
use App\Http\Requests\Tenant\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Post::class);

        $posts = Post::with('user:id,name')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'publish_at' => $post->publish_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
                'author' => $post->user?->name,
                'updated_at' => $post->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Tenant/Posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Post::class);

        return Inertia::render('Tenant/Posts/Create');
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = new Post($request->validated());
        $post->user()->associate($request->user());
        $post->save();

        return redirect()
            ->route('tenant.dashboard.posts.index')
            ->with('success', "Post \"{$post->title}\" saved.");
    }

    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        return Inertia::render('Tenant/Posts/Edit', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'body' => $post->body,
                'status' => $post->status,
                'publish_at' => $post->publish_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $post->update($request->validated());

        return redirect()
            ->route('tenant.dashboard.posts.index')
            ->with('success', "Post \"{$post->title}\" updated.");
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()
            ->route('tenant.dashboard.posts.index')
            ->with('success', "Post \"{$post->title}\" deleted.");
    }
}
