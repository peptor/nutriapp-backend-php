<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patientId' => ['required', 'uuid'],
            'templateId' => ['required', 'uuid'],
            'startDate' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'customNotes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['startDate.regex' => 'Data en format YYYY-MM-DD'];
    }
}
