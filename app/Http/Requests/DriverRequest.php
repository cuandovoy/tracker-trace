<?php

namespace App\Http\Requests;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Driver::class);
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:160', 'license' => 'required|string|max:120', 'category' => 'required|string|max:60', 'phone' => 'nullable|string|max:60', 'hired_on' => 'required|date_format:Y-m-d|before_or_equal:today'];
    }
}
