---
name: security-audit
description: Security audit, then optionally a fix plan and the fixes, each phase gated. Runs the project's own dependency audit (npm/pnpm/yarn/bun, `composer audit`, `govulncheck`) and greps the high-signal code classes: SQL and command injection, XSS, path traversal, weak crypto, TLS and header gaps, unthrottled writes, unbounded OTP attempts, race conditions, missing object-level authorization (IDOR), open registration, client-header trust, and debug tooling exposed in production. Use to audit security, scan dependencies for CVEs, check for vulnerabilities, harden a service before handover, review a diff for security problems, or set rate limits, OTP protections, and bot filtering for an endpoint. Phase one is one ranked `path:line` table and never an edit; code changes always wait for explicit approval. `audit auto` skips only the plan confirmation. `/security-audit report` renders the findings as a self-contained HTML assessment report for stakeholders; `/security-audit issue` publishes them as one agent-grabbable spec issue on the project's GitHub/GitLab tracker; `/security-audit tickets` breaks the remediation into tracer-bullet tickets with blocking edges. All three are manual only, never triggered by a phase.
---

# security-audit

Three phases: **audit** → **plan** → **build**. Phase one is a table and nothing else; the later phases only happen when the human has said so.

Alongside them sit three manual subcommands, never triggered by a phase — see the sections near the end:

- `/security-audit report` — the findings as an HTML assessment report for stakeholders.
- `/security-audit issue` — the findings as one spec-shaped issue on the project's tracker.
- `/security-audit tickets` — the remediation as tracer-bullet tickets with blocking edges.

## Interactive or auto

**Interactive** (default) — audit, then ask before planning, then ask again before editing code. Use this when the request is just "audit this".

**Auto** — audit and write the plan in one pass, no question in between, then stop at the build gate. Enter auto when the request already reaches past the table:

- `/security-audit auto`, `audit auto`, `--auto`
- "audit this and give me a remediation plan", "audit and plan the fixes", "audit + plan"
- "audit this and write up what needs fixing", "review security and draft the fix plan"

The point of auto isn't speed, it's not asking a question the human already answered. If someone asked for a plan in their opening message, stopping to ask whether they want a plan is noise — read the intent and go.

**What auto changes:** exactly one thing, gate 1. Nothing else relaxes.

**What auto never skips:**

- **The build gate.** Auto stops after writing the plan, every time. A plan is a Markdown file you can delete; the fixes are code changes that break callers, lock users out, and encode product decisions. A real audit of a real service turned up a fix that would have locked out every existing user and another that would have silently killed a cron job — neither is a call to make while nobody is looking. Only an explicit, separate "apply the fixes" / "work the plan" opens phase 3.
- **No CRIT or HIGH means no plan.** Auto still checks this first. A plan for three LOW findings is a backlog; say the table is the whole answer and stop. Auto is permission to skip a question, not a reason to manufacture work.
- **Tracing before reporting.** Auto does not license a faster, shallower audit. Same verification, same `path:line` on every row, same `Not scanned:` honesty.

## Phase 1 output contract

The audit ends with exactly this, and stops:

```
| Sev | Finding | Location | Fix |
|---|---|---|---|
| CRIT | SQL concat | app/Repositories/UserRepo.php:88 | bind param |
| HIGH | shell interpolation | cmd/deploy/main.go:41 | exec.Command args |

2 critical/high reachable — blocker.
Not scanned: composer audit unavailable (Composer 2.3).
```

Rules, because the failure mode here is a wall of text nobody reads:

