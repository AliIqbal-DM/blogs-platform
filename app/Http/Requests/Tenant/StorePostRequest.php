<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PostStatus;
use App\Models\Post;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validates post input, including the scheduling rule: a publish_at that is being set or changed
 * must be in the future. Other publishing rules are enforced by Post::applyStatusRules().
 */
class StorePostRequest extends FormRequest
{
    public const COVER_MAX_KILOBYTES = 4 * 1024;

    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'publish_at' => [
                'bail',
                Rule::requiredIf(fn () => $this->isScheduling()),
                'nullable',
                'date',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->isScheduling() && ! $this->keepsStoredPublishAt() && ! Date::parse($value)->isFuture()) {
                        $fail('Choose a publish date and time in the future to schedule this post.');
                    }
                },
            ],
            'cover' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(static::COVER_MAX_KILOBYTES),
            ],
        ];
    }

    /**
     * The validated post attributes, without the cover upload fields. An unchanged publish_at is left out,
     * so the stored value (which may have seconds the form does not send) stays exactly as it was.
     *
     * @return array<string, mixed>
     */
    public function postAttributes(): array
    {
        $except = ['cover', 'remove_cover'];

        if ($this->keepsStoredPublishAt()) {
            $except[] = 'publish_at';
        }

        return $this->safe()->except($except);
    }

    /**
     * The post being edited, or null when creating one.
     */
    protected function existingPost(): ?Post
    {
        return null;
    }

    private function isScheduling(): bool
    {
        return $this->input('status') === PostStatus::Scheduled->value;
    }

    /**
     * Whether a post that is already scheduled stays scheduled for the same minute. Such a post may be
     * overdue (its time passed before the publish job ran) and must still be editable.
     */
    private function keepsStoredPublishAt(): bool
    {
        $post = $this->existingPost();
        $submitted = $this->input('publish_at');

        return $this->isScheduling()
            && $post?->status === PostStatus::Scheduled
            && $post->publish_at !== null
            && is_string($submitted)
            && strtotime($submitted) !== false
            && static::sameMinute(Date::parse($submitted), $post->publish_at);
    }

    /**
     * Compared in UTC to the minute, so offsets, milliseconds and dropped seconds do not count as a change.
     */
    private static function sameMinute(DateTimeInterface $a, DateTimeInterface $b): bool
    {
        return Date::instance($a)->utc()->startOfMinute()->equalTo(Date::instance($b)->utc()->startOfMinute());
    }
}
