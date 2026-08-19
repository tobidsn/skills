---
name: security-audit
description: Lightweight security audit — run the project's own dependency audit (npm/pnpm/yarn/bun audit, `composer audit`, `govulncheck`) and grep the high-signal vulnerability classes: SQL injection, command injection, XSS, path traversal, TLS/header gaps, missing rate limits, race conditions. Use when someone asks to audit security, scan dependencies for CVEs, check for vulnerabilities, or review a diff for security problems. Reports one ranked table with `path:line`; it never auto-patches.
---

# security-audit

Two modes. One table out. Nothing else.

## Output contract

Every run ends with exactly this, and stops:

```
| Sev | Finding | Location | Fix |
|---|---|---|---|
| CRIT | SQL concat | app/Repositories/UserRepo.php:88 | bind param |
| HIGH | shell interpolation | cmd/deploy/main.go:41 | exec.Command args |

2 critical/high reachable — blocker.
```

Rules, because the failure mode here is a wall of text nobody reads:

- **Max 10 rows**, ranked CRIT → HIGH → MED → LOW. More than 10 findings: keep the top 10 and say `+N more (MED/LOW)` on the verdict line.
- **Every row needs a real `path:line`.** No location, no row — a finding you can't open is a guess. Dependency findings use the manifest line (`composer.lock:1204`) or the package name when the tool gives no line.
- **`Fix` is a fragment, not a sentence.** `bind param`, `use os.Root`, `throttle:6,1`. Detail lives in `references/patterns.md`, not the table.
- One verdict line: count of reachable CRIT/HIGH, then `blocker` or `clean`.
- No threat-model prose, no STRIDE/DREAD scoring, no checklist dump, no "Summary" paragraph, no next-steps list.

**Never fix without being asked.** No `npm audit fix --force`, no dependency bumps, no edits. Report, then wait. If the user asks for fixes, one finding = one edit, smallest change that closes it.

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

## Mode: deps

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
- **`govulncheck` is not bundled with Go.** If `command -v govulncheck` is empty, install is `go install golang.org/x/vuln/cmd/govulncheck@latest` (needs `$GOPATH/bin` on `PATH`). Ask before installing.

Then triage — advisory count is not the finding, reachability is:

1. **Runtime or dev-only?** Dev-only never blocks a release.
2. **Is the vulnerable function actually called?** `govulncheck` answers this itself (it traces call paths), which makes its findings higher-signal than `npm audit`'s. For the others, grep the vulnerable symbol before you call it CRIT.
3. **Is a patched version out?** No patch available downgrades urgency but is worth naming in `Fix` as `no patch — pin/replace`.

An unreachable critical is a MED row, not a CRIT row. Say why in `Fix`: `dev-only`, `unreachable`.

## Mode: code

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
| MED | Rate limiting | auth/login/reset surfaces with no `php:` `throttle:` · `ts:` `express-rate-limit` · `go:` `x/time/rate` or server timeouts | throttle the auth routes |
| HIGH | Race condition | `php:` check-then-act with no `lockForUpdate\(\)\|Cache::lock` · `ts:` read-modify-write across an `await`, no transaction · `go:` shared map/counter across goroutines | row lock / mutex / atomic |
| LOW | Missing headers | zero hits app-wide for `helmet\|Content-Security-Policy\|Strict-Transport-Security` | security-headers middleware |

Races in Go need a command, not a grep — `go test -race ./...` (findings only from paths the tests exercise).

`Missing headers` is an absence finding — report it once for the app, never per file. Same for rate limiting: one row per unprotected auth surface, not one per route.

## What this skill is not

Not a threat-modeling framework, not a compliance/GDPR review, not a secrets-history scan, and not a fan-out of parallel audit agents. It is one pass, one table. If the user wants a design-level review, say so in a sentence and let them ask.

## Files

One per language, loaded only when its manifest is present. Each is BAD/GOOD for the nine classes above — read it to write a fix, not to produce the table.

- `references/php.md` — read when `composer.json` exists
- `references/typescript.md` — read when `package.json` exists
- `references/go.md` — read when `go.mod` exists
