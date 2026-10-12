<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-platform') || $this->user()->can('manage-company');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:160', 'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($this->route('member')?->id)],
            'role' => ['required', Rule::in($this->user()->isSuper() ? ['admin'] : ['admin', 'operator'])], 'active' => 'required|boolean'];
    }
}
