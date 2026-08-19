# Plan template

Read this after the trace is done, before writing the file. It is self-contained: the template, what each section is for, and one worked example end to end. The method that produces it lives in `plan-mode.md`; the notation lives in `SKILL.md`.

## Where it goes

```
docs/plans/<YYYY-MM-DD>-<slug>.md
```

`<slug>` is kebab-case of the title. Date first, because planning the same area twice is normal and the directory should sort chronologically. Take the date from the machine — don't guess it:

```bash
date +%F
```

So "OTP on login" becomes `docs/plans/2026-08-19-otp-on-login.md`.

## The template

````md
# <task title>

## Context

- @path/to/file — what it is + its current state, one line each
- conventions from AGENTS.md/CLAUDE.md that constrain this task (stack, layout, how to run/test)
- scope traced: <specific start> → <specific stop>

## Graph

graph: <flow name>

```ts
<the traced as-is path — every node verified>
```

src:
  <Node> → path/from/repo/root:LINE
  <Node> → path/from/repo/root:LINE

graph: [proposed] <flow name>

```ts
<the target shape — unchanged nodes kept, new work marked [new], no line numbers>
```

## Goals

- <outcome — what gets created / changed / removed>

## Notes

- flow-wide facts from the trace: cardinality, ordering, dead branches
- gotchas the executor would miss reading the files cold

## Done when

- checkable statements of success, using this project's own verification

## Implementation

<!-- executor's working log — leave empty -->
````

Keep the section order, and keep `## Implementation` exactly as written — the heading and its comment both stay in the file you hand over. They tell the executor where to log, so deleting them leaves them guessing.

## Section guide

**Title** — a short readable noun phrase. The filename is `<date>-<kebab-case-title>`.

**Context** — one line per file the work touches, naming its *current* state and not just its purpose: `@app/Http/Controllers/Api/LoginController.php — issues a Sanctum token; no attempt throttling` tells the executor why it's listed. Then the conventions from `AGENTS.md`/`CLAUDE.md` that constrain the work. Then `scope traced:` — the exact start and stop, so the user can tell you immediately if you traced the wrong slice. Every `@path` here must already exist; files you plan to create belong in Goals.

**Graph** — the two graphs, as-is first. This is the section the executor reads twice, and the reason the plan exists in this form. The as-is graph carries a `src:` block; the `[proposed]` graph carries none. If there's nothing to trace, one line saying so replaces the as-is graph — see `plan-mode.md`.

**Goals** — concrete deliverables: **created** / **changed** / **removed**, one bullet per outcome rather than per step. *"Login rejects a 6th attempt within the minute with 429"* is a Goal; *"open the controller, add the middleware, save"* is not. Each `[new]` or changed node in the proposed graph should be findable in a Goal, and vice versa — that correspondence is what stops the graph and the prose from drifting apart. Naming the node in the bullet makes the link explicit and costs nothing.

**Notes** — the flow-wide findings from the trace (cardinality, ordering and timing, conditions and dead branches), plus anything easy to miss reading the files cold: edge cases, data migrations, env and config, ordering constraints between the Goals, backwards compatibility, and who else calls the path you're about to change. That last one is worth checking every time — adding a limit or a guard to a path a cron job or another service already calls will break that caller, and the trace is where you'd have noticed.

**Done when** — checkable, and phrased in this project's own verification. Take the commands from `AGENTS.md`/`CLAUDE.md`: a test command, a build, a real request or job run. The strongest form is a check that the old behaviour is now impossible — something that used to succeed and now doesn't:

```
- `for i in $(seq 1 6); do curl -s -o /dev/null -w '%{http_code} ' -X POST localhost/api/login -d @creds.json; done` prints five non-429 codes then 429
- <the project's test command> passes
```

Prefer that over "the code was changed", which is not verification. If the project genuinely has no test runner and no way to run a request, say so here rather than inventing a command.

**Implementation** — empty on handover. The executor's log, written as the work happens.

## Worked example

A finished plan for a small change. The line numbers below are illustrative — in a real plan every one of them came from a file you opened.

````md
# Login attempt rate limit

## Context

