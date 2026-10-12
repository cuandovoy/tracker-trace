<?php

namespace App\Http\Requests;

use App\Models\Truck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Truck::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plate' => mb_strtoupper(trim((string) $this->plate))]);
    }

    public function rules(): array
    {
        return ['plate' => ['required', 'string', 'max:32', Rule::unique('trucks')->where('company_id', $this->user()->company_id)->ignore($this->route('truck')?->id)],
            'brand' => 'required|string|max:120', 'model' => 'required|string|max:120', 'year' => 'required|integer|min:1950|max:'.(date('Y') + 1),
            'capacity_tons' => 'required|numeric|min:0.01|max:9999', 'odometer' => 'required|numeric|min:0|max:999999999',
            'fuel' => ['required', Rule::in(['diesel', 'gnc'])], 'maintenance' => 'required|boolean',
            'last_service' => 'nullable|date_format:Y-m-d', 'next_service' => 'nullable|date_format:Y-m-d|after_or_equal:last_service'];
    }
}
