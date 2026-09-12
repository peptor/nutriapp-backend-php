<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignmentId' => ['required', 'uuid'],
            'recordDate' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'fieldName' => ['required', 'string', 'min:1', 'max:60'],
            'value' => ['present'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
