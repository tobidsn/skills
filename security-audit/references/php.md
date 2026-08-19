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

## TLS verification disabled — MED

```php
// BAD
Http::withoutVerifying()->get($url);
$client = new Client(['verify' => false]);

// GOOD — trust the CA bundle, or point at the specific cert
$client = new Client(['verify' => '/path/to/internal-ca.pem']);
```

Almost always left over from debugging a self-signed cert in staging.

## Rate limiting — MED

```php
// routes/api.php
Route::middleware('throttle:6,1')->group(function () {
    Route::post('login', LoginController::class);
    Route::post('password/reset', ResetController::class);
});
```

Throttle by identity *and* IP — `RateLimiter::for('login', fn ($r) => Limit::perMinute(6)->by($r->email . '|' . $r->ip()))`. Also cap upload size in the Form Request; a rate limit doesn't stop one huge body.

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
