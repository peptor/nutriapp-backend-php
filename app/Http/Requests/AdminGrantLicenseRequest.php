<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminGrantLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endsAt' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            // Si s'indica l'import cobrat (IVA inclòs, en cèntims), s'emet la factura de la quota com si hagués pagat.
            'amountCents' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'billingPeriod' => ['required_with:amountCents', 'nullable', 'in:MONTHLY,ANNUAL'],
        ];
    }

    public function messages(): array
    {
        return [
            'endsAt.required' => 'Cal indicar fins a quina data té la llicència EvoPro',
            'endsAt.date_format' => 'La data no és vàlida',
            'endsAt.after_or_equal' => 'La data de fi no pot ser anterior a avui',
        ];
    }
}
