<?php

namespace App\Http\Requests\Tenant;

class UpdatePostRequest extends StorePostRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }
}
