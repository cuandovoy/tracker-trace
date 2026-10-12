<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Trip::class);
    }

    public function rules(): array
    {
        $location = $this->route('kind') === 'destinos';

        return [
            'name' => 'required|string|max:180', 'address' => ($location ? 'required' : 'nullable').'|string|max:180',
            'contact_name' => 'nullable|string|max:180', 'phone' => 'nullable|string|max:60', 'notes' => 'nullable|string|max:3000',
            ...($location ? ['customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $this->user()->company_id)]]
                : ['tax_id' => 'nullable|string|max:60', 'email' => 'nullable|email|max:255']),
        ];
    }
}
