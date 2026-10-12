<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-platform') || $this->user()->can('manage-company');
    }

    public function rules(): array
    {
        $rules = ['name' => 'required|string|max:160', 'tax_id' => 'nullable|string|max:100', 'address' => 'nullable|string|max:240', 'phone' => 'nullable|string|max:60', 'timezone' => 'required|timezone'];
        if ($this->user()->isSuper()) {
            $rules['active'] = 'required|boolean';
            if ($this->isMethod('post')) {
                $rules['admin_name'] = 'required|string|max:160';
                $rules['admin_email'] = 'required|email|max:180|unique:users,email';
            }
        }

        return $rules;
    }
}
