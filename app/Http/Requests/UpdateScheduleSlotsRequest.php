<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots' => ['present', 'array'],
            'slots.*.week' => ['nullable', Rule::in(['A', 'B'])],
            'slots.*.dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'slots.*.startTime' => ['required', 'date_format:H:i'],
            'slots.*.endTime' => ['required', 'date_format:H:i'],
        ];
    }
}
