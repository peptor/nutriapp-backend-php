<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['ACTIVE', 'COMPLETED', 'CANCELLED'])],
            'evolutionRating' => ['nullable', Rule::in(['POSITIVE', 'STABLE', 'NEGATIVE'])],
        ];
    }
}
