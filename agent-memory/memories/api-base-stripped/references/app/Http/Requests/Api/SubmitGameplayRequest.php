<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitGameplayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'main_dish_option_id' => ['nullable', 'uuid', 'exists:customer_menu_options,id'],
            'side_dish_option_id' => ['nullable', 'uuid', 'exists:customer_menu_options,id'],
            'drink_option_id' => ['nullable', 'uuid', 'exists:customer_menu_options,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $selectedCount = collect([
                $this->main_dish_option_id,
                $this->side_dish_option_id,
                $this->drink_option_id
            ])->filter()->count();

            if ($selectedCount < 2) {
                $validator->errors()->add('options', 'At least two menu options must be selected');
            }
        });
    }
}

