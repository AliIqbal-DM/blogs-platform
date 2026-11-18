<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validates the shape of the input. Publishing rules (e.g. scheduled needs a future publish_at)
 * are enforced by Post::applyStatusRules().
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
            'publish_at' => ['nullable', 'date'],
            'cover' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(static::COVER_MAX_KILOBYTES),
            ],
        ];
    }

    /**
     * The validated post attributes, without the cover upload fields.
     *
     * @return array<string, mixed>
     */
    public function postAttributes(): array
    {
        return $this->safe()->except(['cover', 'remove_cover']);
    }
}
