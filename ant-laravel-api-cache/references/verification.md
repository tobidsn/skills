# Verification — prove it, don't eyeball it

Four checks gate every change. Run them locally against the project's own DB; no HTTP server needed (requests dispatch through the kernel).

## 1. Query counts: cold vs warm

Use `scripts/verify_endpoint.php`:

```bash
php scripts/verify_endpoint.php /path/to/project "/api/v2/pages/some-slug" "/api/v2/posts/type/news?load=5"
```

Each path is hit 3×; output shows status + query count per hit. Expectations:

- hit 1 (cold): the fill queries (fine)
- hits 2–3 (warm): **0** for cached endpoints — anything >0 means the resource is still rendering (core bug) or an N+1 lives outside the cached section
- a 404 endpoint: small constant count every hit (404s intentionally aren't cached)

Interpreting a Debugbar trace instead: fetch `/_debugbar/open?op=get&id=<id>` with the user's cookies, read `queries.statements`, and group by shape (see n-plus-one-checklist.md).

## 2. Response body equivalence

Byte-diffs fail on JSON-object key order (batched eager loads reorder keys — legal). Compare canonically and expect ONLY volatile meta fields to differ (`memoryusage`, `elapstime`, `timestamp` — strip them first):

```python
import json

def canon(o):
    if isinstance(o, dict):
        return {k: canon(v) for k, v in sorted(o.items())}
    if isinstance(o, list):
        return [canon(x) for x in o]
    return o

a = json.load(open('body_old.json'))
b = json.load(open('body_new.json'))
for x in (a, b):
    x.get('meta', {}).pop('memoryusage', None)
    x.get('meta', {}).pop('elapstime', None)
    x.get('meta', {}).pop('timestamp', None)
print('IDENTICAL' if canon(a) == canon(b) else 'DIFFERS')
```

Capture "old" bodies by `git stash` → dump → `git stash pop` → dump — same data, both implementations, minutes apart. If `updated_at` differs, check whether your own invalidation test `touch()`ed the model between dumps before suspecting the code.

**Watch date formats**: if `published_at` comes back as an array of components (`{"year": ..., "month": ...}`) instead of an ISO string, the serializer's `DateTimeInterface` branch is missing — that's a breaking contract change.

## 3. Invalidation

```php
$post->touch(); // fires the observer -> clearCache()
```

Then re-hit: the next request must be **cold** (queries > 0) and the one after **warm** (0). Run this for the detail endpoint AND one group-indexed collection endpoint — the group flush is the part people forget to wire.

## 4. Scramble docs (if the project has dedoc/scramble)

```bash
curl -sk https://<project>.test/docs/api.json -o /dev/null -w "%{http_code}\n"   # expect 200
```

A Scramble crash is **silent**: empty 500, nothing in laravel.log, PHP exits 255 with no output. If it happens after your changes:

- Bisect file-by-file (`git checkout HEAD -- <file>` one at a time, re-test), then method-by-method inside the guilty controller.
- Crash runs are slow (memory exhaustion) — structure the bisect so most runs are the fast "expected 200" case: revert everything, confirm 200, then re-add pieces one at a time.
- Known causes: resource collections flowing through `\Closure` params into the recursive serializer; self-recursive resources reaching the serializer at all. Fixes in SKILL.md pitfall #1.
- `timeout` doesn't exist on stock macOS — don't build the bisect loop around it.

## Kernel-dispatch harness details

The harness bootstraps once and dispatches N requests in-process:

```php
$app = require $projectPath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); // needed before cache()/DB facades

DB::enableQueryLog();
DB::flushQueryLog();                 // reset BETWEEN hits — counts are per-request
$response = $kernel->handle(Illuminate\Http\Request::create($path, 'GET'));
$count = count(DB::getQueryLog());
```

Gotchas:
- Forgetting the console-kernel `bootstrap()` line → `Class "cache" does not exist` from the container.
- Locale-keyed caches: clear/warm per locale (`X-Locale` header) or you'll measure the wrong entry.
- `php artisan cache:clear` between measurement rounds gives honest colds, but also empties helper caches (languages, file lookups) — expect a few extra queries on the very first hit.
- No fixture data for an endpoint? Seed a minimal row set via a tinker script, verify, then delete it — schema-check the table first (`Schema::getColumnListing`), translated tables often keep `title` on a `_lang` table, not the base table.
