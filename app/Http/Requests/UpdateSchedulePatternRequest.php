<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchedulePatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['WEEKLY', 'BIWEEKLY'])],
            'cycleStartDate' => ['nullable', 'date', 'required_if:mode,BIWEEKLY'],
            'cycleStartWeek' => ['nullable', Rule::in(['A', 'B']), 'required_if:mode,BIWEEKLY'],
        ];
    }
}
