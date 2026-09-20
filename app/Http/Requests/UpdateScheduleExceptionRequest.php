<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['FESTIU', 'ABSENCIA', 'VACANCES', 'HORARI_ESPECIAL'])],
            'startTime' => ['required_if:type,HORARI_ESPECIAL', 'nullable', 'date_format:H:i'],
            'endTime' => ['required_if:type,HORARI_ESPECIAL', 'nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
