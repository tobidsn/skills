---
name: creator-plan
description: Use when the user wants a written implementation plan before any code is touched — they give a short title plus a sentence or two of detail, and you produce a structured `docs/plan/<slug>.md` (Context / Goals / Notes / Done-when / Implementation) that derives from a spec at `docs/spec/<slug>.md` when one exists. Domain-agnostic and spec-driven; reads the project's AGENTS.md / CLAUDE.md for stack, layout, and verification conventions instead of assuming any. Trigger on "buatkan plan", "buat rencana", "create a plan", "draft a plan", "plan untuk X", "tolong rencanakan", "/creator-plan", or any time the user explicitly asks for a planning document (not a code change).
---

# Creator Plan

Produce one Markdown file at `docs/plan/<slug>.md` where `<slug>` is kebab-case of the title. No code edits, no git, no PR — that's the orchestrator's (`/workflow`) job. The plan is **HOW**; it derives from the spec (**WHAT/WHY**) at `docs/spec/<slug>.md` when one exists.

## First: learn the project (don't assume a stack)

This skill is domain-agnostic. It assumes nothing about the language, framework, or whether the project even has a browser, a test runner, or a build step. Before writing, read these in order and let them override every default below:

1. **`AGENTS.md` or `CLAUDE.md`** — repo root first, then the nearest one to the work. This is the source of truth for the stack, directory layout, how to build/test/run, and project conventions.
2. The **spec** at `docs/spec/<slug>.md`, if it exists.
3. The **code the task touches** — skim it; don't ask follow-ups you could answer by reading.

If there's no `AGENTS.md`/`CLAUDE.md`, infer conventions from the code (manifest files, test dirs, scripts) and say so in the plan's Context.

## Spec-driven: read the spec first

If `docs/spec/<slug>.md` exists, the plan **derives from it**:

- Link it at the top: `> Spec: @docs/spec/<slug>.md`.
- Every Goal cites the requirement it satisfies — `[R1]`, `[R2]`, …
- The plan must not contradict the spec. If building would, the spec is wrong — fix it (via `/workflow spec`, which calls `creator-spec`) first, then re-plan.
- The spec **owns the clock and the acceptance criteria** → **omit** the `Manhours` and `Done when` blocks from the plan.

If there's no spec, the plan is the only artifact: **keep** `Manhours` (it owns the clock) and add a task-specific `Done when`.

## Output template

```md
# <task title>

> Spec: @docs/spec/<slug>.md          ← only when a spec exists; otherwise delete this line

## Manhours                            ← OMIT this block when a spec exists (the spec owns the clock)

- started:   <YYYY-MM-DD HH:MM TZ>  (epoch <seconds>)
- completed: —
- total:     —

## Context

- @path/to/file — what it is + its current state, one line each
- conventions from AGENTS.md/CLAUDE.md that constrain this task (stack, layout, how to run/test)

## Goals

- [R#] <outcome — what gets created / changed / removed>   ← cite the R# when a spec exists; one bullet per outcome, not per step

## Notes

- gotchas the executor would miss reading the files cold (edge cases, migrations, config, ordering)

## Done when                           ← include ONLY when there is no spec; otherwise the spec's Acceptance Criteria own this

- task-specific, checkable statements of success — use the project's OWN verification: passing tests, a clean build, a real request/command/job run

## Implementation

<!-- executor's working log — leave empty -->
```

Keep the section order and the empty `Implementation` heading + its HTML comment. Delete the conditional blocks (`Spec:` line, `Manhours`, `Done when`) when they don't apply — don't leave empty headings.

## Stamp the start time (no-spec plans only)

Only when creating a **spec-less** plan (the plan owns the clock). Use the machine's local time, or a timezone named in AGENTS.md/CLAUDE.md:

```bash
date '+%Y-%m-%d %H:%M %Z'   # human-readable, for display
date +%s                    # epoch seconds, for the duration sum
```

Write both into the line, e.g. `started: 2026-05-29 14:03 WIB  (epoch 1748503380)`. Leave `completed`/`total` as `—`; `/workflow done` fills them. Never overwrite `started` when updating. **When a spec exists, don't stamp anything — the spec already did.**

## Section guide

- **Title** — short readable noun phrase. Filename = kebab-case lowercased.
- **Spec** — link line, present only when `docs/spec/<slug>.md` exists.
- **Manhours** — present only on spec-less plans; see "Stamp the start time".
- **Context** — `@path/from/repo/root` for each related file with a one-line "what" + current state, plus the AGENTS.md/CLAUDE.md conventions that constrain the work.
- **Goals** — concrete deliverables: **created** / **changed** / **removed**, one bullet per outcome. Cite the `R#` each satisfies when a spec exists; every requirement in scope should be covered by at least one Goal.
- **Notes** — things easy to miss reading the files cold: edge cases, data migrations, env/config, ordering constraints, backwards-compat.
- **Done when** — spec-less plans only, and **task-specific**. State how success is checked using the project's real verification (tests, build, a live request/run) — take the commands from AGENTS.md/CLAUDE.md.
- **Implementation** — empty. Executor's log only.

## Scope — this skill vs `/workflow`

- **`creator-plan`** (this skill): writes the plan file. Nothing else.
- **`creator-spec`**: writes the spec the plan derives from.
- **`/workflow`**: spec (calls `creator-spec`) → plan (calls this) → build → verify → done.

When invoked from `/workflow`'s `plan` step, behavior is identical: write `docs/plan/<slug>.md` (deriving from the spec if present) and stop.

## Anti-patterns

- **Don't assume a stack.** Read AGENTS.md/CLAUDE.md; don't hardcode "open the browser" or "run the tests" if the project has neither.
- **Don't write the implementation.** No "the change you'll make" code blocks.
- **Don't duplicate the spec's Acceptance Criteria** into the plan — when a spec exists, it owns verification.
- **Don't keep `Manhours` when a spec exists** — a dead timer in the plan is noise.
- **Don't pad.** "Write clean code" / "follow best practices" — delete. No constant boilerplate sections.
- **Don't invent files.** Every `@path` in Context must exist; proposed new files go in Goals.
- **Don't touch git, branches, commits, or PRs.** That's `/workflow`.

## Updating an existing plan

If the user points at an existing `docs/plan/<slug>.md`, edit in place. Preserve the `Implementation` section and the `Manhours.started` stamp (if present) unless explicitly asked to reset them.
