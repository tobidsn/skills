# PHP / Laravel fix patterns

Loaded when `composer.json` is present. BAD/GOOD only — the findings table doesn't need this file.

## SQL injection — CRIT

```php
// BAD
User::whereRaw("email = '{$request->email}'")->first();
DB::select("SELECT * FROM users WHERE id = $id");

// GOOD
User::where('email', $request->email)->first();
DB::select('SELECT * FROM users WHERE id = ?', [$id]);
// Raw genuinely needed? bind it:
User::whereRaw('LOWER(email) = ?', [$email])->first();
```

Column names, table names, and `ORDER BY` direction can't be bound — allowlist them against a fixed array:

```php
abort_unless(in_array($sort, ['name', 'created_at'], true), 422);
$query->orderBy($sort, $dir === 'desc' ? 'desc' : 'asc');
```

## Command injection — CRIT

The bug is the shell, not the string. Pass argv.

```php
// BAD
shell_exec("convert {$request->file} out.png");

// GOOD — array form, no shell involved
$p = proc_open(['convert', $path, 'out.png'], $descriptors, $pipes);
// Symfony Process, already in Laravel:
new Symfony\Component\Process\Process(['convert', $path, 'out.png']);
// Last resort if a shell is unavoidable:
shell_exec('convert ' . escapeshellarg($path) . ' out.png');
```

Argv still doesn't stop *argument* injection — a value of `--upload-pack=…` is read as a flag. Reject a leading `-`, or terminate flags with `--`.

## XSS — HIGH

```blade
{{-- BAD --}}
{!! $post->body !!}

{{-- GOOD --}}
{{ $post->body }}

{{-- Rich text that must stay HTML: sanitize server-side (HTMLPurifier), then --}}
{!! $post->sanitized_body !!}
```

`{{ }}` escapes for HTML text, not for a JS or attribute context. Passing data into inline JS goes through `@json($data)`, never string interpolation.

## Path traversal — HIGH

```php
// BAD
return response()->file(storage_path('uploads/' . $request->name));

// GOOD — resolve, then prove it stayed under the root
$root = realpath(storage_path('uploads'));
$path = realpath($root . '/' . $request->name);
abort_unless($path && str_starts_with($path, $root . DIRECTORY_SEPARATOR), 404);
return response()->file($path);
```

`realpath` before the prefix check is what closes the symlink hole, and the trailing separator is what stops `/uploadsfoo` from passing as `/uploads`. On archive extraction, cap the decompressed size and reject entries whose names traverse.

### Destructive targets need two more checks — HIGH

A read served from the wrong path leaks one file; a `Storage::deleteDirectory()` on the wrong path takes the bucket. When the path feeds `unlink`, `Storage::delete`, `deleteDirectory`, `rename`, or a move, the prefix check above is necessary and not sufficient:

```php
// BAD — path from the DB, and the row was never checked against the caller
Storage::disk('public')->deleteDirectory($project->folder);

// GOOD
$folder = trim($project->folder, '/');
abort_if($folder === '' || substr_count($folder, '/') < 1, 422);   // depth floor: never the root
abort_unless($project->user_id === $user->id, 403);                 // ownership, read before the delete
abort_unless(Storage::disk('public')->exists($folder), 404);
Storage::disk('public')->deleteDirectory('projects/'.$folder);      // root prefixed here, not by the data
```

`realpath` returns `false` for a path that no longer exists, so a delete built on it silently degrades to the root — that is the whole bug in one line. The depth floor and the hardcoded root prefix are what stop an empty or `.`-valued column from resolving to "everything".

## Weak crypto / RNG — HIGH

```php
// BAD
$hash = md5($password);
if ($token === $request->token) { … }
$token = mt_rand();

// GOOD
$hash = password_hash($password, PASSWORD_ARGON2ID);
password_verify($password, $hash);
if (hash_equals($token, $request->token)) { … }
$token = bin2hex(random_bytes(32));
```

Laravel's `Hash::make()` / `Hash::check()` and `Str::random()` are already correct — the finding is code that bypasses them.

**But check the configured cost, not just the call.** `Hash::make()` reads `config/hashing.php`, so the work factor lives there, not at the call site:

```php
// config/hashing.php — bcrypt rounds below 10, or argon2 memory/time cut down, is a HIGH finding
'bcrypt' => ['rounds' => env('BCRYPT_ROUNDS', 12)],
```

