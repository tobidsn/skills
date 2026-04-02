# Code Examples

Examples for Antikode Laravel API architecture. **Authoritative project rules:** `.cursor/rules/api-development.mdc` (`ApiResponse`, Sanctum, API Resources, pagination).

## Model with ULID

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Task extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'project_id',
        'assignee_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
```

## API Resource

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'project_id' => $this->project_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

## Form Request with DTO

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks\V1;

use App\Http\Payloads\Tasks\StoreTaskPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => [
                'required',
                'string',
                Rule::in(['pending', 'in_progress', 'completed']),
            ],
            'priority' => [
                'required',
                'string',
                Rule::in(['low', 'medium', 'high']),
            ],
            'due_date' => ['nullable', 'date', 'after:today'],
            'project_id' => ['required', 'string', 'exists:projects,id'],
            'assignee_id' => ['nullable', 'string', 'exists:users,id'],
        ];
    }

    public function payload(): StoreTaskPayload
    {
        return new StoreTaskPayload(
            title: $this->string('title')->toString(),
            description: $this->string('description')->toString(),
            status: $this->string('status')->toString(),
            priority: $this->string('priority')->toString(),
            dueDate: $this->date('due_date'),
            projectId: $this->string('project_id')->toString(),
            assigneeId: $this->string('assignee_id')->toString(),
        );
    }
}
```

## DTO (Data Transfer Object)

```php
<?php

declare(strict_types=1);

namespace App\Http\Payloads\Tasks;

use DateTimeInterface;

final readonly class StoreTaskPayload
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $status,
        public string $priority,
        public ?DateTimeInterface $dueDate,
        public string $projectId,
        public ?string $assigneeId,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'project_id' => $this->projectId,
            'assignee_id' => $this->assigneeId,
        ];
    }
}
```

## Action Class

```php
<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Http\Payloads\Tasks\StoreTaskPayload;
use App\Models\Task;

final readonly class CreateTask
{
    public function handle(StoreTaskPayload $payload): Task
    {
        return Task::create($payload->toArray());
    }
}
```

## Invokable Controller

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
        $task = $this->createTask->handle(
            payload: $request->payload(),
        );

        return ApiResponse::created(
            TaskResource::make($task),
            'Task created'
        );
    }
}
```

## ApiResponse (project standard)

Use `App\Services\ApiResponse` instead of custom `Responsable` wrappers. See `.cursor/rules/api-development.mdc` for `json`, `created`, `paginate`, `cursorPaginate`, `messageOnly`, `validationError`, and `deleted`.

## Routes

### Main API Routes File

```php
<?php
// routes/api/routes.php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__ . '/tasks.php';
    require __DIR__ . '/projects.php';
});

Route::prefix('v2')->group(function () {
    require __DIR__ . '/tasks.php';
});
```

### Resource Routes File

```php
<?php
// routes/api/tasks.php

use App\Http\Controllers\Tasks\V1;
use Illuminate\Support\Facades\Route;

// V1 Routes
Route::middleware(['auth:sanctum', 'http.sunset:2025-12-31'])->group(function () {
    Route::get('/tasks', V1\IndexController::class);
    Route::post('/tasks', V1\StoreController::class);
    Route::get('/tasks/{task}', V1\ShowController::class);
    Route::patch('/tasks/{task}', V1\UpdateController::class);
    Route::delete('/tasks/{task}', V1\DestroyController::class);
});

// V2 Routes (when needed)
// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::get('/tasks', \App\Http\Controllers\Tasks\V2\IndexController::class);
//     Route::post('/tasks', \App\Http\Controllers\Tasks\V2\StoreController::class);
// });
```

## Index Controller with Query Builder

```php
<?php

namespace App\Http\Controllers\Tasks\V1;

use App\Http\Resources\API\Resources\TaskResource;
use App\Models\Task;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

