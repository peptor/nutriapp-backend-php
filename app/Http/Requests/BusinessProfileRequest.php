<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Arriba com a multipart/form-data (per anar acompanyat opcionalment del logo), per això
// els camps buits arriben com a string buit i cal normalitzar-los a null (mirall d'emptyToNull a Node).
class BusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['companyName', 'taxId', 'address', 'postalCode', 'city', 'phone'];
        $normalized = [];
        foreach ($fields as $field) {
            if ($this->input($field) === '') {
                $normalized[$field] = null;
            }
        }
        if ($normalized) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'companyName' => ['nullable', 'string', 'max:150'],
            'taxId' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'postalCode' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