- **Every CRIT and HIGH gets a row — always, however many there are.** Rank CRIT → HIGH → MED → LOW, then let MED/LOW fill the table to about 10 rows and collapse the rest to `+N more (MED/LOW)` on the verdict line. The cap is a readability device for the tail, never something a blocker falls off the end of: truncating a CRIT and labelling it `MED/LOW` on the way out hides the finding *and* misreports its severity. If CRIT and HIGH alone run past 10, the table is longer than 10 rows and that is correct — a repo with twelve blockers has twelve blockers, and the count is itself the finding. Aggregating dependencies to one row per ecosystem already keeps the ordinary case well under the cap.
- **Every row needs a real location, and code rows need `path:line`.** No location, no row — a finding you can't open is a guess. Cite a line number only when *that line is the finding*: `go.mod:3` is right for a missing `toolchain` floor, because the absent directive belongs on that line. An arbitrary lockfile line for "45 advisories across 17 packages" points at nothing — that row's location is `composer.lock`, or the package coordinate (`vendor/package@1.2.3`) when it's one package. Fake precision is worse than none: it survives review because it looks checkable.
- **Aggregate dependency findings to one row per ecosystem.** A repo with 45 composer advisories and 78 npm ones cannot spend the table's row budget on them, and 17 separate package rows would bury the code findings. One row each, carrying the counts that decide urgency (`1 critical + 13 high across 17 packages`). The per-package detail belongs in the plan's provenance block, not the table.
- **`Fix` is a fragment, not a sentence.** `bind param`, `use os.Root`, `throttle:6,1`. Detail lives in the language reference, not the table.
- **The verdict number is literally the count of CRIT + HIGH rows in the table.** Count the rows; don't estimate. A verdict that disagrees with its own table destroys trust in every other number.
- **`Not scanned:` is one optional line for what you could not check** — a missing scanner, an unreadable dependency, a path you were denied. Use it whenever it applies: silence reads as "clean", and a scan that didn't happen is not a clean scan. One line, no elaboration.
- No threat-model prose, no STRIDE/DREAD scoring, no checklist dump, no "Summary" paragraph, no next-steps list.

**Never edit code in phase 1.** No `npm audit fix --force`, no dependency bumps, no edits at all. Report, then ask.

## Detect first, then load

One pass over the repo root decides both what to run and what to read. Do this before anything else:

```bash
ls composer.json package.json go.mod 2>/dev/null
ls package-lock.json pnpm-lock.yaml yarn.lock bun.lock bun.lockb composer.lock go.sum 2>/dev/null
```

| Manifest found | Reference to load | Audit to run |
|---|---|---|
| `composer.json` | `references/php.md` | `composer audit` |
| `package.json` | `references/typescript.md` | the Node-family audit for its lockfile |
| `go.mod` | `references/go.md` | `govulncheck` |

**Load only the files that matched.** A Go service reads `go.md` and nothing else. Never read all three — that is the whole reason they're split.

**A project can match more than one, and usually does.** A Laravel app with a Vite/Inertia frontend has `composer.json` *and* `package.json`: run **both** audits, read **both** reference files, and merge everything into the single output table. Same for a Go service with an embedded JS admin panel.

## Scanning dependencies

Run every ecosystem you detected — not just the first one. Within one ecosystem, pick the command from the committed lockfile; never assume npm because `package.json` exists.

| Lockfile | Command |
|---|---|
| `package-lock.json` | `npm audit --omit=dev` |
| `pnpm-lock.yaml` | `pnpm audit --prod` |
| `yarn.lock` | `yarn npm audit --environment production` (Berry) / `yarn audit --groups dependencies` (v1 — check `yarn -v` first) |
| `bun.lock`, `bun.lockb` | `bun audit` |
| `composer.lock` | `composer audit --locked --no-dev` (add `--format=summary` only on Composer ≥ 2.7) |
| `go.sum` | `govulncheck ./...` |

**Competing lockfiles are a HIGH finding — but only within one ecosystem.** `package-lock.json` next to `pnpm-lock.yaml` means a non-reproducible install; report it and say which one CI actually uses. `composer.lock` next to `package-lock.json` is two different ecosystems and completely normal — never flag that.

In a monorepo, the lockfile at the workspace root owns the install; don't audit each package directory separately. A nested project only counts as its own root when it sits outside that workspace.

Two gates that fail confusingly if you skip them:

