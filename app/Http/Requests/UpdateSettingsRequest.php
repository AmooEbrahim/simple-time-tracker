<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'calendar' => ['required', 'string', Rule::in(['gregorian', 'jalali', 'hijri'])],
            'week_start' => ['required', 'integer', 'between:0,6'],
            'weekend_days' => ['present', 'array'],
            'weekend_days.*' => ['integer', 'between:0,6', 'distinct'],
            'show_holidays' => ['required', 'boolean'],
            'holiday_country' => [
                'required',
                'string',
                Rule::in(array_keys(config('holidays.countries', []))),
            ],
        ];
    }
}
