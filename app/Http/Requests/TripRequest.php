<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Trip::class);
    }

    public function rules(): array
    {
        $company = $this->user()->company_id;

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $company)],
            'origin_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $company)],
            'destination_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $company)],
            'truck_id' => ['required', 'integer', Rule::exists('trucks', 'id')->where('company_id', $company)->whereNull('archived_at')],
            'driver_id' => ['required', 'integer', Rule::exists('drivers', 'id')->where('company_id', $company)->whereNull('archived_at')],
            'date' => 'required|date_format:Y-m-d', 'origin' => 'required_without:origin_location_id|nullable|string|max:180', 'destination' => 'required_without:destination_location_id|nullable|string|max:180',
            'cargo' => 'required|string|max:180', 'tons' => 'required|numeric|min:0|max:9999', 'planned_km' => 'required|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:5000', 'historical' => 'sometimes|boolean',
            'actual_km' => 'nullable|required_if:historical,true|numeric|min:0|max:9999999'];
    }
}
