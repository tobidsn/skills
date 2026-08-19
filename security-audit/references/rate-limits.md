# Rate limits, OTP, and bot filtering

Read this when a run turns up an unthrottled write endpoint, an OTP or verification flow, or a public endpoint that costs money per request. It carries the numbers and the reasoning; the findings table only needs the row.

## Two finding classes, not one

Lumping these together hides the severe one:

| Sev | Class | What it is |
|---|---|---|
| MED | **Unthrottled public write** | A public `POST`/`PUT`/`PATCH`/`DELETE` with no limiter. Abuse of volume — spam, scraping, resource burn, and a bill when the endpoint sends SMS or email. |
| HIGH | **Unbounded verification attempts** | An OTP, 2FA, or reset-token verify with no cap on wrong guesses. This is an authentication bypass: a 6-digit code is 10⁶, and unlimited retries reduce that to "eventually". |

The second is not a rate-limiting problem wearing a different hat. A rate limit caps *frequency*; an attempt cap bounds *total guesses against one identity*. A verify endpoint at 2 requests/minute with no failure counter is still brute-forceable given patience.

## You cannot grep an absence

Both findings are things that *aren't there*, so the method is enumerate, then subtract. Grepping for `throttle:` finds the routes that are protected — it says nothing about the ones that aren't, and a hit count of "some" reads as "handled".

```bash
# Laravel — every write route, then look at what has no throttle and no auth
php artisan route:list --method=POST --json 2>/dev/null \
  | python3 -c "import json,sys;[print(r['uri'],'|',r['middleware']) for r in json.load(sys.stdin) if 'throttle' not in str(r.get('middleware',''))]"

# Express / Nest — registration sites, then compare against limiter mounts
grep -rEn "\.(post|put|patch|delete)\(" src/ routes/ | grep -v test
grep -rn "rateLimit\|@Throttle" src/

# Go — route registration in the router setup
grep -rEn '\.(POST|PUT|PATCH|DELETE)\(|HandleFunc\(' internal/ cmd/
```

Report one row per unprotected *surface*, not per route: "12 public write routes with no limiter" at the router file, not twelve rows.

## Recommended starting limits

These are starting points to tune, not laws — the right number depends on real traffic and on what a single request costs you. Tune the number; do not skip the limit.

| Endpoint class | Limit | Key by | Why this shape |
|---|---|---|---|
| Login | 5–10 / 15 min | identity **+ IP** | Credential stuffing rotates the identity, so IP has to be in the key |
| Public signup | 3–5 / hour | IP | Plus bot filtering — see below |
| Password reset request | 3–5 / hour | identity; 10 / hour per IP | |
| **OTP / SMS send** | 1 per 2 min cooldown, **plus** a daily cap (≈10) | identity **only** | Each send costs money; see the keying rule below |
| **OTP verify** | 2–3 / min request rate, **plus** max 3 wrong codes per 15 min | identity | Two separate controls — see the OTP section |
| Public form / contact | 5–10 / hour | IP | Plus bot filtering |
| Public read / search | 60–100 / min | IP | |
| Authenticated write | 60 / min | user id | |
| Payment / order create | 5–10 / min | user id | Plus an idempotency key — a limiter does not stop a double charge |

**The key depends on what you are protecting, and this is where most limiters go wrong.**

- Protecting against **credential stuffing**: the attacker controls the identity, so keying by identity alone is useless. Include the IP.
- Protecting against **cost** (SMS, email, a paid third-party call): key by **identity alone**. Adding the IP hands out a fresh allowance every time the user changes network — wifi to cellular resets the budget, and an attacker cycling IPs gets an unlimited one.

That second rule is the one that looks wrong until you think about it. A daily SMS cap keyed by `phone + ip` does not cap anything.

## OTP needs four controls

A complete flow has all four. Each one covers an attack the others don't:

1. **Resend cooldown** — one send per 1–2 minutes, keyed by identity. Stops a hammering client and accidental double-sends.
2. **Daily send cap** — around 10 per identity per day. This is the cost control, and the reason it exists is financial, not security.
3. **Verify request rate** — 2–3 per minute, keyed by identity. Slows automation.
4. **Wrong-guess cap** — at most 3 incorrect codes per identity per 15-minute window, **cleared on success**. This is the control that makes the code space finite.

Plus three properties of the code itself: **at least 6 digits**, **single-use** (mark it validated, reject a second use), and a **short TTL** (5 minutes).

**Bind the failure counter to the identity, not to the code.** Burning the code after N failures sounds equivalent and is weaker: the attacker requests a fresh code and gets a fresh budget, so the real ceiling becomes `send_cap × attempts_per_code`. Counting failures per phone across a window closes that, and clearing the counter on success keeps legitimate users out of the way.

Laravel, using the built-in limiter store rather than a new table:

