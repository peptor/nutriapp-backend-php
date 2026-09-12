<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchRecordsRequest extends FormRequest
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
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.fieldName' => ['required', 'string', 'min:1', 'max:60'],
            'entries.*.value' => ['present'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
