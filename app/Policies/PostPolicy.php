<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Writers share their blog: any writer of a tenant may manage every post in it.
 * Users and posts live in the same tenant database, so the user is always a writer of this blog.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return true;
    }

    public function delete(User $user, Post $post): bool
    {
        return true;
    }
}
