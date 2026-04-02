# Testing

**API endpoints (JSON, Sanctum, `ApiResponse` envelope):** use `ant-laravel-api` and `.cursor/rules/api-testing.mdc`—assert `success`, `statusCode`, `message`, `data`, and `meta` as in this project, not generic `{ data: [...] }` only.

## Unit tests

```php
namespace Tests\Unit;

use Tests\TestCase;

final class PostServiceTest extends TestCase
{
    public function test_generates_slug(): void
    {
        $service = new PostService();
        $this->assertSame('hello-world', $service->generateSlug('Hello World'));
    }
}
```

## Pest (syntax)

```php
<?php

use App\Models\User;

it('does something', function (): void {
    $user = User::factory()->create();
    expect($user->email)->not->toBeEmpty();
});

it('validates input', function (string $value, bool $ok) {
    // ...
})->with([['bad', false], ['good-value', true]]);
```

## Factories

```php
public function definition(): array
{
    return [
        'title' => fake()->sentence(),
        'user_id' => User::factory(),
    ];
}

public function published(): static
{
    return $this->state(fn (array $a) => ['published_at' => now()]);
}
```

## Fakes (HTTP, events, queues, notifications, storage)

```php
Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);
Http::assertSent(fn ($r) => str_contains($r->url(), 'api.example.com'));

Event::fake([OrderShipped::class]);
Event::assertDispatched(OrderShipped::class);

Queue::fake();
Queue::assertPushed(SendEmail::class);

Notification::fake();
Notification::assertSentTo($user, WelcomeNotification::class);

Storage::fake('public');
```

## Database helpers

Use `RefreshDatabase` (or `DatabaseTransactions`). Assert with `assertDatabaseHas`, `assertDatabaseMissing`, `assertSoftDeleted`, `assertModelExists`.

## Commands

```bash
php artisan test
php artisan test --filter=test_name
php artisan test tests/Feature/SomeTest.php
php artisan test --parallel
php artisan test --coverage
./vendor/bin/pest --filter=SomeTest
```
