<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePromoCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', \App\Models\PromoCategory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a category name.',
            'name.max' => 'The category name may not be greater than 255 characters.',
            'name.unique' => 'This category name already exists.',
            'sort_order.integer' => 'The sort order must be a valid number.',
            'sort_order.min' => 'The sort order must be at least 0.',
        ];
    }
}