**No `config/hashing.php` is not a finding** — it means the framework default applies, which is safe. Only a file that *lowers* the cost is worth a row.

The same applies to any project or package helper that wraps hashing (`app/Support/Hasher.php`, a vendor `Bcrypt` class): open it and read the parameters, following it into `vendor/` when that's where it lives. A wrapper whose name matches the GOOD pattern still fails if it passes a minimum cost. Report it at the wrapper's own `path:line`, and note in `Fix` when it is upstream and cannot be changed in-repo.

`whereRaw` is the highest-noise signal in this file. `whereRaw('LOWER(title) LIKE ?', [$q])` is *correct* — the placeholder and bindings array are right there. Read the arguments before reporting: the finding is interpolation into the SQL string, not the use of `whereRaw`.

## TLS verification disabled — MED

```php
// BAD
Http::withoutVerifying()->get($url);
$client = new Client(['verify' => false]);

// GOOD — trust the CA bundle, or point at the specific cert
$client = new Client(['verify' => '/path/to/internal-ca.pem']);
```

Almost always left over from debugging a self-signed cert in staging.

## SSRF — CRIT

The server is inside the network the caller is not. A fetch whose URL comes from input turns your app into a proxy for the metadata endpoint, the internal admin panel, and every service that trusts its own subnet.

```php
// BAD
$preview = Http::get($request->input('url'))->body();
$image = file_get_contents($request->avatar_url);

// GOOD — allowlist the host, and refuse to be redirected off it
$host = parse_url($request->input('url'), PHP_URL_HOST);
abort_unless(in_array($host, config('services.preview.allowed_hosts'), true), 422);

$ip = gethostbyname($host);
abort_if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false, 422);

Http::withOptions(['allow_redirects' => false, 'timeout' => 5])->get($request->input('url'));
```

Three things the naive version misses. A **scheme check** (`file://`, `gopher://` are not http). **Redirects** — an allowlisted host can 302 you to `169.254.169.254`, which is why `allow_redirects` goes off rather than being trusted. And the **DNS rebinding window**: the resolve you validated is not necessarily the resolve the HTTP client performs. A blocklist of `localhost` and `127.0.0.1` catches none of this — `0.0.0.0`, `[::1]`, `2130706433`, and a hostname you control resolving to a private A record all get through. Allowlist, or route the fetch through an egress proxy.

Webhook URLs, avatar-by-URL, PDF/HTML renderers, and "import from link" are where this lives.

## Secrets in responses — HIGH

```php
// BAD — every column, forever, including the ones added next sprint
return response()->json(User::with('orders')->find($id));

// GOOD — a Resource names the fields, and the model hides the rest
final class UserResource extends JsonResource {
    public function toArray($request): array {
        return ['id' => $this->id, 'name' => $this->name];
    }
}

// app/Models/User.php — the backstop for the paths that skip the Resource
protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'api_token'];
```

`$hidden` is a safety net, not the control: it only applies to serialization, and a `toArray()` in a job or an export walks straight past your intent. The finding is a model or collection returned directly from a controller — grep for `return response()->json($model` and `return $model`. Watch `with()` too: an eager-loaded relation serializes wholesale, so a clean `UserResource` still leaks if it wraps `$this->orders` without a nested Resource.

## CORS — MED

`config/cors.php` with `'allowed_origins' => ['*']` and `'supports_credentials' => true` is a real finding — the combination is rejected by browsers, so it usually means someone then reflected the origin instead, which is worse. List the origins. And remember CORS is a browser policy, not authorization: it stops a page from reading the response, never a script or a server from making the request.

## Rate limiting — MED

```php
// routes/api.php
Route::middleware('throttle:6,1')->group(function () {
    Route::post('login', LoginController::class);
    Route::post('password/reset', ResetController::class);
});
```

Throttle by identity *and* IP — `RateLimiter::for('login', fn ($r) => Limit::perMinute(6)->by($r->email . '|' . $r->ip()))`. Also cap upload size in the Form Request; a rate limit doesn't stop one huge body.

## Missing object-level authorization (IDOR) — CRIT

`auth` middleware is authentication, not authorization. Route model binding fetches any record whose id the caller can guess — nothing about it checks ownership.