```php
// in the verify handler, when the submitted code matches nothing
$failKey = 'otp_verify_fail:'.$request->phone_number;
if (RateLimiter::hit($failKey, 900) >= 3) {          // 3 wrong codes / 15 min
    return ApiResponse::errorResponse(429, 'Too many incorrect attempts. Please request a new OTP.');
}
// …and on a successful verify
RateLimiter::clear('otp_verify_fail:'.$request->phone_number);
```

### The Laravel stacked-limit trap

Returning several `Limit`s from one `RateLimiter::for` is the right way to express a cooldown *and* a daily cap — but `ThrottleRequests` derives one cache key per `Limit` from its `by()` value, so two limits sharing a `by()` clobber each other's decay window. Give each a distinct prefix:

```php
RateLimiter::for('otp_send', function (Request $request) {
    $phone = $request->phone_number ?? $request->ip();

    return [
        Limit::perMinutes(2, 1)->by('otp-cooldown:'.$phone),   // 1 send / 2 min
        Limit::perDay(10)->by('otp-daily:'.$phone),            // cost ceiling, phone only
    ];
});
```

Without the prefixes the daily cap and the cooldown fight over one key and neither behaves as written. Nothing errors — it just silently doesn't work, which is why this is worth a row when you see it.

**Dev escape hatches must fail closed.** A limiter that returns `Limit::none()` when delivery is disabled, or a bypass endpoint, is fine — as long as the flag defaults to *enabled* so production is protected by omission rather than by remembering:

```php
if (! config('services.sms.enabled', true)) {   // default true: prod is protected
    return Limit::none();
}
```

The audit check is the **default value in the second argument**. `config('services.sms.enabled', false)` here is a finding: a deploy that forgets the flag ships with no cooldown at all.

## When a rate limit is not enough

**A rate limit and a bot filter solve different problems, and neither substitutes for the other.** A limiter caps volume per key; ten thousand IPs sending one request each pass every limiter you have. A solved CAPTCHA, meanwhile, still allows unlimited volume if nothing is counting. Public endpoints that cost money per request — OTP send, contact forms that email staff, signup — want both.

### reCAPTCHA v3, done properly

v3 returns a **score from 0.0 to 1.0, not a pass or fail.** You choose a threshold and you choose the action. Four things must be checked server-side, and an integration that only runs the client-side script is theatre — the token means nothing until `siteverify` has seen it:

1. `success` — the token is valid, unexpired, and unused
2. `score >= threshold` — 0.5 is the usual starting point
3. `action` matches the action you expected — **this is the check most implementations skip.** Without it, a token minted on a low-value page can be replayed against your OTP endpoint
4. transport failures fail **closed** — a timeout talking to Google is a rejection, not a pass

```go
// the shape that gets it right: score AND action AND fail-closed
if !result.Success {
    return fmt.Errorf("recaptcha: verification failed (errors: %v)", result.ErrorCodes)
}
if result.Score < v.minScore {                    // minScore 0.5
    return fmt.Errorf("recaptcha: score %.2f below threshold %.2f", result.Score, v.minScore)
}
if action != "" && result.Action != action {
    return fmt.Errorf("recaptcha: action mismatch (expected %q, got %q)", action, result.Action)
}
```

Give the HTTP client a timeout (10s is plenty) so a slow siteverify cannot hang the request.

**Two config patterns to check, both of which disable the protection silently:**

- **Enabled-if-configured.** `Enabled = Secret != ""` is convenient for local dev and fail-open in production: forget the secret in the deploy environment and the endpoint quietly accepts anything. Verify the secret is actually set wherever it matters, or make a missing secret fatal at boot in production.
- **A bypass token.** `RECAPTCHA_BYPASS_TOKEN` that skips verification is useful in tests and a backdoor if it reaches production with a value. Confirm it is empty in the production environment, and treat a committed default as a finding.

### Alternatives worth naming

- **Cloudflare Turnstile** — drop-in replacement, no puzzle, and it does not ship personal data to Google. Prefer it when privacy or a data-processing agreement is in scope.
- **Mobile clients: use attestation, not CAPTCHA.** An API consumed by an app should verify **Play Integrity** (Android) or **App Attest** (iOS), or Firebase App Check which wraps both. CAPTCHA in a native app is a bad experience and easily stripped; attestation proves the request came from your real app binary. A well-built OTP flow puts app-check middleware on send *and* verify, alongside the four limits above.

## Reporting these

The table row names the surface and the missing control, not the recommendation:

```
| HIGH | OTP verify has no wrong-guess cap — 6-digit code, unlimited retries | app/Http/Controllers/Api/OtpController.php:88 | 3 failures / 15 min per phone |
| MED  | 12 public write routes with no limiter | routes/api.php:1 | throttle the write group |
| MED  | OTP send costs money and has no daily cap | app/Providers/AppServiceProvider.php:137 | add Limit::perDay keyed by phone |
```

Severity moves with what a request costs and who can reach it. An unthrottled authenticated write is LOW; an unthrottled public endpoint that sends an SMS is MED heading to HIGH, because the impact is someone else's money.
