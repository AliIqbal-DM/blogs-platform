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
use RuntimeException;
use Throwable;

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
                'cover_url' => $post->cover_url,
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
        $post = new Post($request->postAttributes());
        $post->user()->associate($request->user());
        $this->saveWithCover($post, $request);

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
                'cover_url' => $post->cover_url,
            ],
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $post->fill($request->postAttributes());
        $this->saveWithCover($post, $request);

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

    /**
     * Stores a new cover upload (or clears the cover) and saves the post. Post's model events
     * delete the previous file; a new upload is deleted again when the save fails.
     */
    private function saveWithCover(Post $post, StorePostRequest $request): void
    {
        $newCover = $request->file('cover')?->store('covers', 'public');

        if ($newCover === false) {
            throw new RuntimeException('The cover image could not be stored.');
        }

        if ($newCover) {
            $post->cover_image = $newCover;
        } elseif ($request->boolean('remove_cover')) {
            $post->cover_image = null;
        }

        try {
            $post->save();
        } catch (Throwable $e) {
            Post::deleteCoverFile($newCover);

            throw $e;
        }
    }
}
