<?php

namespace App\Http\Requests\Tenant;

use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePostRequest extends StorePostRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    protected function existingPost(): ?Post
    {
        return $this->route('post');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }
}
