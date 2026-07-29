---
name: workflow
description: Spec-driven, domain-agnostic orchestrator for one unit of work in any project — bug fixes, brand-new features, and improvements to existing code. Drives a task through spec → plan → build → verify → done (spec mandatory for new features, skipped by default for fix/improve), reading the project's AGENTS.md / CLAUDE.md for stack and verification conventions. Delegates spec-writing to `creator-spec` and plan-writing to `creator-plan`. Dispatched via `/workflow <slug> <prompt>`, `/workflow spec`, `/workflow plan`, `/workflow next`, `/workflow verify`, `/workflow status`, `/workflow done`. Use whenever the user types `/workflow ...`, or says "buatkan workflow", "mulai workflow", "kerjakan task X", "perbaiki bug", "benerin", "bikin fitur baru", "tambah endpoint", "improve ini", "lanjutkan task", or "cek status workflow".
---

# /workflow — Spec-driven workflow (domain-agnostic)

A thin coordinator for one unit of work in any project. It does **not** design the solution itself — it delegates the spec to `creator-spec`, drafts the plan from it via `creator-plan`, calls the right implementation skills, runs the project's verification against the spec, and tells the user what's next.

## First: learn the project

This skill assumes nothing about the stack. At the start of any task, read **`AGENTS.md` or `CLAUDE.md`** (repo root, then nearest to the work) — it defines the language, layout, how to build/test/run, and conventions. Every "how do I build / how do I verify" decision below comes from there. If neither file exists, infer from the code (manifests, test dirs, scripts) and note your assumptions.

Artifacts live at `docs/spec/<slug>.md` (the contract) and `docs/plan/<slug>.md` (the plan).

## Spec-driven, scaled to the task

The **spec** is the contract: WHAT must be true and WHY (requirements + acceptance criteria). The **plan** is HOW: files, approach, order. Build implements the plan; verify checks back against the spec's acceptance criteria.

```
spec (contract)  →  plan (how)  →  build  →  verify (check back to spec)
   ▲                                                │
   └──────────────────── fail → return here ────────┘
```

Spec is **mandatory for `new`** and **skipped by default for `fix`/`improve`** — a trivial fix shouldn't pay for a spec. Rule of thumb: if the work can be described and verified in one sentence, skip the spec. A `fix`/`improve` task can opt in later with `/workflow spec <slug>`.

## Three use cases (intent)

Detect intent from the prompt. If ambiguous, ask `Intent? (fix/new/improve)`. The user can force it with `<intent>:<slug>`, e.g. `/workflow new:export-endpoint "CSV export endpoint"`.

| Intent    | Trigger keywords                                                              | Spec?            | Meaning                                        |
| --------- | ----------------------------------------------------------------------------- | ---------------- | ---------------------------------------------- |
| `fix`     | fix, bug, broken, rusak, benerin, perbaiki, salah, error, crash, regression   | skipped (opt-in) | repair something wrong in existing code        |
| `new`     | new, create, add, build, bikin, buat baru, fitur baru, endpoint, command, job | **mandatory**    | build a new feature / module / endpoint / page |
| `improve` | improve, refactor, polish, enhance, optimize, tingkatkan, rapikan, ubah       | skipped (opt-in) | refine existing code or a single component     |

## Phases

| Phase    | Detection                                                       | What "next" means                        |
| -------- | --------------------------------------------------------------- | ---------------------------------------- |
| `idle`   | no spec and no plan for the slug                                | `/workflow <slug> <prompt>` to start     |
| `spec`   | `docs/spec/<slug>.md` exists, no plan yet (`new`, or opted-in)  | `/workflow plan`                         |
| `plan`   | `docs/plan/<slug>.md` exists, no source changed for the task    | `/workflow build`                        |
| `build`  | source files changed for this task (see **Git** below)          | `/workflow verify`                       |
| `verify` | build done, checks not yet confirmed                            | run the checks, then `/workflow done`    |
| `done`   | verify passed                                                   | `/workflow done` to close out            |

## Git

Everything here is conditional on `git rev-parse --is-inside-work-tree` succeeding. **If it fails, the project isn't a git repo — skip this whole section and never run `git init`.**

### Branch per task

At start, if the repo is clean and HEAD is on the project's default branch, create a branch for the task and switch to it. Pick the name in this order:

1. A branch convention named in AGENTS.md/CLAUDE.md — always wins.
2. Otherwise the shape already in use: `git branch --sort=-committerdate --format='%(refname:short)' | head -20`.
3. Otherwise map the intent: `fix` → `fix/<slug>`, `new` → `feat/<slug>`, `improve` → `refactor/<slug>`.

Report the branch you created. If HEAD is **already** on a non-default branch, stay on it and say so — the user may have prepared it deliberately.

