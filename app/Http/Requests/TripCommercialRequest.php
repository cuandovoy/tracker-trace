<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripCommercialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return ['value_amount' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99', 'collected' => 'required|boolean', 'viaticos_qty' => 'required|integer|min:0|max:999'];
    }
}