final class IndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $perPage = (int) ($request->load ?? 10);

        $tasks = QueryBuilder::for(Task::class)
            ->allowedFilters([
                'status',
                'priority',
                'project_id',
                'assignee_id',
            ])
            ->allowedSorts([
                'created_at',
                'due_date',
                'priority',
            ])
            ->allowedIncludes([
                'project',
                'assignee',
            ])
            ->paginate($perPage);

        return ApiResponse::paginate(
            TaskResource::collection($tasks),
            [],
            'Tasks retrieved successfully'
        );
    }
}
```

## Show Controller

```php
<?php

namespace App\Http\Controllers\Tasks\V1;

use App\Http\Resources\API\Resources\TaskResource;
use App\Models\Task;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;

final class ShowController
{
    public function __invoke(string $task): JsonResponse
    {
        $task = QueryBuilder::for(Task::where('id', $task))
            ->allowedIncludes([
                'project',
                'assignee',
            ])
            ->firstOrFail();

        return ApiResponse::json(TaskResource::make($task));
    }
}
```

## Update Controller

```php
<?php

namespace App\Http\Controllers\Tasks\V1;

use App\Actions\Tasks\UpdateTask;
use App\Http\Requests\Tasks\V1\UpdateTaskRequest;
use App\Http\Resources\API\Resources\TaskResource;
use App\Models\Task;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

final readonly class UpdateController
{
    public function __construct(
        private UpdateTask $updateTask,
    ) {
    }

    public function __invoke(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $updatedTask = $this->updateTask->handle(
            task: $task,
            payload: $request->payload(),
        );

        return ApiResponse::json(
            TaskResource::make($updatedTask),
            'Task updated'
        );
    }
}
```

## Update Action

```php
<?php

namespace App\Actions\Tasks;

use App\Http\Payloads\Tasks\UpdateTaskPayload;
use App\Models\Task;

final readonly class UpdateTask
{
    public function handle(Task $task, UpdateTaskPayload $payload): Task
    {
        $task->update($payload->toArray());

        return $task->fresh();
    }
}
```

## Destroy Controller

```php
<?php

namespace App\Http\Controllers\Tasks\V1;

use App\Models\Task;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DestroyController
{
    public function __invoke(Task $task): JsonResponse
    {
        $task->delete();

        return ApiResponse::deleted('Task deleted');
    }
}
```

## HTTP Sunset Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpSunset
{
    public function handle(Request $request, Closure $next, string $date): Response
    {
        $response = $next($request);

        $response->headers->set('Sunset', $date);
        $response->headers->set(
            'Deprecation',
            'This API version is deprecated and will be removed on ' . $date
        );

        return $response;
    }
}
```

## AppServiceProvider Setup

```php
<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prevent lazy loading and N+1 queries
        Model::shouldBeStrict();
    }
}
```

## API errors

Handle expected failures in controllers with `ApiResponse::messageOnly()`, `ApiResponse::validationError()`, or `try` / `catch` patterns from `.cursor/rules/api-development.mdc`. Configure unhandled exceptions in the app’s exception pipeline (`bootstrap/app.php` / handler) so production JSON does not leak sensitive details.

## Service Class Example (when needed)

```php
<?php

namespace App\Services;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\AssignTask;
use App\Actions\Tasks\NotifyAssignee;
use App\Http\Payloads\Tasks\StoreTaskPayload;
use App\Models\Task;

final readonly class TaskService
{
    public function __construct(
        private CreateTask $createTask,
        private AssignTask $assignTask,
        private NotifyAssignee $notifyAssignee,
    ) {
    }

    /**
     * Create a task and handle all related side effects
     */
    public function createAndAssign(StoreTaskPayload $payload, string $assigneeId): Task
    {
        $task = $this->createTask->handle($payload);

        $task = $this->assignTask->handle($task, $assigneeId);

        $this->notifyAssignee->handle($task);

        return $task;
    }
}
```