- @routes/api.php — API route table; `/login` is registered outside any throttle group
- @app/Http/Controllers/Api/Auth/LoginController.php — invokable controller, delegates to the action
- @app/Actions/Auth/AuthenticateUser.php — verifies credentials and issues a Sanctum token
- @app/Http/Kernel.php — `api` middleware group; `throttle:api` maps to a 60/min limiter
- CLAUDE.md: final classes, `declare(strict_types=1)`, business logic in `app/Actions/{Resource}/`, verify with `php artisan test`
- scope traced: POST /api/login → the point a personal access token is persisted

## Graph

graph: login request

```ts
Route::post('/login')
  → {middleware} Kernel::$middlewareGroups['api']
    → throttle:api
      ? limit: 60/min per IP — not per email, and not login-specific
    → SubstituteBindings
  → LoginController.__invoke
    → LoginRequest.validated
      ? invalid: fatal — 422 before the action runs
    → AuthenticateUser.handle
      → Hash::check
        ? mismatch: fatal — throws ValidationException, 422
      → User.createToken
        ! writes a row to personal_access_tokens on every success
        → {db:personal_access_tokens}
```

src:
  Route::post('/login') → routes/api.php:24
  Kernel::$middlewareGroups['api'] → app/Http/Kernel.php:38
  throttle:api → app/Providers/RouteServiceProvider.php:52
  SubstituteBindings → app/Http/Kernel.php:41
  LoginController.__invoke → app/Http/Controllers/Api/Auth/LoginController.php:19
  LoginRequest.validated → app/Http/Requests/Auth/LoginRequest.php:27
  AuthenticateUser.handle → app/Actions/Auth/AuthenticateUser.php:22
  Hash::check → app/Actions/Auth/AuthenticateUser.php:29
  User.createToken → app/Models/User.php:44

graph: [proposed] login request

```ts
Route::post('/login')
  → {middleware} Kernel::$middlewareGroups['api']
    → throttle:api
    → SubstituteBindings
  → [new] {middleware} throttle:login
    ? over limit: fatal — 429 with Retry-After, action never runs
  → LoginController.__invoke
    → LoginRequest.validated
    → AuthenticateUser.handle
      → Hash::check
      → [new] LoginAttemptLimiter.clear
      → User.createToken
        → {db:personal_access_tokens}
```

## Goals

- created: a `login` rate limiter, 5 attempts per minute keyed on email + IP — the `[new] throttle:login` node
- changed: `/login` in @routes/api.php runs behind `throttle:login` in addition to the `api` group
- created: `LoginAttemptLimiter.clear` — the `[new]` node that resets the counter on a successful authentication, so a legitimate user isn't locked out by their own earlier typos
- changed: the 429 response carries `Retry-After`

## Notes

- `throttle:api` at 60/min per IP already exists but is the wrong instrument here: shared-NAT offices hit one bucket, and 60 guesses a minute against one email is not a limit worth having. The new limiter keys on email + IP so neither dimension alone is the whole key.
- Ordering: register the limiter in the service provider before adding the alias to the route, or the route resolves against a limiter that doesn't exist yet and every request 500s.
- Cardinality: `User.createToken` writes a row per successful login and nothing prunes them. Out of scope here, but the table grows unbounded — worth its own plan.
- Behind a load balancer, per-IP keying needs `TrustProxies` configured or every request looks like one client and the limit fires for everyone at once.
- The mobile app retries a failed login automatically twice. Five attempts per minute leaves a real user three genuine tries, which is tight — flag to product before shipping.

## Done when

- `php artisan test` passes, including a new feature test asserting the 6th attempt in a minute returns 429
- six sequential `POST /api/login` with a wrong password return five 422s then a 429 carrying `Retry-After`
- a successful login followed immediately by five wrong attempts still returns 422 on the fifth, proving the counter cleared

## Implementation

<!-- executor's working log — leave empty -->
````

Notice what the example does *not* do: no code blocks showing the middleware being written, nothing in `Done when` that can't actually be run, no `//` comments in the trees, and no line numbers on either `[new]` node.

## Template anti-patterns

The mode-wide refusals are in `plan-mode.md`. These are specific to filling in the document:

- **Don't let the graphs and the Goals drift.** A `[new]` node with no Goal is speculation; a Goal with no node means the graph is incomplete. Check the correspondence in both directions before you save.
- **Don't leave `Done when` generic.** "It works", "tests pass" with no test named, "the code is clean" — none of those are checkable.
- **Don't delete the `Implementation` heading or its comment** to tidy up the file. They're instructions for the next person.
- **Don't describe a file in Context by its purpose alone.** Its *current state* is why it's listed.
