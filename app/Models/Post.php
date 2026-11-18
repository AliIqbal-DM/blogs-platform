<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'status',
        'publish_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->slug ??= static::uniqueSlug($post->title);
        });

        static::saving(fn (Post $post) => $post->applyStatusRules());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /**
     * Every publishing rule lives here, so it applies to forms, factories and seeders alike.
     *
     * @throws ValidationException
     */
    public function applyStatusRules(): void
    {
        if ($this->status === PostStatus::Draft) {
            $this->publish_at = null;
        }

        if ($this->status === PostStatus::Scheduled && ! $this->publish_at?->isFuture()) {
            throw ValidationException::withMessages([
                'publish_at' => 'Choose a publish date and time in the future to schedule this post.',
            ]);
        }

        if ($this->status === PostStatus::Published) {
            $this->published_at ??= now();
        }
    }

    /**
     * Store publish_at in UTC, whatever offset the client sent.
     */
    protected function publishAt(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null
            ? null
            : Date::parse($value)->utc()->format($this->getDateFormat()));
    }

    /**
     * A slug from the title, suffixed with -2, -3, ... when it is already taken on this blog.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;

        for ($suffix = 2; static::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
