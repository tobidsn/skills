---
name: ant-laravel-specialist
description: Laravel specialist orchestrator. Use for any Laravel task — it picks the right ant-* skill automatically. Triggers on: build feature, create API, refactor, fix query, add job/queue, write Livewire component, add tests, general Laravel/PHP work.
license: MIT
metadata:
  version: "1.4.0"
  domain: backend
  triggers: Laravel, Eloquent, PHP framework, Laravel API, Artisan, Blade templates, Laravel queues, Livewire, Laravel testing, Sanctum, Horizon, Antikode, ant- skills
  role: orchestrator
  scope: implementation
  output-format: code
  related-skills: ant-laravel-api, ant-laravel-eloquent, ant-laravel-design-patern, ant-important-code, ant-dedoc-scramble
---

# Ant Laravel Specialist

Orchestrator for Antikode-style Laravel work. Prefer loading the focused `ant-*` skill that matches the task; use this skill’s workflow, references, and templates for cross-cutting or general Laravel work.

## Orchestration (ant- skills)

| Task focus | Load first |
|------------|------------|
| REST APIs, ApiResponse, Form Requests, Sanctum | `ant-laravel-api` |
| Eloquent queries, N+1, models, indexes | `ant-laravel-eloquent` |
| Design patterns, extensibility, pipeline/actions | `ant-laravel-design-patern` |
| Minimal scope, no unsolicited code, thin controllers | `ant-important-code` |
| Scramble, OpenAPI, `/docs/api` | `ant-dedoc-scramble` |

When several areas apply, read the relevant ant- skills in parallel or sequence, then implement using this skill’s checkpoints and references.

**Do not duplicate:** model/query guidance lives in `ant-laravel-eloquent`; REST APIs, routes, Form Requests, Resources, and `ApiResponse` in `ant-laravel-api`. This skill’s references cover queues, Livewire, and testing mechanics only.

## Core Workflow

1. Identify which `ant-*` skill owns the task (use the Orchestration table above)
2. Load that skill and follow its rules — do not duplicate its patterns here
3. For cross-cutting work: schema → models → services/actions → API layer → tests
4. Run `php artisan migrate:status`, `route:list --path=api`, and `test --coverage` before marking done

## Reference Guide

| Topic | Where |
|-------|--------|
| Eloquent, N+1, indexes, pagination | `ant-laravel-eloquent` |
| APIs, Sanctum, Form Requests, Resources, `ApiResponse` | `ant-laravel-api` |
| Queues, Horizon, jobs | `references/queues.md` |
| Livewire | `references/livewire.md` |
| Factories, fakes, Pest syntax, CLI | `references/testing.md` (API assertions → `ant-laravel-api` + `api-testing.mdc`) |

## Rules

- PHP 8.2+, explicit types, PSR-12 (Pint)
- Delegate to the matching `ant-*` skill — never re-implement its patterns here
- No business logic in controllers; no skipped validation; no ignored failed jobs
- Queue long-running work; always write tests (`>85%` coverage)

## Code Templates

Minimal starters only; extend using ant- skills for models and API layers.

### Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

### Queued Job

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PublishPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly Post $post,
    ) {}

    public function handle(): void
    {
        $this->post->update([
            'status'       => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('PublishPost failed', ['post' => $this->post->id, 'error' => $e->getMessage()]);
    }
}
```

## Validation Checkpoints

Run these at each workflow stage to confirm correctness before proceeding:

| Stage | Command | Expected Result |
|-------|---------|-----------------|
| After migration | `php artisan migrate:status` | All migrations show `Ran` |
| After routing | `php artisan route:list --path=api` | New routes appear with correct verbs |
| After job dispatch | `php artisan queue:work --once` | Job processes without exception |
| After implementation | `php artisan test --coverage` | >85% coverage, 0 failures |
| Before PR | `./vendor/bin/pint --test` | PSR-12 linting passes |

