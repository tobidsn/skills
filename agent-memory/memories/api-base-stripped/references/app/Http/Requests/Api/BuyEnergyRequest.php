<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class BuyEnergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'energy_id' => ['required', 'integer', 'exists:energies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'energy_id.required' => 'Energy ID is required',
            'energy_id.exists' => 'Energy not found',
        ];
    }
}
