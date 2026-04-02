---
name: ant-laravel-api
description: Build production-grade Laravel REST APIs using Antikode architecture—stateless design, versioned invokable controllers, Form Request DTOs, Action classes, Laravel Sanctum, App\Services\ApiResponse, and API Resources. Aligns with .cursor/rules/api-development.mdc. Triggers on "build a Laravel API", "Antikode API", "create Laravel endpoints", "add API authentication", "review Laravel API code", "refactor Laravel API", or "improve Laravel code quality".
---

# Laravel API - Antikode Architecture

Build Laravel REST APIs with clean, stateless, resource-scoped architecture. **Project contract:** follow `.cursor/rules/api-development.mdc` for `ApiResponse`, Sanctum, Form Requests, API Resources, pagination, and error handling.

## Quick Start

When user requests a Laravel API in this codebase, follow this workflow:

1. **Understand requirements** - Resources, operations, authentication.
2. **Initialize structure** - Routing, version groups, align with existing `routes/api.php` patterns.
3. **Build first resource** - Complete CRUD to establish pattern.
4. **Add authentication** - Laravel Sanctum (`auth:sanctum`), tokens via `createToken()`.
5. **Iterate** - Reuse Actions, Form Requests, Resources, `ApiResponse`.

## Core Architecture Principles

Read `references/architecture.md` for details. Key principles:

1. **Stateless by design** - Explicit data flow, no hidden dependencies.
2. **Boundary-first** - HTTP (Form Requests + Resources + `ApiResponse`), business logic (Actions/Services), data (Eloquent).
3. **Resource-scoped** - Routes and controllers organized by resource.
4. **Version discipline** - Namespace-based versioning; optional HTTP Sunset for deprecations.

## Project API Contract (AntiCMS)

Always use the centralized response helper and API Resources:

```php
use App\Http\Resources\API\Resources\TaskResource;
use App\Services\ApiResponse;

return ApiResponse::json(TaskResource::make($task));
return ApiResponse::created(TaskResource::make($task), 'Task created');
return ApiResponse::paginate(TaskResource::collection($tasks), [], 'Tasks retrieved successfully');
return ApiResponse::cursorPaginate(TaskResource::collection($items), [], 'OK');
return ApiResponse::messageOnly('Not found', 404);
return ApiResponse::validationError('Validation failed', $errors);
return ApiResponse::deleted('Task deleted');
```

Response envelope: `success`, `statusCode`, `message`, `data`, `meta` (see `api-development.mdc`).

## Code Quality Standards

See `references/code-quality.md`. Summary:

1. **Preserve functionality** - Refactors change how, not what.
2. **Explicit over implicit** - Clear code over clever shortcuts.
3. **Type declarations** - `declare(strict_types=1);`, return and parameter types.
4. **Avoid nested ternaries** - Prefer `match`.
5. **PSR-12** - Laravel Pint.

## Project Structure (illustrative)

```
routes/api.php                 # Or routes/api/routes.php + resource files

app/Http/
  Controllers/Api/ or {Resource}/V1/
    StoreController.php        # Invokable
  Requests/Api/ or {Resource}/V1/
    StoreTaskRequest.php       # rules() + payload() → DTO when useful
  Resources/API/Resources/
    TaskResource.php
app/Actions/{Resource}/
  CreateTask.php
app/Services/
  ApiResponse                  # Central JSON envelope (do not bypass)
app/Models/
  Task.php
```

## Building a New Resource Endpoint

### Step 1: Model

Prefer ULIDs when the project already uses them. Keep models thin.

### Step 2: Routes

Use `Route::middleware(['auth:sanctum'])->group(...)` for protected routes. Match existing prefix/version conventions in `routes/api.php`.

### Step 3: DTO (optional Payload)

`app/Http/Payloads/{Resource}/StoreTaskPayload.php` with `toArray()` when validation maps to a typed object.

### Step 4: Form Request

`app/Http/Requests/Api/` or `{Resource}/V1/` — `rules()`, optional `messages()`, optional `payload()` returning a DTO.

### Step 5: Action

`app/Actions/{Resource}/CreateTask.php` — single `handle()` method.

### Step 6: API Resource

Transform output in `TaskResource::toArray()`; never return raw models from controllers.

### Step 7: Invokable controller

Inject Action, validate via Form Request, return `ApiResponse::json|created|paginate` wrapping Resources.

Example:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks\V1;

use App\Actions\Tasks\CreateTask;
use App\Http\Requests\Tasks\V1\StoreTaskRequest;
use App\Http\Resources\API\Resources\TaskResource;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

final readonly class StoreController
{
    public function __construct(
        private CreateTask $createTask,
    ) {}

    public function __invoke(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->createTask->handle($request->payload());

        return ApiResponse::created(
            TaskResource::make($task),
            'Task created'
        );
    }
}
```

## Pagination

Use `ApiResponse::paginate` with `SomeResource::collection($paginator)` where `$paginator` comes from `->paginate()`. For `cursorPaginate()`, use `ApiResponse::cursorPaginate`. For custom top-level keys (e.g. inbox categories), `ApiResponse::json` with an explicit `pagination` array is acceptable per `api-development.mdc`.

## Authentication

Laravel Sanctum: `auth:sanctum` middleware; issue tokens with `$user->createToken($request->device_id)->plainTextToken` (or project-equivalent). Do not assume JWT/`auth:api` unless the repo explicitly adds it.

## Essential setup

`Model::shouldBeStrict()` in `AppServiceProvider::boot()` when the project uses it. Register optional Sunset middleware per versioning policy.

## Anti-Patterns to Avoid

- Bypassing `ApiResponse` with `response()->json()` for app APIs.
- Business logic bloating models.
- Multiple unrelated actions in one invokable controller.
- Skipping API Resources for public JSON.
- Breaking response shape without versioning.
- Nested ternaries and missing types.

## References

- **`.cursor/rules/api-development.mdc`** — canonical AntiCMS API rules (`ApiResponse`, Sanctum, Resources, pagination, tests).
- **`references/architecture.md`** — Antikode structural patterns.
- **`references/code-examples.md`** — Examples using `ApiResponse` and Resources.
- **`references/code-quality.md`** — Refactoring and PSR-12.

## Templates

`assets/templates/` — scaffolding aligned with `api-development.mdc` (`ApiResponse`, API Resources):

- `Controller.php` — invokable controller, `ApiResponse::created` + `{Model}Resource`
- `FormRequest.php` — `rules()` + `payload()` → DTO
- `Payload.php` — readonly DTO + `toArray()`
- `Action.php` — `handle({Payload}): {Model}`
- `Model.php` — ULID model stub
- `Resource.php` — `App\Http\Resources\API\Resources\*Resource`
