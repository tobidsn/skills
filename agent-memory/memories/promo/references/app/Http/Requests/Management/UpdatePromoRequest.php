<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePromoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('promo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:promo_categories,id'],
            'points' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'string'],
            'image_detail' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'tnc' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'url' => ['nullable', 'url'],
            'reward_id' => ['nullable', 'exists:rewards,id'],
            'is_reward' => ['nullable', 'boolean'],
            'max_redeem' => ['nullable', 'integer', 'min:0'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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
            'title.required' => 'Please enter a title.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'category_id.exists' => 'The selected category is invalid.',
            'points.required' => 'Points are required for burn points rules.',
            'points.integer' => 'Points must be a valid number.',
            'points.min' => 'Points must be at least 0.',
            'is_active.required' => 'Please select the active status.',
            'description.max' => 'The description may not be greater than 255 characters.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