```php
// BAD — any logged-in user can open any project by UUID
Route::middleware('auth')->get('project/{project}', [ProjectController::class, 'show']);
public function show(Project $project) { … }            // binding = find by id, nothing else

// GOOD — pick one mechanism and use it on every resource route
public function show(Project $project)
{
    $this->authorize('view', $project);                  // ProjectPolicy::view checks user_id
    …
}
Route::get('project/{project}', …)->can('view', 'project');   // same policy, at the route

// Or skip the binding and scope the query to the owner:
$project = $request->user()->projects()->findOrFail($id);

// Nested resources: scope the child binding to the parent
Route::get('project/{project}/token/{token}', …)->scopeBindings();
```

Indirect access is the same bug: `campaign.show` reached via `$campaign->project` still needs the ownership check on the project. A `role` check does not fix IDOR — role gates *which class of user*, the policy gates *which row*. **Zero files in `app/Policies` plus zero `authorize()` calls in an app with per-user data is itself the finding** — one CRIT row for the app, not one per route.

## Open registration — HIGH

Laravel Breeze/Jetstream ship `/register` enabled; on an internal admin or multi-tenant CMS that is a door, and combined with the IDOR above it is the whole breach.

```php
// BAD — routes/auth.php left as scaffolded on an admin app
Route::post('register', [RegisteredUserController::class, 'store']);   // creates user, auto-login

// GOOD — remove the routes, or gate account activation
// 1. No self-serve accounts: delete the register routes; admins create users.
// 2. Registration needed: create disabled, no auto-login, admin approves.
$user = User::create([...('is_approved' => false)...]);
// and a middleware/gate that checks is_approved before anything else
```

New accounts must land on least privilege: an explicit low role, no default access to shared resources, and `throttle:` on the register route so accounts can't be minted in a loop.

## Client headers as authorization — HIGH

`Origin`, `Referer`, and `X-Forwarded-*` are written by the client. curl sets them to whatever it wants — a domain whitelist over them is CSRF hygiene at best, never authentication.

```php
// BAD — spoofable, and fails open
$origin = $request->header('Origin') ?? $request->header('Referer');
if ($whitelist->isEmpty()) return $next($request);       // no config = allow everyone

// GOOD — a server-side credential per project, and fail closed
Route::middleware('auth:sanctum')->post('…');            // or a per-project API key checked in middleware
abort_if($whitelist->isEmpty(), 403);                    // unconfigured = deny
```

Report the fail-open branch as its own part of the finding — "backwards compatible" allow-all defaults are how the check quietly stops existing.

## Debug & dev surface in production — HIGH

```bash
# ignition/telescope/horizon/debugbar in "require" instead of "require-dev" is the grep
grep -n 'ignition\|telescope\|debugbar' composer.json
grep -rn 'APP_DEBUG' .env.example
```

`APP_DEBUG=true` in production turns Ignition into an exploit path (`/_ignition/execute-solution`, `update-config`) — that's a config check, but the audit can flag the enablers: debug packages in `require`, no `viewTelescope`/`viewHorizon` gate override, an API-docs route (Scramble/Scribe `/docs/api.json`) whose gate is only `auth()->check()` — on a multi-tenant app that hands every self-registered user the full endpoint map. Leftover routes are the same class:

```php
// BAD — scaffolding that shipped
Route::get('test', [TestController::class, 'index']);    // returns a whole model, no auth

// GOOD — delete it; a "temporary" route has no safe version
```

## Race conditions — HIGH

Check-then-act on shared state: balance checks, coupon redemption, "first one wins" claims.

```php
// BAD — two requests both read 1 remaining
$coupon = Coupon::find($id);
if ($coupon->remaining > 0) { $coupon->decrement('remaining'); }

// GOOD — the row is locked for the transaction
DB::transaction(function () use ($id) {
    $coupon = Coupon::whereKey($id)->lockForUpdate()->firstOrFail();
    abort_if($coupon->remaining < 1, 409);
    $coupon->decrement('remaining');
});

// Non-DB shared state, across processes:
Cache::lock("coupon:$id", 10)->block(5, fn () => …);
```

`lockForUpdate()` outside a transaction does nothing — the lock releases immediately.

## Missing security headers — LOW

One finding for the app, never one per file.

```php
// app/Http/Middleware/SecurityHeaders.php
$response->headers->add([
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'",
]);
```

Session cookies want `HttpOnly`, `Secure`, `SameSite=Lax` too — `config/session.php`: `'secure' => true`, `'http_only' => true`, `'same_site' => 'lax'`.
