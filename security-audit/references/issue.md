# Publishing findings to the issue tracker

Two subcommands, reached by typing them or by saying yes when a gate offers them (`issue` at gate 1 as the tracker-shaped phase 2, `tickets` once after an issue publishes) — never unprompted:

- `/security-audit issue` — one spec-shaped issue carrying the whole remediation, agent-grabbable.
- `/security-audit tickets` — the remediation broken into tracer-bullet tickets with blocking edges.

The issue and the local plan file are two shapes of the same phase-2 artifact; produce one, not both, unless asked.

Both publish to the tracker of the project **being audited**, discovered from its own git remote. Publishing an issue is outward-facing and visible to the whole team: **always show the full draft and get an explicit yes before creating anything**, in every mode including auto.

## Content sources — same rule as `report`

Findings come from the audit run in this session; failing that, the newest `docs/security-audit/*-plan.md` and its embedded findings table; failing both, run the audit first and say so. **Never invent a finding to fill a section.** Every claim in the issue traces back to a table row.

## Step 0 — resolve repo and platform

```bash
git config --get remote.origin.url
```

- No remote → stop; say the tracker can't be discovered and show what was checked.
- Parse host + path. SSH (`git@github.com:owner/repo.git`) and HTTPS both occur; strip trailing `.git`; GitLab paths keep all subgroup segments.
- `github.com` → `gh`, `gitlab.com` → `glab`, anything else → stop and say only those two are supported.
- Preflight: `gh auth status` / `glab auth status`. Missing or unauthenticated → stop with the concrete fix (`brew install gh` + `gh auth login`), no fallback.

Pass `--repo <path>` explicitly on every call so behavior doesn't depend on the working subdirectory.

## `issue` — one spec-shaped issue

One issue, structured so an agent (or engineer) can pick it up cold and work it without the audit session. The shape is a spec, not a findings dump:

```md
## Problem Statement
## Solution
## User Stories
## Implementation Decisions
## Testing Decisions
## Out of Scope
## Further Notes
```

Fill procedure, section by section:

- **Title** — `Security remediation: <the finding classes, comma-joined>`. Name the classes (`OTP account-takeover chain, disabled rate limiting, vulnerable dependencies`), not the severities. "3 CRIT findings" tells a reader nothing; the classes tell them everything.
- **Problem Statement** — the CRIT/HIGH findings as *consequence*, dated (`A security audit (<date>) found that …`). Write the attack chain the way an attacker walks it, not the way the grep found it. Dependency findings get their counts (`52 advisories across 17 packages (3 critical, 20 high)`).
- **Solution** — the outcome in one paragraph, plus one sentence on who is unaffected (`Members keep exactly the same login experience; only attackers are affected.`). That sentence is what lets a product owner approve the work.
- **User Stories** — long and numbered, `As a <actor>, I want <control>, so that <benefit>`. Each CRIT/HIGH control becomes at least one story; cover the operator and developer actors too (rate-limit budgets, CI-failing tests, stable response contracts). This section is what makes the issue verifiable — each story is checkable.
- **Implementation Decisions** — the plan's Goals translated to decisions, plus every constraint the audit uncovered (keying rules for rate limits, which flows share the weak RNG, response fields to remove vs keep). When `references/rate-limits.md` was read during the audit, its concrete numbers land here — never re-derive them from memory.
- **Testing Decisions** — external behavior only, name the seam (usually the project's existing feature-test seam) and the prior art in the suite. List the specific assertions per control.
- **Out of Scope** — the MED/LOW findings, named explicitly with a note that they deserve follow-ups. Also anything audited and found fine — saying "already fine" prevents someone re-fixing it.
- **Further Notes** — open design questions the code couldn't answer (the same ones that would pause phase 3), and where the audit evidence lives.

Two rules inherited from the spec discipline:

- **No `path:line` and no code snippets in the issue body** — they go stale the day the branch moves. The evidence with `path:line` lives in the plan file; reference `docs/security-audit/<date>-<slug>-plan.md` when it exists. Exception: a config value or schema fragment that *encodes a decision* (a limiter key, a response shape) may be inlined.
- **Counts must match the findings table.** An issue claiming "3 critical" when the table has 2 destroys trust the same way a wrong verdict line does.

Then publish, after the confirm:

```bash
gh issue create --repo <path> --title "<title>" --body-file <draft> --label ready-for-agent
glab issue create --repo <path> --title "<title>" --description-file <draft> --label ready-for-agent
```

Write the draft to a scratch file and pass `--body-file` — a multi-kilobyte body inline in a shell argument breaks on quoting. If the `ready-for-agent` label doesn't exist, create it first (`gh label create ready-for-agent --description "Fully specified, ready for an AFK agent" --color 0E8A16`) or, when label creation is denied, publish without it and say so.

## `tickets` — tracer-bullet breakdown

For when one issue is too big to land as one PR. Break the remediation into **vertical slices**: each ticket cuts a complete path through every layer (route, middleware/config, tests) and is demoable on its own — never "all the config" in one ticket and "all the tests" in another. Size each to a single fresh context window.

Give each ticket its **blocking edges** — and here the audit already computed them: **the fix-the-chain ordering is the dependency graph** (from the plan's `Notes` when a plan exists, else from the issue's Implementation Decisions, else from the audit's own chain reasoning). The enabler finding blocks the findings it enables (closing the OTP disclosure endpoint blocks the wrong-guess-cap ticket, because the cap is meaningless while codes are readable). Dependency upgrades usually have no blockers and can start immediately.

Before publishing, present the breakdown as a numbered list — title, blocked-by, what it delivers — and ask whether the granularity and edges are right. Iterate until approved; then publish blockers first so later tickets can reference real identifiers:

```md
## Parent

<the spec issue from `/security-audit issue`, when one exists — otherwise omit>

## What to build

<the end-to-end behavior this ticket makes work>

## Acceptance criteria

- [ ] …

## Blocked by

- #<n> <title>, or "None (can start immediately)".
```

Use the platform's native blocking/sub-issue relation where it has one; otherwise the `Blocked by` section is the edge. Apply `ready-for-agent` to each. **Never close or modify a parent issue** — the tickets reference it, they don't consume it.

## Conventions

- MED/LOW findings never become tickets uninvited — same rule as the plan. If the user asks for follow-up issues for them, that's a separate explicit request.
- One confirm covers one publish batch: for `tickets`, the approved breakdown is the confirmation and the batch publishes in dependency order without re-asking per ticket.
- If the local remote and the project the user asked about differ, stop and ask — filing security findings on the wrong repo leaks them.
- A security issue on a **public** repo is a disclosure. When the repo is public, say so in the confirm prompt and let the human decide — that's their call, not a default.