**If the working tree is dirty, stop and ask before creating or switching branches.** Uncommitted work belongs to the user; don't stash, reset, checkout over it, or "clean up" first.

### Changed-files signal

Phase `build` is detected from git, not from memory of what you edited. Task files = tracked changes minus the workflow's own artifacts:

```bash
DEFAULT=$(git symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null || echo main)
BASE=$(git merge-base HEAD "$DEFAULT")
{ git diff --name-only; git diff --cached --name-only; \
  git log --name-only --pretty=format: "$BASE"..HEAD; } \
  | sort -u | grep -Ev '^(docs/(spec|plan)/|$)'
```

Non-empty → the task has reached `build`. Empty while a plan exists → still `plan`. Use the same list for the `done` summary of what changed.

### Commit

Committing happens **only** at `/workflow done`, and **only** after the user says yes to that specific commit. Match the repo's existing message style — read `git log --oneline -20` first; don't impose Conventional Commits on a repo that doesn't use it. Stage the source changes and the spec/plan together: the artifacts are part of the work.

**Push and PRs are never part of `done`.** Do them only when the user asks for them in their own words, in that turn.

## Sub-command dispatch

Parse the first token after `/workflow`: a bare word that isn't a known sub-command → treat as `<slug>` for the start flow; otherwise `spec` / `plan` / `next` / `verify` / `status` / `done`. Empty `args` → `status`.

`next` is a phase-aware alias: it runs the command in the **What "next" means** column for the current phase. So `/workflow next` resolves to `/workflow plan` at `spec`, `/workflow build` at `plan`, `/workflow verify` at `build`, and `/workflow done` at `verify`.

---

## `/workflow <slug> <prompt>` — start

1. **Validate.** `<slug>` required (kebab-case; reject spaces/uppercase). `<prompt>` is everything after — one sentence of intent. If missing, ask for it.
2. **Read AGENTS.md/CLAUDE.md** to learn the stack and how this project builds/tests/verifies.
3. **Detect intent** (table above), or honor an explicit `<intent>:<slug>`.
4. **Create the task branch** per **Git → Branch per task** (skipped if not a repo; stop and ask if the tree is dirty).
5. **Branch on intent:**
   - **`new` → spec-first.** Invoke `creator-spec` with the slug as title and the prompt as detail → `docs/spec/<slug>.md` (`creator-spec` stamps `started`). Phase `spec`. Next: `/workflow plan`.
   - **`fix` / `improve` → spec skipped.** Invoke `creator-plan` with the slug as title and the prompt as detail → `docs/plan/<slug>.md` (it stamps `started`). Phase `plan`. Next: `/workflow build`. (Add a spec later with `/workflow spec <slug>` if it turns out to need one.)
6. **Report:** intent, branch, artifact path, phase, next command. Point at the path; don't paste the file into chat.

---

## `/workflow spec [request]` — write or refine the spec

Invoke `creator-spec` to write or refine `docs/spec/<slug>.md`. The mandatory entry point for `new`; an explicit opt-in for `fix`/`improve`.

- **Creating:** `creator-spec` fills the spec template and stamps `started` — the **spec owns the time clock** for the task.
- **Refining:** pass `[request]` to `creator-spec` to edit in place; it never overwrites `started`. Resolve every **Open Question** before `/workflow plan` — an unresolved question means the plan would be guessing.

