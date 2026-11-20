<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class DriverSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['month' => ['nullable', 'date_format:Y-m', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']];
    }

    /**
     * The requested month, or the current month in the company timezone.
     */
    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return $month
            ? CarbonImmutable::createFromFormat('!Y-m', $month)
            : CarbonImmutable::now($this->user()->company->timezone)->startOfMonth();
    }
}