- **`composer audit` needs Composer ≥ 2.4.** Run `composer --version` first. Older: report `composer audit unavailable (Composer <2.4)` as a LOW row and fall back to `local-php-security-checker --path=composer.lock`, or advise upgrading Composer — do not upgrade it yourself.
- **`govulncheck` is not bundled with Go, and you don't need to install it.** When `command -v govulncheck` is empty, run it without touching the machine:

  ```bash
  go run golang.org/x/vuln/cmd/govulncheck@v1.1.4 ./...
  ```

  This leaves no binary on `$PATH` and needs no permission to install anything. Prefer it over `go install` every time — reach for `go install golang.org/x/vuln/cmd/govulncheck@latest` only if the user asks for the tool permanently, and ask first. If `go run` can't fetch the module (offline, no cache, restricted proxy), say so on the `Not scanned:` line rather than reporting the dependencies as clean.

  `govulncheck` also covers the **standard library at the toolchain that builds the code**, which is a finding class no lockfile shows. When it reports stdlib vulnerabilities, check whether CI pins a newer Go than the local toolchain and whether `go.mod` has a `toolchain` directive holding the floor — a repo where CI is clean and dev machines are not is a real MED finding at `go.mod`.

Then triage — advisory count is not the finding, reachability is:

1. **Runtime or dev-only?** Dev-only never blocks a release.
2. **Is the vulnerable function actually called?** `govulncheck` answers this itself (it traces call paths), which makes its findings higher-signal than `npm audit`'s. For the others, grep the vulnerable symbol before you call it CRIT.
3. **Is a patched version out?** No patch available downgrades urgency but is worth naming in `Fix` as `no patch — pin/replace`.

An unreachable critical is a MED row, not a CRIT row. Say why in `Fix`: `dev-only`, `unreachable`.

## Scanning code

Default target is the working diff (`git diff --name-only` plus staged); a path argument overrides it. On "audit the whole project", scope to the app source dirs and skip `vendor/`, `node_modules/`, `dist/`, generated code.

Grep signals below are the entry point, not the verdict. **Trace before you report**: follow the value back to where it enters the system, and check for validation upstream. Input that passed a strict parser is MED, not CRIT — note the upstream defense in `Fix`.

Signals are tagged by language — **run only the tags you detected.** A Go-only repo runs the `go:` rows and skips the rest.

