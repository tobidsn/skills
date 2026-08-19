# N+1 Checklist — render-path queries in resource-heavy APIs

Every item below was a real bug in production anticms code. After response caching lands, these only cost the cold fill — but a cold fill going 49 → 14 queries is still worth it, and endpoints without caching (CMS admin, exports) benefit on every request.

## How to spot them fast

Group a Debugbar/query-log trace by **query shape** (strip quoted values and numbers):

```python
import collections, re
shapes = collections.Counter(re.sub(r"\d+|'[^']*'", '?', q['sql'])[:120] for q in statements)
# any shape with count > 1 that queries by a single id = lazy-load in a loop
```

Repeated `where base_id = ?` / `where id = ? limit 1` shapes are lazy relation loads inside a render loop. Repeated *identical blocks* of shapes (same sequence twice) means something is rendered twice — see item 6.

## 1. Wrong relation eager-loaded (the sneakiest one)

The service eager-loads `meta.lang`, the resource reads `$item->translations`. `lang` is a `hasOne`, `translations` a `hasMany` — related but different relations, so every meta row lazy-loads:

```php
// BEFORE — resource does: foreach ($item->translations as ...)
->with('translations', 'meta.lang', 'featuredImageFile')

// AFTER
->with('translations', 'meta.translations', 'featuredImageFile', 'category.translations', 'tags')
```

**Grep every `->with(` in the services and cross-check each relation name against what the resources actually read.** This bug hides because the eager load "looks right" and even loads *some* data. Also check for the same string in more than one service (it was in PostService ×5 AND CategoryService here) and for `Model::with(` (no `->` — a plain sed for `->with(` misses it).

## 2. Model helpers that ignore eager-loaded relations

```php
// BEFORE — always queries, even when tags were eager-loaded
public function getPostTags(): mixed
{
    $tags = $this->tags()->get();
    // ...
}

// AFTER — reuse the loaded relation
$tags = $this->relationLoaded('tags') ? $this->tags : $this->tags()->get();
```

Apply the same guard to every `$this->relation()->get()` inside models/resources (`getCategories()`, etc.). Then make sure the list queries actually eager-load those relations (item 1's AFTER line).

## 3. Nested rows loaded one-by-one

Custom-field style trees where the resource walks `$field->details` and `$field->children[*]->details`:

```php
// BEFORE — 1 query per field for details (11 lazy queries on a real page)
return $this->customFields()->with('children')->whereNull('parent_id')->orderBy('sort')->get();

// AFTER — 3 queries total, regardless of field count
return $this->customFields()->with(['details', 'children.details'])->whereNull('parent_id')->orderBy('sort')->get();
```

Caveat: batching `details` into one `whereIn` can change the **row order** within each field, which reorders keys inside JSON *objects*. Key order in objects is not part of the JSON contract — verify with a canonical (sorted-key) diff, not a byte diff.

## 4. Spatie media lazy-loads

`getFirstMediaUrl()` needs the `media` relation. Two fixes:

```php
// Cached file lookups: store the model WITH media
return Cache::remember('file_' . $id, 60, function () use ($id) {
    return self::with('media')->find($id);   // was: self::find($id)
});

// Eager chains: include media on the file relation
->with('featuredImageFile.media')            // was: 'featuredImageFile'
```

## 5. Morph relations read per-render

`$this->template?->name` (morphOne) resolved inside the resource → 1 lazy query per request. Eager it conditionally where it applies:

```php
if ($type !== null) {
    $query->type($type);
    if ($type === PostType::PAGE->value) {
        $query->with('template');
    }
}
```

## 6. The double render

Cold path serializes the resource for the cache, then returns `ApiResponse::paginate($resource)` — which renders the whole resource **again**, doubling every remaining render query. The trace signature: the same block of query shapes appearing twice in one request. Fix: respond from the serialized array (see `caching-patterns.md` §3).

## 7. Services with no eager loading at all

Easy to miss because the endpoint "works": `getPostsByTag` built its query with zero `->with(...)`. Any list that feeds a heavy resource needs the standard set:

```php
Post::with('translations', 'meta.translations', 'featuredImageFile', 'category.translations', 'tags')
```

And light resources need their bit too — `CategoryResource` reads `translations`, so `getCategoriesByType()` / `findCategoryBySlug()` need `->with('translations')`.

## What NOT to chase

- **One big `select * from translations` (50–70ms) right after `cache:clear`** — that's a `getLanguages()`-style lookup that is already TTL-cached; it only appears when the cache is empty.
- **The eager-load block itself** (one query per relation, whereIn-batched) — that IS the fix working. 10–15 batched queries on a cold fill for a heavy resource is normal.
- **Sub-millisecond duplicate-free queries** — fix correctness (counts) first; micro-latency later if ever.
