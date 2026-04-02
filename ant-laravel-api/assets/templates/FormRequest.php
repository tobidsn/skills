<?php

declare(strict_types=1);

namespace App\Http\Requests\{Resource}\V1;

use App\Http\Payloads\{Resource}\{Payload};
use Illuminate\Foundation\Http\FormRequest;

final class {Request} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function payload(): {Payload}
    {
        return new {Payload}();
    }
}