| Sev | Class | Grep signal | Fix |
|---|---|---|---|
| CRIT | SQL injection | `php:` `whereRaw\|selectRaw\|DB::(raw\|select\|statement)` containing `$` · `ts:` `` query(`…${ `` , `queryRawUnsafe` · `go:` `fmt.Sprintf` feeding `db.Query\|db.Exec` | bind param / placeholder |
| CRIT | Command injection | `php:` `shell_exec\|passthru\|proc_open\|system\(` · `ts:` `child_process\.exec\(` , `shell:\s*true` · `go:` `exec.Command\("(sh\|bash)", "-c"` | pass args separately |
| HIGH | XSS | `php:` `{!! !!}` · `ts:` `innerHTML\|dangerouslySetInnerHTML\|v-html` · `go:` `"text/template"` import in a web handler, `template.HTML\(` | auto-escape / sanitize |
| HIGH | Path traversal | `php:` request input reaching `file_get_contents\|fopen\|include` · `ts:` `path.join\(` with `req\.` · `go:` `filepath.Join` with user input | `os.Root` (Go 1.24+) / resolve + prefix check |
| HIGH | Weak crypto / RNG | `php:` `md5\(\|sha1\(` on passwords, `mt_rand` · `ts:` `Math.random` for tokens · `go:` `"math/rand"` for tokens · all: `==`/`===` comparing secrets | argon2id/bcrypt · CSPRNG · constant-time compare |
| MED | TLS disabled | `php:` `withoutVerifying\|'verify'\s*=>\s*false` · `ts:` `rejectUnauthorized:\s*false` , `NODE_TLS_REJECT_UNAUTHORIZED` · `go:` `InsecureSkipVerify:\s*true` | remove the override, pin the CA |
| HIGH | Unbounded verify attempts | OTP/2FA/reset verify with no cap on *wrong* guesses — a request-rate limiter alone does not count failures | 3 failures / 15 min per identity |
| MED | Unthrottled public write | public `POST\|PUT\|PATCH\|DELETE` with no `php:` `throttle:` · `ts:` `express-rate-limit`, `@Throttle` · `go:` `x/time/rate` — and no server timeouts | throttle the write surface |
| HIGH | Race condition | `php:` check-then-act with no `lockForUpdate\(\)\|Cache::lock` · `ts:` read-modify-write across an `await`, no transaction · `go:` shared map/counter across goroutines | row lock / mutex / atomic |
| CRIT | Missing object-level authz (IDOR) | `php:` implicit binding / `find($id)` in a controller with zero `authorize\|->can\|Policy` and no owner scope · `ts:` `findUnique({ where: { id: req.params… } })` with no user filter · `go:` DB fetch by URL id with no owner column in the `WHERE` | scope query to owner / policy |
| HIGH | Missing endpoint auth | absence class — enumerate routes, subtract the ones behind auth: API groups, uploads, exports, leftover test/debug routes | auth middleware / delete route |
| HIGH | Open registration | public `register\|signup` handler that auto-logs-in on an admin/internal app, new user reaching shared data | invite-only / approval flag |
| HIGH | Client header as authz | `Origin\|Referer\|X-Forwarded-` read to *grant* access, or a whitelist that passes when empty | server-side credential; fail closed |
| HIGH | Debug surface in prod | `php:` ignition/telescope/horizon in `require` (not `require-dev`), docs route gated only by `auth()->check()` · `ts:` public swagger/GraphQL playground, verbose error handler · `go:` `net/http/pprof` import on the main mux | gate, remove, move to dev deps |
| LOW | Missing headers | zero hits app-wide for `helmet\|Content-Security-Policy\|Strict-Transport-Security` | security-headers middleware |

Races in Go need a command, not a grep — `go test -race ./...` (findings only from paths the tests exercise).

`Missing headers` is an absence finding — report it once for the app, never per file. Same for the throttling rows: one row per unprotected surface, not one per route.

**Absences cannot be grepped, so enumerate then subtract.** Grepping `throttle:` finds the routes that *are* protected and says nothing about the ones that aren't — and a handful of hits reads as "handled". List the write endpoints, then look at which ones have neither a limiter nor authentication:

```bash
php artisan route:list --method=POST --json     # Laravel: check the middleware column
grep -rEn "\.(post|put|patch|delete)\(" src/    # Express/Nest: compare against limiter mounts
```

**The same subtraction finds the two authorization absences.** First pass, endpoint auth: from the route list, which routes have no auth middleware at all — API groups, upload endpoints, exports, leftover `test`/`debug` routes? Each unauthenticated route that touches data is its own row. Second pass, object-level auth: `auth` on every route proves the caller is *logged in*, not that they're *allowed* — for each route that loads a record by a URL id, check that something ties the record to the caller (a policy, an `authorize()` call, or an owner-scoped query). An app with route model binding everywhere, an owner relation on the model, and zero policies is one CRIT row: any registered user can read every tenant's data by guessing ids. An open `register` endpoint next to that IDOR upgrades "logged in" to "anyone on the internet" — report both rows and name the chain in `Fix`.

When either throttling row fires, or the endpoint is public and costs money per request (SMS, email, a paid API), read `references/rate-limits.md`: the recommended limits and how to key them, the four controls an OTP flow needs, and how to pick a bot filter by client type — Turnstile for web, attestation for mobile apps. Don't invent limits from memory, and don't name a bot filter before you know whether the client is a browser or an app: an OTP cap guessed wrong is either someone's SMS bill or a locked-out user base, and "add reCAPTCHA" to a mobile-only API is the wrong fix at the wrong layer.

### Open the wrapper: a correct primitive name is not a correct configuration

The table above lists `argon2id/bcrypt` and `crypto/rand` as the *fixes*, which makes them look like proof of safety when you grep and find them. They are not. **The parameters that decide whether a primitive is safe live in the call, and often one level below it** — in a project helper or a shared library, not at the site you grepped.

When hashing, encryption, token generation, or signing is delegated to a wrapper (`Hash::make`, `bcryptService.HashPassword`, `supports.Bcrypt`, an internal `crypto` package), **open the wrapper and read the parameters.** Follow it into `vendor/` or the module cache if that's where it lives:

```bash
grep -rn "func.*HashPassword\|GenerateFromPassword\|argon2\.\|bcrypt\." $(go env GOMODCACHE)/<module>@<version>/ 2>/dev/null
grep -rn "password_hash\|PASSWORD_" vendor/<vendor>/<pkg>/
```

What to check once you're there: bcrypt cost (`MinCost` is **4**; the default is 10 and below ~10 is a HIGH finding), argon2 memory/time parameters, AES mode (GCM vs bare CBC/ECB), and whether the RNG is the crypto one. A helper named `Bcrypt` calling `GenerateFromPassword(pw, bcrypt.MinCost)` passes every grep in the table above and is still a real finding.

This generalizes past hashing: a name that matches the GOOD column is a reason to look closer, never a reason to stop. Report the finding at the wrapper's own `path:line` and note in `Fix` when it's upstream and cannot be changed in-repo.

## Gate 1: ask before planning

**Interactive mode only — auto mode skips straight to phase 2.**

After the table, ask once — a real question with options, not a rhetorical one. Nothing is written until the answer comes back:

> Want a fix plan for the CRIT/HIGH findings, or is the table enough?

Ask it in whatever language the conversation is already in — this is shown to a human, not matched against a pattern.

Offer three: **write the plan**, **fix now without a plan** (they accept the fixes as listed), **just the table**. If there are no CRIT or HIGH rows, don't ask at all — say the table is the whole answer and stop. A plan for three LOW findings is a backlog, not a plan.

The gate exists because a security fix often has several legitimate shapes with different trade-offs, and that choice belongs to the human. It is a cheap question, so ask it — unless they already answered it by asking for a plan up front, which is what auto mode is for.

## Phase 2: the plan

Only after a yes. Read `references/plan-template.md` and follow it. In short:

- One file at `docs/security-audit/<YYYY-MM-DD>-<slug>-plan.md`, relative to the **project root being audited** — not this skill's repo. `<slug>` is kebab-case of the audited scope.
- **Goals cover CRIT and HIGH only.** MED/LOW stay in the embedded findings table, out of scope, said so explicitly. Ten Goals where three of them matter buries the three.
- The findings table is **embedded in the plan** under `## Findings`. The audit lived in a chat message; if the plan doesn't carry its own evidence, every Goal becomes an unsourced assertion the week after.
- No code in the plan. Goals name outcomes; `## Implementation` stays empty for the build phase to fill.

Then stop and ask again before touching code.

## Gate 2 and phase 3: the build

**This gate holds in every mode, including auto.** Ask before the first edit — and stop again, mid-build, if a Goal turns out to need a decision the code cannot answer (an unused `Status` flag with no activation flow behind it, a route with unknown callers). Guessing there is worse than pausing.

Then work the plan, CRIT before HIGH, **in the order `Notes` gives** when it names an ordering constraint — that order can differ from severity order, and following severity blindly can undo the fix you just made:

- **One finding = one edit.** Never combine two findings in one change — a revert has to be able to undo exactly one finding.
- **Fix the chain in order.** When one finding enables another, the enabler goes first. Closing a hardcoded secret while an unauthenticated file read still exposes `.env` fixes nothing.
- **Verify each fix with a real command** and record it: a build, the test suite, a race detector run, or a grep proving the old pattern is gone. Log it under `## Implementation` as you go — that section is the working log, written during the build, not summarized after.
- **Say what you did not fix and why.** An upstream dependency finding (a wrapper's bcrypt cost in a shared library) may need a wrapper or an upstream bump rather than an edit here. Promising an edit you can't make is worse than naming the constraint.
- **Rotate, don't just unhardcode.** A secret that reached a repo, a log, or a readable file is compromised; removing the literal doesn't un-leak it. Flag rotation as a step the human must do — you cannot do it for them.

## `/security-audit report` — the stakeholder report

A side door, not a fourth phase. **Only ever runs when someone types it** — no phase produces it, no gate offers it, and a clean audit does not trigger it.

It exists because the plan cannot serve the other reader. A plan is a work order — Goals, `path:line`, verification commands — and a client or delivery lead reading it still can't answer *"can we ship, and what are we accepting if we do?"* The report answers that: same findings, risk stated as consequence rather than mechanism, and the limits of the review promoted from a trailing line to a section.

Output is one self-contained HTML file at `<audited project>/docs/security-audit/<YYYY-MM-DD>-<slug>-report.html` — no network, opens anywhere, prints to PDF with page breaks and a `CONFIDENTIAL` footer already handled.

Content comes from the audit run in this session; failing that, the newest `docs/security-audit/*-plan.md` and its embedded findings; failing both, run the audit first and say so. Never invent a finding to fill a field.

Read `references/report.md` before writing it — it carries the fill procedure, the severity and status mapping, and two mandatory guards. Both guards matter more than they look: the template ships with two **sample findings** that must be deleted, and `grep -n "SAMPLE\|{{"` on the finished file must come back empty. A surviving sample block delivers a fabricated critical to a client.

## `/security-audit issue` and `/security-audit tickets` — the tracker

Two more side doors, same rules as `report`: **only ever run when someone types them**, and content comes from the audit run in this session, else the newest plan file, else run the audit first. Read `references/issue.md` before either — it carries the repo/platform resolution (git remote → `gh` or `glab`), the fill procedures, and the publish commands.

`issue` publishes **one spec-shaped issue** — Problem Statement / Solution / User Stories / Implementation Decisions / Testing Decisions / Out of Scope / Further Notes, labelled `ready-for-agent` — written so an agent or engineer can work the remediation without ever seeing the audit session. CRIT/HIGH drive the spec; MED/LOW are named in Out of Scope. No `path:line` in the body (it goes stale — the plan file carries the evidence and gets referenced instead).

`tickets` breaks the remediation into **tracer-bullet vertical slices with blocking edges** — the fix-the-chain ordering from the plan *is* the dependency graph. The breakdown is shown as a numbered list and iterated until approved, then published blockers-first so edges reference real issue numbers. It references the spec issue as parent when one exists, and never closes or modifies it.

**Publishing is outward-facing, so it always gates** — full draft shown, explicit yes, in every mode including auto. On a public repo, the confirm prompt must say the issue will be a public disclosure of the vulnerabilities.

## What this skill is not

Not a threat-modeling framework, not a compliance/GDPR review, not a secrets-history scan, and not a fan-out of parallel audit agents. One pass, one table, then only what the human approves. If they want a design-level review, say so in a sentence and let them ask.

## Files

Loaded on demand, never all at once.

- `references/php.md` — read when `composer.json` exists
- `references/typescript.md` — read when `package.json` exists
- `references/go.md` — read when `go.mod` exists
- `references/rate-limits.md` — read when a throttling row fires, an OTP/verification flow is in scope, or a public endpoint costs money per request
- `references/plan-template.md` — read only after the human approves a plan
- `references/report.md` — read only on `/security-audit report`; it points at `assets/report-template.html`, which is copied and filled in place rather than read into context
- `references/issue.md` — read only on `/security-audit issue` or `/security-audit tickets`; repo/platform resolution, the spec-issue and ticket templates, publish commands and gates

The three language files are BAD/GOOD for the nine classes above; read one to write a fix, not to produce the table.