The spec is the **contract** — WHAT must be true and WHY (requirements + acceptance criteria), never how to build it (that's the plan). `creator-spec` reads the project's AGENTS.md/CLAUDE.md so the acceptance criteria lean on the project's real verification; this skill just dispatches it and tracks the phase.

---

## `/workflow plan [request]` — derive the plan

Invoke `creator-plan` to write `docs/plan/<slug>.md`.

- **If a spec exists**, `creator-plan` reads it and every Goal traces to a requirement (cite `R#`). The plan must not contradict the spec; if building proves it wrong, fix the spec first (`/workflow spec`), then re-plan. The **spec owns the clock**, so the plan has no Manhours block.
- **If no spec** (`fix`/`improve` that skipped it), `creator-plan` drafts the plan straight from the prompt and stamps `started` in the plan.
- If `docs/plan/<slug>.md` already exists and `[request]` is given, pass it to `creator-plan` to revise in place (preserve the `Implementation` section).

End state: phase `plan`. Next: `/workflow build`.

---

## `/workflow build` (or `/workflow next` at `plan`) — implement

Implement the plan's Goals using the conventions and stack from AGENTS.md/CLAUDE.md, and the implementation skills relevant to that stack (language reviewers, framework patterns, design skills — whatever the project calls for). Follow existing patterns in the code; don't introduce new tools or structure the project doesn't already use.

For a larger multi-file build, dispatch the project's build subagent (if it has one) with the spec + plan paths so the orchestrator's context stays clean.

After the first edit, phase becomes `build`. When done, run `/workflow verify`.

---

## `/workflow verify` — gate

Verify with the project's **own** verification, taken from AGENTS.md/CLAUDE.md. Report pass/fail per item. **If any item fails, stop and report; don't call it done.**

Typical checks, in priority order (use what the project actually has):

- Run the **test suite** (and add/extend tests if the change needs them).
- Run the **build / typecheck / lint** and confirm it's clean.
- Exercise the change for real — a request to the endpoint, the command run, the job executed, or the page opened.

**If a spec exists**, also run every item in its **Acceptance Criteria** and report each pass/fail. Verify is the "test" step — the spec's acceptance criteria are the contract you check back against.

---

## `/workflow status`

Print exactly:

```
phase:   <phase>
slug:    <slug-or-"n/a">
intent:  <fix|new|improve|"n/a">
branch:  <current-branch + " (N files changed)", or "n/a">
spec:    <docs/spec/...-or-"none">
plan:    <docs/plan/...-or-"none">
elapsed: <now − started as "Xh Ym", or "n/a">
next:    <suggested-next-command>
```

`elapsed` reads `started` from the spec if one exists, otherwise from the plan. `branch` is `n/a` outside a git repo; `N` is the count from **Git → Changed-files signal**. Terse — this is the "where am I" command.

---

## `/workflow done` — close out

1. **Confirm verify passed** — including every Acceptance Criterion in the spec, if there is one.
2. **Stamp completion and sum the manhours** in the file that owns the clock (the **spec** if it exists, else the **plan**):

   ```bash
   SPEC=docs/spec/<slug>.md
   PLAN=docs/plan/<slug>.md
   SRC=$( [ -f "$SPEC" ] && echo "$SPEC" || echo "$PLAN" )
   START_EPOCH=$(grep -m1 -oE 'epoch [0-9]+' "$SRC" | grep -oE '[0-9]+')
   NOW_EPOCH=$(date +%s)
   NOW_HUMAN=$(date '+%Y-%m-%d %H:%M %Z')   # use a TZ named in AGENTS.md/CLAUDE.md if any
   SECS=$(( NOW_EPOCH - START_EPOCH ))
   printf '%dh %dm\n' $(( SECS / 3600 )) $(( (SECS % 3600) / 60 ))
   ```

   In that file's `## Manhours` block set `completed:` to `$NOW_HUMAN  (epoch $NOW_EPOCH)` and `total:` to the `Xh Ym` result. (Epoch is UTC-based, so the diff is correct regardless of timezone.)
3. **Summarize** what changed — the file list from **Git → Changed-files signal** — and report the total.
4. **Offer the commit** (git repos only). Show the branch, the files to be staged, and the proposed message written in the repo's existing style, then ask. Commit only on an explicit yes; if the answer is no or ambiguous, leave the tree as-is and say the work is uncommitted. Never push and never open a PR here — those need their own ask.

Phase returns to `idle`.

---

## Integration with other skills

| Situation                                    | Skill / action                                                  |
| -------------------------------------------- | --------------------------------------------------------------- |
| `new` start, or `/workflow spec`             | `creator-spec` — writes/refines `docs/spec/<slug>.md` (owns the spec template + clock) |
| `/workflow plan` (and `fix`/`improve` start) | `creator-plan` — drafts `docs/plan/<slug>.md`, reading the spec when present |
| building / verifying                         | the stack-appropriate skills named in AGENTS.md/CLAUDE.md       |

`creator-spec` and `creator-plan` are domain-agnostic too — they read the project's AGENTS.md/CLAUDE.md for stack, layout, and verification conventions rather than assuming any.

---

## Anti-patterns

- **Don't assume a stack.** Read AGENTS.md/CLAUDE.md; verify the way the project verifies, not a hardcoded checklist.
- **Don't write a spec for a trivial `fix`.** If it's describable and verifiable in one sentence, skip to plan/build.
- **Don't let the plan contradict the spec.** The spec is the contract — if building proves it wrong, update the spec first, then re-plan.
- **Don't mark `done` while any Acceptance Criterion fails.** Verify is the test step.
- **Don't auto-commit, push, or open PRs.** Ask before any git action.
- **Don't touch uncommitted work you didn't create.** No stash, reset, or checkout over a dirty tree — stop and ask.
- **Don't `git init`.** No repo means the Git section doesn't apply, not that you should create one.
- **Don't guess a commit style.** Read `git log --oneline -20` and match it.
- **Don't paste the spec or plan into chat.** Point at the file path.
- **Don't introduce tools or structure the project doesn't already use.** Follow existing patterns.
