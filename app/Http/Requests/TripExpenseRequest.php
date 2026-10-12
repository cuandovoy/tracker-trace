<?php

namespace App\Http\Requests;

use App\Models\TripExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return ['date' => 'required|date_format:Y-m-d', 'category' => ['required', Rule::in(array_keys(TripExpense::CATEGORIES))],
            'description' => 'required|string|max:250', 'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999.99',
            'currency' => ['required', Rule::in(['UYU', 'USD'])]];
    }
}
