---
name: ant-laravel-api-cache
description: Diagnose and fix Laravel API caching that "doesn't work" (queries still run on every cache hit) plus the N+1 queries underneath — for anticms-style projects (PostController, AdvancePostResource, ApiResponse, Scramble docs). Use whenever the user says an API cache isn't working, queries still appear despite caching, mentions "+1 query", shares a Laravel Debugbar trace to optimize, asks to add response caching to API endpoints, or wants N+1 fixes in a Laravel CMS API. Also use before adding cache()->rememberForever around any API Resource — the skill prevents the classic bug where the resource OBJECT gets cached instead of its output.
---

# Laravel API Response Caching + N+1 Fixes (anticms-style)

Battle-tested workflow from abm-investama-cms / azurite-cms: make every cache hit serve **0 queries**, keep response bodies byte-identical, keep Scramble docs alive, and keep cache invalidation working.

## The core bug (read this first)

The most common reason "cache doesn't work" in these projects:

```php
// BROKEN: caches the resource OBJECT, not its output
$data = cache()->rememberForever($key, fn () => new PostResource($post));
return ApiResponse::json($data);
```

Laravel still calls `PostResource::toArray()` **on every request** to render the response — and `toArray()` is where the queries live (template lookups, media, tags, categories, custom field details). The cache only skips the initial `findBySlug`; every "hit" still runs 10–20 queries.

**The fix is always the same shape:** serialize the resource to a plain array *before* caching, cache the array, respond from the array.

```php
$data = cache()->get($cacheKey);

// Self-heal legacy cache entries that stored the resource object
if ($data !== null && is_object($data) && $data instanceof PostResource) {
    cache()->forget($cacheKey);
    $data = null;
}

if ($data === null) {
    $post = $this->postService->findBySlug($slug, PostType::PAGE->value);
    if (! $post) {
        return ApiResponse::error('Error not found', 404);
    }

    $data = $this->serializeResourceRecursively(new PostResource($post));
    cache()->forever($cacheKey, $data);
}

return ApiResponse::json($data);
```

`serializeResourceRecursively` lives in a trait — full code in `references/caching-patterns.md` (copy it as `app/Trait/Api/SerializesApiResources.php`, `use` it in the base `Controller`).

## Workflow

Work in this order — measuring first prevents guessing:

1. **Measure** every endpoint cold vs warm with kernel-level query counts (`scripts/verify_endpoint.php`, or read the user's Debugbar trace). A "cached" endpoint with >0 warm queries has the core bug or an N+1 in the render path. See `references/verification.md`.
2. **Pick the cache pattern per endpoint** (table below) and apply it — code in `references/caching-patterns.md`.
3. **Fix N+1s in the cold-fill path** using the checklist in `references/n-plus-one-checklist.md`. These only run once per cache fill after step 2, but they still dominate cold latency (often 30+ → ~15 queries).
4. **Verify** — all four, every time:
   - warm hits = 0 queries
   - response body canonically identical to the old implementation (JSON with sorted keys; key *order* may shift, values must not)
   - saving a post flushes the right caches (touch a model, next hit must be cold, then warm again)
   - `/docs/api.json` still returns 200 (Scramble crashes are silent — see pitfalls)

## Cache pattern selection

| Endpoint class | Pattern | Invalidation |
|---|---|---|
| Detail by slug (`pages/{slug}`, `posts/{slug}`) | serialize → `cache()->forever` | model `clearCache()` forgets the exact keys per locale |
| Primary collection (`posts/type/{type}`, `posts/relationship`) | serialize + pagination meta → `forever` + **group index** | `clearCache()` loops the `posts_collection` group index and forgets every key |
| Everything else (search, related, tags, categories, pages index) | serialize → `cache()->remember` with **300s TTL** | TTL expiry (no manual invalidation needed) |
| Self-recursive resources (navigation trees) | **do NOT serialize** — `rememberForever` the resource with *full eager-loading* so cached models carry all relations | model's `clearCache()` on saved/deleted |

Key composition: `prefix + identifier + locale (+ version suffix)` for details; `prefix + md5(json_encode($filters) . extra . locale)` for filtered collections. Locale must always be in the key — these APIs localize via an `X-Locale` header, not the URL.

## Critical pitfalls (each one bit us in production code)

1. **Scramble docs crash — the silent killer.** dedoc/scramble's type inference hard-crashes PHP (exit 255, zero error output, `/docs/api` → empty 500) when a resource collection flows **through a `\Closure` parameter** into a helper that calls the recursive serializer, or when a **self-recursive resource** (e.g. a NavigationItemResource whose `items` renders `self::collection($this->children)`) reaches the serializer at all. Inline the cache+serialize pattern in each controller method (proven safe); never build a `respondCachedPaginated(\Closure $resolver)`-style helper. For recursive resources, use the eager-load pattern instead. Always check `/docs/api.json` after touching controllers.
2. **Carbon dates get mangled.** `processArrayRecursively` calls `toArray()` on objects — `Carbon::toArray()` explodes a date into a components array, silently changing the API contract. The trait ships with a `DateTimeInterface` branch that json-encodes/decodes instead, preserving Laravel's ISO format. Don't remove it.
3. **Don't render the resource twice on cold fill.** After serializing for the cache, respond from the serialized array — not `ApiResponse::paginate($resource)`, which re-renders and re-runs every query. Bonus: cold and warm responses become guaranteed-identical.
4. **`ApiResponse::paginate()` fatals on arrays** (`$data->resource`). Cached warm paths must respond via `ApiResponse::apiResponse($cached['data'], $meta)` / `ApiResponse::json($data)`, rebuilding `attributes` + `filtered` meta from the cached pagination array. `getRequestFilters()` is often `private` — make it `public`.
5. **Preserve 404 semantics under `cache()->remember`.** `remember` doesn't cache `null` (re-runs every time — fine for missing categories), but a service that returns `[]` for "tag not found" would get cached as a valid empty response. Cache a `['not_found' => true]` sentinel and translate it back to a 404.
6. **`getLanguages()`-style translation lookups look scary in traces** (one big query, 50ms+) but are usually already cached with a short TTL — they only show up right after `cache:clear`. Check before "fixing".

## N+1 quick checklist (details + code in `references/n-plus-one-checklist.md`)

- **Wrong relation eager-loaded**: service loads `meta.lang` (hasOne) but the resource reads `$item->translations` (hasMany) → one query per meta row. Grep every `->with(` against what the resource actually reads.
- **Model helpers that re-query loaded relations**: `$this->tags()->get()` inside the resource ignores eager-loaded `tags`. Guard with `$this->relationLoaded('tags') ? $this->tags : $this->tags()->get()`.
- **Nested detail rows loaded one-by-one**: eager `['details', 'children.details']`, not just `'children'`.
- **Spatie media**: cache/eager the `media` relation together with the file model (`File::with('media')->find($id)`, `featuredImageFile.media`) so `getFirstMediaUrl()` runs no query.
- **Repeated query shapes in a trace = same statement with different IDs.** Group Debugbar statements by shape (strip quoted values) to spot them instantly.

## Verification harness

`scripts/verify_endpoint.php` dispatches requests through the HTTP kernel with `DB::getQueryLog()` — no server needed, exact query counts per hit. Usage and the canonical-body-diff snippet are in `references/verification.md`. Never claim an endpoint is fixed without cold/warm numbers and a body diff.
