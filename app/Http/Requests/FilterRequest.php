<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => 'nullable|string|max:100', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', Rule::in(['programado', 'en_viaje', 'completado', 'anulado', 'available', 'maintenance', 'on_route'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $this->user()->company_id)],
            'truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $this->user()->company_id)],
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('company_id', $this->user()->company_id)],
            'archived' => ['nullable', Rule::in(['0', '1'])], 'page' => 'nullable|integer|min:1'];
    }
}
