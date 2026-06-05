---
name: creator-spec
description: Use when the user wants a written spec — the contract for a piece of work — before any plan or code, in any project. They give a short title plus a sentence or two of intent, you produce a structured `docs/spec/<slug>.md` (Manhours / Problem-Why / Scope / Requirements / Acceptance Criteria / Notes / Open Questions) by reading the project's AGENTS.md / CLAUDE.md for stack and verification conventions instead of assuming any. Mandatory for a new feature, opt-in for a fix/improve. Trigger on "buatkan spec", "buat spec", "tulis spec", "create a spec", "draft a spec", "spec untuk X", "/creator-spec", or any time the user asks for a spec / requirements / acceptance-criteria document (not a plan, not a code change).
---

# Creator Spec

Produce one Markdown file at `docs/spec/<slug>.md` where `<slug>` is kebab-case of the title. No plan, no code edits, no git — that's `creator-plan`'s and `/workflow`'s job. The spec is the **contract**: WHAT must be true and WHY (requirements + acceptance criteria). It never says HOW to build it — that's the plan (`creator-plan`).

## First: learn the project (don't assume a stack)

This skill is domain-agnostic. It assumes nothing about the language, framework, or whether the project has a browser, a test runner, or a build step. Before writing, read these in order and let them override every default below:

1. **`AGENTS.md` or `CLAUDE.md`** — repo root first, then the nearest one to the work. This is the source of truth for the stack, directory layout, how to build/test/run, and project conventions. The Acceptance Criteria must lean on **this** project's real verification.
2. The **code the task touches** — skim it; don't ask follow-ups you could answer by reading.

If there's no `AGENTS.md`/`CLAUDE.md`, infer conventions from the code (manifests, test dirs, scripts) and say so in the spec's Notes.

## The spec owns the clock

The spec is the time-tracking source of truth for the task. When **creating**, stamp `started` in Manhours; `/workflow done` later fills `completed`/`total`. **Never overwrite `started`** when refining. Use the machine's local time, or a timezone named in AGENTS.md/CLAUDE.md:

```bash
date '+%Y-%m-%d %H:%M %Z'   # human-readable, for display
date +%s                    # epoch seconds, for the duration sum
```

Write both into the line, e.g. `started: 2026-05-29 14:03 WIB  (epoch 1748503380)`.

## Output template

```md
# <task title> — Spec

## Manhours

- started:   <YYYY-MM-DD HH:MM TZ>  (epoch <seconds>)
- completed: —
- total:     —

## Problem / Why

- who it's for + what's missing or wrong, in one or two lines

## Scope

- in:  what this delivers
- out: what's explicitly excluded (YAGNI)

## Requirements

- R1: <testable statement — something that must be true when done>
- R2: …

## Acceptance Criteria

- [ ] <check the verify step runs; map each back to an R#>
- [ ] <how this is proven: a passing test, a clean build, a real request/command/job run>

## Notes

- constraints, data shapes, dependencies, edge cases

## Open Questions

- anything unresolved — resolve before /workflow plan
```

Keep the section order and the Manhours block.

## Section guide

- **Title** — short readable noun phrase, suffixed ` — Spec`. Filename = kebab-case lowercased of the title (without the ` — Spec` suffix).
- **Manhours** — stamp `started` on create (see "The spec owns the clock"); leave `completed`/`total` as `—`.
- **Problem / Why** — who it's for and what's missing or wrong. One or two lines, no solutioning.
- **Scope** — `in` = what this delivers; `out` = what's explicitly excluded. The `out` line is where you kill scope creep (YAGNI).
- **Requirements** — `R1`, `R2`, … each a single **testable** statement of something true when done. These are what the plan's Goals cite and what verify checks back against — keep them about outcomes, not implementation.
- **Acceptance Criteria** — checkboxes the `/workflow verify` step runs; map each back to an `R#`. Lean on the project's **real** verification per AGENTS.md/CLAUDE.md (a passing test, a clean build/typecheck/lint, a real request/command/job run, or — for a UI project — the browser pass it defines). Don't invent a verification the project can't perform, and don't hardcode a generic checklist.
- **Notes** — constraints, data shapes, dependencies, edge cases, ordering — anything that bounds the work without prescribing the how.
- **Open Questions** — anything unresolved. An unresolved question means the plan would be guessing.

## Resolve open questions before handoff

The spec is only ready for `creator-plan` when **Open Questions is empty (or explicitly "None")**. When a question's answer changes the requirements or acceptance criteria, **ask the user** a short, focused question rather than guessing — then fold the answer into Scope/Requirements and clear the question.

## Scope — this skill vs `/workflow`

- **`creator-spec`** (this skill): writes/refines the spec file. Nothing else.
- **`creator-plan`**: writes the plan, deriving from the spec.
- **`/workflow`**: spec (calls this) → plan (calls `creator-plan`) → build → verify → done.

When invoked from `/workflow`'s `spec` step or a `new` start, behavior is identical: write `docs/spec/<slug>.md` and stop.

## Anti-patterns

- **Don't assume a stack.** Read AGENTS.md/CLAUDE.md; the acceptance criteria must match how the project actually verifies, not a hardcoded checklist.
- **Don't write HOW.** No files-to-touch, no approach, no code — that's the plan. The spec is the contract.
- **Don't leave Open Questions unresolved.** Resolve them (ask the user) before the plan is drafted.
- **Don't overwrite `started`** when refining an existing spec.
- **Don't pad.** No "follow best practices" boilerplate.
- **Don't touch the plan, the code, git, or PRs.** That's `creator-plan` / `/workflow`.

## Updating an existing spec

If the user points at an existing `docs/spec/<slug>.md`, edit in place. Preserve the `Manhours.started` stamp. Resolve any Open Questions raised. Don't renumber existing `R#`s that a plan may already cite — append new ones.
