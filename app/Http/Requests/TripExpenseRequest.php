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
        return ['date' => 'required|date_format:Y-m-d', 'category' => ['required', Rule::in($this->allowedCategories())],
            'description' => 'required|string|max:250', 'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999.99',
            'currency' => ['required', Rule::in(['UYU', 'USD'])]];
    }

    /**
     * Legacy "viaticos" is accepted only when editing a row that already has it.
     *
     * @return list<string>
     */
    private function allowedCategories(): array
    {
        $expense = $this->route('expense');
        $isLegacy = $expense instanceof TripExpense && $expense->category === 'viaticos';

        return array_keys($isLegacy ? TripExpense::LABELS : TripExpense::CATEGORIES);
    }
}
