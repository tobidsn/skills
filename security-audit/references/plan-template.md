# Plan template

Read this only after the human approved a plan. It is self-contained — nothing here needs the `creator-plan` skill, though the section order deliberately matches it so both kinds of plan read the same way in a project.

## Where it goes

```
<audited project root>/docs/security-audit/<YYYY-MM-DD>-<slug>-plan.md
```

The path is relative to **the project you audited**, not to wherever this skill lives. `<slug>` is kebab-case of the audited scope — the repo or service name, or the subsystem when the audit was scoped (`billing-api`, `auth-flow`). Date first, because a second audit of the same project is normal and the directory should sort chronologically.

Get the date from the machine, don't guess it:

```bash
date '+%Y-%m-%d'
```

## The plan is an exploit map — say so when you hand it over

A finished plan names unpatched vulnerabilities, their exact `path:line`, the request that triggers them, and often the leaked credential itself. Until the fixes land, that file is the most useful document an attacker could find in the repo.

So when you write one, tell the human plainly: committing and pushing it while the CRIT findings are still open widens who knows how to exploit them, and a public remote makes that irreversible. Recommend committing it **after** the blockers are closed, or keeping it out of version control until then. Never push it yourself on your own initiative.

The same caution applies to anything else that carries findings out of the session — eval fixtures, issue descriptions, commit messages, screenshots. Redact the project name, the coordinates, and the secret values before they go anywhere public, and keep the vulnerability *class* instead: "a hardcoded signing-secret fallback in the JWT layer" is enough to be useful and useless as a recipe.

## First: learn the project

Read `AGENTS.md` or `CLAUDE.md` — repo root first, then the nearest one to the code — and let it override every default here: the stack, the layout, and above all **how this project builds, tests, and runs**. The `Done when` section has to use the project's real verification commands, and those come from that file, not from a guess. If neither file exists, infer from the manifests and test directories and say so in `Context`.

## The template

```md
# Security fixes — <audited scope>

## Findings

<the audit table, verbatim, all severities>

<the verdict line, and the `Not scanned:` line if there was one>

## Context

- @path/to/file — what it is and its current vulnerable state, one line each
- conventions from AGENTS.md/CLAUDE.md that constrain the fixes (stack, layout, how to build and test)

## Goals

- [CRIT] @path:line — <the outcome, not the steps>
- [HIGH] @path:line — <outcome>

MED/LOW findings above are out of scope for this plan.

## Notes

- ordering constraints where one finding enables another
- secrets that need rotating, not just unhardcoding
- findings whose fix lives upstream in a dependency
- edge cases the executor would miss reading the files cold

## Done when

- <checkable statements using this project's own verification commands>

## Implementation

<!-- executor's working log — leave empty -->

- task-specific, checkable statements of success — use the project's OWN verification: passing tests, a clean build, a real request/command/job run
```

Keep the section order, and keep `## Implementation` as written — the comment and the guidance bullet both stay in the file you hand over. They are the instruction the build phase reads before it starts logging, so deleting them leaves the executor guessing what a finished entry looks like.

## Section guide

**Findings** — the audit table verbatim, *all* severities including the MED/LOW that aren't Goals. This is the plan's evidence base and it replaces the `> Spec:` link a feature plan would carry: the audit was a chat message, so if the plan doesn't embed it, every Goal becomes an unsourced claim a week later. Never trim it to just the Goals.

**Context** — one line per file the fixes touch, naming its current *vulnerable* state, not just its purpose. `@pkg/middleware/jwt.go — issues and validates JWTs; falls back to a hardcoded secret when config is empty` tells the executor why it's here. Every `@path` must already exist; files you plan to create belong in Goals.

**Goals** — CRIT and HIGH only, each tagged with its severity and its `path:line` so it traces back to a row in `## Findings`. One bullet per outcome, not per step: *"`/admin/logs` requires authentication"* is a Goal; *"open router.go, add the middleware, save"* is not. Every CRIT and HIGH row in the table needs a Goal, and the explicit out-of-scope line for MED/LOW keeps the omission deliberate rather than looking like an oversight.

**Notes** — the section that makes a security plan different from a feature plan. Four things belong here:

- **Ordering, when findings chain.** If an unauthenticated file read exposes the `.env` that holds the secret you're about to rotate, the read gets closed first. State the dependency explicitly; the executor works top-down and will not infer it.
- **Rotation.** A secret that reached a repo, a log, a backup, or a world-readable file is compromised the moment it got there. Removing the literal from the code does not un-leak it. Name the credential and say plainly that a human has to rotate it — the executor cannot.
- **Upstream fixes.** When the vulnerable parameter lives in a dependency (a shared library's bcrypt cost, a framework's default), the honest Goal is a wrapper, a pinned fork, or an upstream bump — not an edit to a file in this repo. Say which, so nobody promises a change they cannot make.
- **Blast radius of the fix itself.** Adding authentication to a route that a cron job or another service already calls will break that caller. Adding a rate limit behind a proxy without `trust proxy` set makes every request look like one client.

**Done when** — checkable, and phrased in the project's own verification. Take the commands from AGENTS.md/CLAUDE.md; if the project has a test suite, a build, and a way to run a real request, use those. For security fixes, the strongest form is a check that the old behaviour is now impossible — a request that used to succeed and now returns 403, a grep that used to match and now finds nothing:

```
- `curl -s -o /dev/null -w '%{http_code}' localhost:8080/admin/logs` returns 401, not 200
- `grep -rn '<the literal that was hardcoded>' .` returns nothing
- <the project's test command> passes
```

Prefer that over "the code was changed", which is not verification.

**Implementation** — hand it over holding only the comment and the guidance bullet. The build phase replaces the bullet with what it actually did and what actually verified it, one entry per finding, written as the work happens rather than summarised afterwards.

## Anti-patterns

- **Don't write the implementation.** No code blocks showing the change; Goals name outcomes. The diff belongs to the build phase.
- **Don't trim the findings table** to only the Goals — the MED/LOW rows are the record of what was seen and consciously deferred.
- **Don't make MED/LOW into Goals.** Ten Goals where three matter buries the three.
- **Don't invent files.** Every `@path` in Context must exist.
- **Don't copy generic advice.** "Follow OWASP best practices", "validate all input" — delete. Every line should be about this repo's actual findings.
- **Don't touch git, branches, commits, or PRs**, and don't edit code. This phase writes one Markdown file and stops.

## Updating an existing plan

If a plan for the same scope already exists, edit it in place rather than adding a second file for the same date: preserve whatever `## Implementation` has accumulated, refresh `## Findings` with the new audit, and mark Goals that are now fixed rather than deleting them — a plan that quietly loses a Goal reads as if the finding never existed.
