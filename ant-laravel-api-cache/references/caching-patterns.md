# Caching Patterns — full Laravel code

All code is taken from a working anticms-style project (Laravel 12). Adapt names (`PostResource` = `AdvancePostResource`, `ApiResponse`, `PostService`) to the target project — the shapes matter, not the names.

## Contents

1. [The serializer trait](#1-the-serializer-trait) — required by everything else
2. [Detail endpoint (forever + model invalidation)](#2-detail-endpoint)
3. [Collection endpoint (forever + group index)](#3-collection-endpoint-with-group-index)
4. [Model clearCache additions](#4-model-clearcache)
5. [TTL endpoints (the rest)](#5-ttl-endpoints)
6. [404 sentinel under remember()](#6-404-sentinel-under-remember)
7. [Self-recursive resource (navigation) — eager-load pattern](#7-self-recursive-resources-navigation)

## 1. The serializer trait

`app/Trait/Api/SerializesApiResources.php`. Use it in the base `Controller` so every API controller inherits it. **Never call it through a `\Closure`-taking helper** (Scramble crash — see SKILL.md pitfall #1).

```php
<?php

namespace App\Trait\Api;

trait SerializesApiResources
{
    /**
     * Serialize a resource (and any nested resources) to a plain array so it
     * can be cached without re-running toArray() queries on every request.
     */
    protected function serializeResourceRecursively($resource)
    {
        if ($resource instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            $data = $resource->resolve(request());

            return $this->processArrayRecursively($data);
        }

        if (is_array($resource)) {
            return $this->processArrayRecursively($resource);
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            $data = $resource->toArray(request());

            return $this->processArrayRecursively($data);
        }

        return $resource;
    }

    /**
     * Recursively process array to serialize any nested resources
     */
    private function processArrayRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \Illuminate\Http\Resources\Json\JsonResource) {
                $data[$key] = $this->serializeResourceRecursively($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->processArrayRecursively($value);
            } elseif (is_object($value)) {
                // Keep JSON date format; Carbon::toArray() would split dates into components
                if ($value instanceof \DateTimeInterface) {
                    $data[$key] = json_decode(json_encode($value), true);
                } elseif (method_exists($value, 'toArray')) {
                    try {
                        $serialized = $value->toArray(request());
                        $data[$key] = is_array($serialized)
                            ? $this->processArrayRecursively($serialized)
                            : $serialized;
                    } catch (\Exception $e) {
                        $data[$key] = json_decode(json_encode($value), true) ?? null;
                    }
                } else {
                    $json = json_encode($value);
                    $data[$key] = $json !== false ? json_decode($json, true) : null;
                }
            }
        }

        return $data;
    }
}
```

Why `resolve()` for JsonResource: it runs `toArray()` **and** filters `MissingValue` entries from `whenLoaded()`, exactly like a real response render.

## 2. Detail endpoint

Forever cache, invalidated by the model. Includes self-healing so already-deployed caches holding legacy resource objects fix themselves on first hit.

```php
public function getPage($slug)
{
    $cacheKey = config('cache.cache_post_resource') . $slug . '_' . $this->locale . '_v2';

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

    if (! $data) {
        return ApiResponse::error('Error not found', 404);
    }

    return ApiResponse::json($data);
}
```

`getPost($slug)` is identical minus the type filter. Note: 404s are intentionally NOT cached (missing post → early return before `cache()->forever`).

## 3. Collection endpoint with group index

For paginated/filtered lists whose cache must be flushed when any post changes. **Keep this inline in the controller method** — do not extract a closure-taking helper (Scramble pitfall).

```php
private const CACHE_TTL = 300; // seconds — for the TTL endpoints below

public function getPostsByType(GetPostRequest $request, string $type)
{
    $filters = $this->getPostFilters($request);
    $cacheKey = $this->generatePostsCollectionCacheKey($type, $filters);
    $cacheGroupIndex = 'posts_collection';

    $cachedData = cache()->get($cacheKey);

    if ($cachedData === null) {
        $collection = $this->postService->getPostsByType(
            $type,
            $filters['field'],
            $filters['direction'],
            $filters['featured'],
            $filters['highlight'],
            $filters['shouldPaginate'],
            $filters['load'],
            $filters['page'],
            $filters['year']
        );

        $resource = PostResource::collection($collection);
        $serializedData = $this->serializeResourceRecursively($resource);

        $paginationMeta = [];
        if (method_exists($collection, 'total')) {
            $paginationMeta = [
                'total' => $collection->total(),
                'per_page' => $collection->perPage(),
                'current_page' => $collection->currentPage(),
                'last_page' => $collection->lastPage(),
            ];
        }

        cache()->forever($cacheKey, [
            'data' => $serializedData,
            'pagination' => $paginationMeta,
        ]);

        // Register the key so Post::clearCache can flush it on save
        $this->addCacheKeyToGroup($cacheGroupIndex, $cacheKey);

        // Respond from the serialized data — do NOT re-render the resource
        // (ApiResponse::paginate($resource) would re-run every query)
        $cachedData = [
            'data' => $serializedData,
            'pagination' => $paginationMeta,
        ];
    }

    $meta = [];
    if (! empty($cachedData['pagination'])) {
        $meta['attributes'] = $cachedData['pagination'];
        $meta['filtered'] = ApiResponse::getRequestFilters(); // make this public if private
    }

    return ApiResponse::apiResponse($cachedData['data'], $meta);
}

private function generatePostsCollectionCacheKey(string $type, array $filters): string
{
    $keyParts = [
        'posts_collection',
        $type,
        $filters['field'],
        $filters['direction'],
        $filters['featured'] ? '1' : '0',
        $filters['highlight'] ? '1' : '0',
        $filters['year'],
        $filters['load'],
        $filters['page'],
        $filters['shouldPaginate'] ? '1' : '0',
        $filters['search'] ?? '',
        $this->locale,
    ];

    return 'posts_collection_' . md5(implode('_', $keyParts));
}

private function addCacheKeyToGroup(string $groupIndex, string $cacheKey): void
{
    $keys = cache()->get($groupIndex, []);
    if (! in_array($cacheKey, $keys)) {
        $keys[] = $cacheKey;
        cache()->forever($groupIndex, array_unique($keys));
    }
}
```

Relationship-style endpoints (fetch by ids) use the same idea with a sorted, deduped id list in the key so `?ids=a,b` and `?ids=b,a` share one entry:

```php
$ids = array_filter(explode(',', $request->get('ids', '')));
$idsKey = implode(',', collect($ids)->unique()->sort()->values()->all());
$cacheKey = 'posts_relationship_' . md5($idsKey . '_' . $this->locale);
```

## 4. Model clearCache

Add the group flush to the existing `clearCache()` (called from the Post observer's saved/deleted hooks):

```php
// Clear posts collection cache group
$postsCollectionIndex = 'posts_collection';
$postsCollectionKeys = cache()->get($postsCollectionIndex, []);
foreach ($postsCollectionKeys as $key) {
    cache()->forget($key);
}
cache()->forget($postsCollectionIndex); // keep the index from growing unbounded
```

The per-locale detail keys should already be forgotten there; if not:

```php
$locales = \App\Models\Translations\Translation::getLanguages();
foreach ($locales['languages'] as $locale) {
    Cache::forget(config('cache.cache_post_resource') . $this->slug . '_' . $locale['code'] . '_v2');
}
```

## 5. TTL endpoints

Search, related, tags, categories, plain indexes — unlimited key space or no clean invalidation hook, so use a short TTL instead of forever. A small named-closure-free helper for the non-paginated ones is safe (it was tested against Scramble); the paginated ones must stay inline (section 3 shape, `cache()->remember` instead of get/forever).

```php
/**
 * Cache the serialized resource for a short TTL.
 */
private function rememberSerialized(string $cacheKey, \Closure $resolver)
{
    return cache()->remember($cacheKey, self::CACHE_TTL, function () use ($resolver) {
        $result = $resolver();

        return $result === null ? null : $this->serializeResourceRecursively($result);
    });
}
```

> Scramble note: `rememberSerialized` with closures returning flat resources (CategoryResource, TagResource, arrays, non-recursive PostResource collections) is proven safe. The crash only happens when a closure-fed helper *also* builds `PostResource::collection()` + pagination inside itself. When in doubt, inline.

Usage:

```php
public function getCategory(Request $request, $slug)
{
    $data = $this->rememberSerialized('category_' . $slug . '_' . $this->locale, function () use ($slug) {
        $category = $this->categoryService->findCategoryBySlug($slug);

        return $category ? new CategoryResource($category) : null;
    });

    if (! $data) {
        return ApiResponse::error('Error not found', 404); // null isn't cached → 404s stay live
    }

    return ApiResponse::json($data);
}
```

Stable filter-based keys:

```php
private function filtersCacheKey(array $filters, string $extra = ''): string
{
    return md5(json_encode($filters) . '_' . $extra . '_' . $this->locale);
}
// 'posts_search_'   . $this->filtersCacheKey($filters)
// 'posts_related_'  . $this->filtersCacheKey($filters, $slug)
// 'posts_category_' . $this->filtersCacheKey($filters, $slug)
```

## 6. 404 sentinel under remember()

`cache()->remember` re-runs on `null` but WOULD cache `[]`. When a service signals "not found" with a falsy non-null value, cache a sentinel:

```php
$cached = cache()->remember($cacheKey, self::CACHE_TTL, function () use ($slug, $filters) {
    $collection = $this->tagService->getPostsByTag($slug, /* ... filters ... */);

    if (! $collection) {
        return ['not_found' => true];
    }

    $resource = PostResource::collection($collection);
    $data = $this->serializeResourceRecursively($resource);

    $pagination = [];
    if (is_object($collection) && method_exists($collection, 'total')) {
        $pagination = [
            'total' => $collection->total(),
            'per_page' => $collection->perPage(),
            'current_page' => $collection->currentPage(),
            'last_page' => $collection->lastPage(),
        ];
    }

    return ['data' => $data, 'pagination' => $pagination];
});

if (! empty($cached['not_found'])) {
    return ApiResponse::error('Error not found', 404);
}
```

## 7. Self-recursive resources (navigation)

A resource that renders itself (`'items' => self::collection($this->children)`) must never reach the serializer — Scramble's type inference loops until the PHP process dies. Instead, cache the resource object but eager-load **everything the resource reads**, so the models serialized into the cache carry their relations and warm renders run zero queries:

```php
public function show(Request $request, $navigation)
{
    $cacheKey = config('cache.cache_navigation_slug') . $navigation . '_' . $this->locale;
    $this->cacheIndex($cacheKey); // register key for clearCache

    $data = cache()->rememberForever($cacheKey, function () use ($navigation) {
        // Eager-load everything the resource renders so cached models
        // carry their relations and warm hits run no queries
        $navigationModel = Navigation::with([
            'rootItems.translations',
            'rootItems.post.translations',
            'rootItems.children.post.translations',
            'rootItems.children.children.post.translations',
        ])->where('slug', $navigation)->first();

        if (! $navigationModel) {
            return null;
        }

        return NavigationItemResource::collection($navigationModel->rootItems);
    });

    if (! $data) {
        cache()->forget($cacheKey); // don't leave a null/empty entry behind
        return ApiResponse::error('Navigation not found', 404);
    }

    return ApiResponse::json($data);
}
```

The eager list must include the relations the resource lazy-reads at **every depth** (here: `post.translations` per level — the `children`/`translations` auto-load via the relation definitions in this codebase; add them explicitly if the target project's relations don't).
