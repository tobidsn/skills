<?php

declare(strict_types=1);

namespace App\Http\Controllers\{Resource}\V1;

use App\Actions\{Resource}\{Action};
use App\Http\Controllers\Controller;
use App\Http\Requests\{Resource}\V1\{Request};
use App\Http\Resources\API\Resources\{Model}Resource;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

final readonly class {Controller} extends Controller
{
    public function __construct(
        private {Action} $handler,
    ) {}

    public function __invoke({Request} $request): JsonResponse
    {
        $entity = $this->handler->handle(
            payload: $request->payload(),
        );

        return ApiResponse::created(
            {Model}Resource::make($entity),
            'Resource created'
        );
    }
}
