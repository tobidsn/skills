---
name: ant-laravel-eloquent
description: Laravel Eloquent for Antikode-style apps—query performance, relationship loading, indexes, pagination, caching, and model hygiene. Triggers on "Eloquent", "N+1", "query optimization", "Laravel models", "Antikode Eloquent", "review model queries", "database performance", or "refactor Eloquent".
---

# Laravel Eloquent - Antikode Architecture

Use Eloquent as the data layer behind Actions/Services: keep models focused on persistence and relationships; put business rules in services. Apply the sections below for query performance, loading strategy, and model hygiene in this codebase—especially during reviews and refactors.

## Models and safety

- Prefer `final` model classes and explicit return types on relationship methods (`BelongsTo`, `HasMany`, etc.).
- Protect mass assignment with `$fillable` or a deliberate `$guarded`; never leave models wide open.
- Use `$casts` for dates, arrays, booleans, and integers so the domain types stay consistent at boundaries.

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Post extends Model
{
    protected $fillable = ['title', 'content', 'status'];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
        'is_featured' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

In non-production environments, catch accidental lazy loading:

```php
Model::preventLazyLoading(! app()->isProduction());
```

## SELECT and N+1

**Do:** Select only needed columns; eager load what you will read; constrain nested eager loads.

```php
User::select(['id', 'name', 'email'])->get();

Post::with(['user' => function ($query) {
    $query->select('id', 'name');
}])->select(['id', 'title', 'user_id'])->get();

Post::with(['comments' => function ($query) {
    $query->where('approved', true)->latest();
}])->get();

$posts = Post::all();
$posts->loadMissing(['user', 'category']);
```

**Avoid:** `SELECT *` for large lists; accessing relations in loops without `with()` / `loadMissing()`.

```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;
}
```

Use `withCount` when you only need counts, not full relations:

```php
Post::withCount('comments')->get();
```

## Indexes and WHERE clauses

**Do:** Filter on indexed columns; combine conditions that match compound indexes; use `whereIn` for discrete sets.

```php
User::where('email', $email)->first();
Post::where('status', 'published')->where('created_at', '>=', $date)->get();
Post::where(['status' => 'published', 'category_id' => $id])->get();
User::whereIn('status', ['active', 'pending'])->get();
```

**Avoid:** Functions on indexed columns in `WHERE`; leading wildcards on LIKE; unconstrained `whereHas`; unnecessary `orWhere` chains when `whereIn` fits.

```php
User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
User::where('name', 'like', '%john%')->get();
Post::whereHas('user')->get();
```

Prefer `whereExists` over heavy `whereHas` when you only need existence on large datasets:

```php
Post::whereExists(function ($query) {
    $query->select(DB::raw(1))
        ->from('comments')
        ->whereRaw('comments.post_id = posts.id')
        ->where('approved', true);
})->get();
```

## Counts, existence, aggregates, pluck

**Do:** `exists()` for presence checks; database aggregates (`sum`, `avg`); `pluck` on a constrained query.

```php
if (User::where('email', $email)->exists()) {
}

$total = Order::sum('amount');

$userIds = User::where('active', true)->pluck('id');
Post::select('title')->where('status', 'published')->pluck('title');
```

**Avoid:** `count() > 0` for existence; `get()->pluck()`; summing in PHP over full result sets.

## Query scopes

Centralize reusable filters on the model:

```php
public function scopePublished(Builder $query): void
{
    $query->where('status', 'published')->whereNotNull('published_at');
}

Post::published()->orderByDesc('published_at')->get();
```

## Large datasets: chunk, cursor, lazyById

**Do:** `chunk` when mutating rows; `cursor` / `lazyById` for read-heavy streaming; chunk with `with()` when relations are needed per row.

```php
User::chunk(500, function ($users) {
    foreach ($users as $user) {
        $user->update(['last_seen' => now()]);
    }
});

User::cursor()->each(function ($user) {
    $this->sendEmail($user);
});

User::lazyById(500)->each(function ($user) {
    $user->posts;
});

Post::with('user')->chunk(100, function ($posts) {
    foreach ($posts as $post) {
    }
});
```

**Avoid:** `all()` + foreach for large tables.

## Pagination

**Do:** `cursorPaginate` for large offsets; `simplePaginate` when total count is unnecessary; index order columns.

```php
Post::orderBy('created_at', 'desc')->cursorPaginate(20);
Post::where('status', 'published')->simplePaginate(20);
```

**Avoid:** Deep `paginate()` pages on huge tables without indexes; ordering by unindexed expressions.

## Eloquent vs query builder vs raw SQL

- **Eloquent:** CRUD, relationships, scopes, small to medium complexity.
- **Query Builder:** joins, grouping, reporting without hydrating full models.
- **Raw SQL:** analytics, heavy reporting, proven hot paths.

Performance is often: raw < builder < eloquent for the same work—trade convenience against measured need.

## Caching

Cache expensive lists and counts; invalidate on writes (observers or domain services).

```php
$popularPosts = Cache::remember('popular_posts', 3600, function () {
    return Post::with('user')
        ->where('views', '>', 1000)
        ->orderByDesc('views')
        ->take(10)
        ->get();
});

Cache::tags(['posts', 'users'])->put('popular_content', $data, 3600);
```

## Database-level updates and transactions

**Do:** bulk `update`/`increment` in the database; wrap multi-row writes in transactions.

```php
Post::where('status', 'draft')->update(['status' => 'archived']);
Post::where('id', $id)->increment('views');

DB::transaction(function () use ($data) {
    Model::insert($data);
});
```

**Avoid:** Loading a collection only to update each row in PHP when one query suffices.

## Model events

Use `booted()` for cross-cutting persistence rules (slugs, cascades) sparingly; prefer explicit service logic when behavior is complex or test-critical.

```php
protected static function booted(): void
{
    static::creating(function (Post $post) {
        $post->slug = Str::slug($post->title);
    });
}
```

## Connections, queues, monitoring, memory

- Use read connections for heavy read workloads when configured (`User::on('read')`).
- Offload large processing to queued jobs.
- Use query logging or Telescope in development; time critical paths in code when investigating regressions.
- In long workers: `DB::disconnect()` when appropriate; avoid retaining huge collections.

## Anti-patterns (quick reference)

- Eager loading relations you never use.
- Filtering or aggregating in collections after `all()`.
- `whereHas` without narrowing the related query.
- Many single-row queries in loops—batch with `whereIn` or joins.
- Bulk `create()` in a loop without a transaction.

## Checklist

- [ ] Required columns selected; relationships eager loaded or constrained
- [ ] Indexes match common `WHERE` / `ORDER BY` usage
- [ ] No N+1; `withCount` used where only counts are needed
- [ ] `exists()` vs `count()` used appropriately
- [ ] Large reads use chunk/cursor/lazyById; mutations use chunk + transaction where needed
- [ ] Pagination strategy fits dataset size
- [ ] Caching and invalidation considered for hot reads
- [ ] Mass assignment and casts configured; lazy loading guarded in dev
- [ ] Scopes used for repeated query shapes
