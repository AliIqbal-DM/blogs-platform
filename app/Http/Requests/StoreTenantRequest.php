<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    /**
     * MySQL database names are limited to 64 characters and the id gets a prefix.
     */
    public const MAX_ID_LENGTH = 50;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) {
                    $id = static::tenantIdFrom((string) $value);

                    if ($id === '') {
                        $fail('The name must contain at least one letter or number.');
                    } elseif (Tenant::whereKey($id)->exists()) {
                        $fail("A blog with the id \"{$id}\" already exists. Choose a different name.");
                    }
                },
            ],
            'domain' => [
                'required',
                'string',
                'max:253',
                'lowercase',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::notIn(config('tenancy.central_domains')),
                Rule::unique('domains', 'domain'),
            ],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'The domain must be a valid hostname, for example acme.blogs.test.',
            'domain.not_in' => 'The domain cannot be a central domain.',
            'brand_color.regex' => 'The brand color must be a hex color like #4f46e5.',
        ];
    }

    public function tenantId(): string
    {
        return static::tenantIdFrom($this->validated('name'));
    }

    public static function tenantIdFrom(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9_]/', '', Str::slug($name, '_'));

        return trim(Str::limit($slug, static::MAX_ID_LENGTH, ''), '_');
    }
}
