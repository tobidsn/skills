# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Repo Is

A **skills library for Claude Code** — self-contained skill packages (each in its own directory) that extend Claude with specialized knowledge for Laravel/PHP development following Antikode Architecture principles.

Skills are distributed via `npx skills add <package>` from the Skills CLI. There are no build steps, no package manager, and no test runner at the repo level.

## Skill Structure

Each skill is a directory containing:
- `SKILL.md` — The skill itself (triggering conditions, instructions, references)
- `assets/` — Templates, HTML tools, etc. (optional)
- `references/` — Supporting markdown docs loaded on demand (optional)
- `agents/` — Sub-agent definitions (optional, used by `skill-creator`)
- `scripts/` — Python tooling (optional, used by `skill-creator`)

## Skills in This Repo

| Skill | Purpose |
|-------|---------|
| `ant-laravel-specialist` | Orchestrator — routes tasks to focused ant-* skills |
| `ant-laravel-api` | REST API architecture: ApiResponse, Sanctum, Form Requests, Resources |
| `ant-laravel-eloquent` | Eloquent optimization: N+1 prevention, indexes, pagination |
| `ant-laravel-api-cache` | API response caching + N+1 fixes for anticms-style projects: serialize-before-cache, invalidation groups, TTL patterns, Scramble pitfalls, query-count verification harness |
| `ant-laravel-design-patern` | Design patterns: Strategy, Factory, Builder, Observer, Actions, Events |
| `ant-dedoc-scramble` | OpenAPI 3.1 docs via Laravel Scramble |
| `api-prototyper` | Runnable mock API for frontend integration — real routes/validation/response shapes, hardcoded dummy data, no database. Any stack: reads the codebase for conventions (greenfield → asks once); inputs are prompts, screenshots, docs/URLs, OpenAPI, or example JSON, normalized into one endpoint table gated for approval before code. Happy-path tests verified to actually execute, existing linter run on new files, output left uncommitted |
| `ant-important-code` | Antikode discipline enforcer: minimal code, no unsolicited implementation |
| `project-issue` | Issue triage for the current project — repo auto-discovered from `.git/config`, supports `gh` (GitHub) and `glab` (GitLab) |
| `call-graph` | Verified plain-text graphs for flow/trace/"what calls X"/overview questions — every node carries `path:line` evidence. Four materials: code (language-agnostic), interface flows, orchestration, process. Three modes, by explicit subcommand: default = trace; `brainstorm`/`design` = a `[proposed]` graph for work that doesn't exist yet, with an `open:` decision block iterated until empty; `plan` = writes `docs/plans/<date>-<slug>.md` carrying the as-is trace plus the target shape, with Goals citing graph nodes |
| `promo-card` | Landscape PNG announcement cards for a skill/feature/release — self-contained HTML rendered to an exact pixel size via headless Chrome, with DOM verification before the screenshot |
| `security-audit` | audit → plan → build, each phase gated. Audit = one ranked `path:line` table + `Not scanned:` line; every CRIT/HIGH always gets a row, MED/LOW fill to ~10 and the tail collapses to `+N more`; scans every detected ecosystem (composer + npm coexist in a Laravel app), `govulncheck` via `go run` without installing. Plan lands at `<audited project>/docs/security-audit/<date>-<slug>-plan.md`, CRIT/HIGH only, findings table embedded. `audit auto` skips the plan confirmation; the build gate never opens without explicit approval. Manual `/security-audit report` renders the findings as a self-contained HTML assessment report for stakeholders at `<project>/docs/security-audit/<date>-<slug>-report.html`; manual `/security-audit issue` publishes them as one spec-shaped `ready-for-agent` issue (Problem/Solution/User Stories/Decisions, no `path:line` in the body) on the project's gh/glab tracker, and `/security-audit tickets` breaks the remediation into tracer-bullet tickets whose blocking edges come from the plan's fix-the-chain order — publishing always gates, and a public repo means public disclosure. None of the three is triggered by a phase. Language patterns in `references/{php,typescript,go}.md` + `plan-template.md` + `rate-limits.md` (recommended limits and keying, the four OTP controls incl. wrong-guess caps, bot filtering by client type — Turnstile for web, Play Integrity/App Attest for apps), all loaded on demand |
| `pdf-to-html` | PDF -> one self-contained, print-ready HTML file with the original font and layout. `scripts/probe.py` dumps page geometry, glyph coordinates in mm, embedded rasters and their placement; `scripts/measure.py` reads box rules, bands, columns and colours off a 300 dpi raster in mm; `scripts/inline.py` folds images in as WebP data URIs; `scripts/verify.py` renders back through headless Chrome and diffs shape, rule geometry and extracted text against the source. `assets/page-template.html` carries the CSS primitives |
| `recap` | Session recap under two headings — What Was Done / Next Actions. Bullets carry an openable anchor (`path:line`, command, PR); an empty heading is dropped rather than padded. Triggers on "tldr", "where are we", "sampai mana", `/recap` |
| `autopilot` | Unattended continuation when the user leaves the keyboard or a queue runs start to finish. Never stops on an ambiguous decision — takes the best-evidenced reading and states it in the visible output at the moment it is made, so nothing is lost to compaction. Blocked work is `AUTOPILOT-BLOCKED` in a comment with the real markup commented out beside it, verified by deletion: strip the marker and no invented word may still render, run, or ship; `grep -rn AUTOPILOT-BLOCKED` is the complete unfinished list. Reports through `recap` with one added `## Assumptions` block, each line anchored `— reverse: path:line`; writes no report file. Push, merge, deploy, delete, and outward sends still stop |
| `skill-creator` | Framework for creating, evaluating, and packaging new skills |
| `autoresearch` | Autonomous LLM training experiments with karpathy/autoresearch: setup, experiment loop, train.py modifications, val_bpb metric, program.md guidance |
| `agent-memory` | Persistent cross-conversation memory storage |
| `find-skills` | Discover and install skills from the public ecosystem |

## Antikode Architecture (Core Philosophy)

All `ant-*` skills enforce these principles:
- **Do not code unless asked** — no extra features, unsolicited refactors, or bonus files
- **Thin controllers** — business logic belongs in Services or Actions (`app/Actions/{Resource}/`)
- **Final classes** for controllers, models, services
- **Strict types** — `declare(strict_types=1)`, explicit return types
- **Explicit DI** — constructor injection only, no service locators
- **PSR-12** compliance

## skill-creator Workflow

When creating or improving a skill:
1. Draft `SKILL.md` with clear trigger conditions
2. Create `evals/evals.json` with 2–3 realistic test prompts
3. Run evals: `python scripts/run_eval.py`
4. Review results: `python eval-viewer/generate_review.py`
5. Grade: `python scripts/aggregate_benchmark.py`
6. Optimize description: `python scripts/run_loop.py`
7. Package: `python scripts/package_skill.py`

See `skill-creator/references/schemas.md` for `evals.json` and `grading.json` schema.

## ant-laravel-api Templates

PHP scaffolding templates live in `ant-laravel-api/assets/templates/`:
- `Controller.php` — Invokable controller (thin, delegates to Action)
- `Action.php` — Single-purpose business logic
- `FormRequest.php` — Validation + optional `payload()` → DTO
- `Model.php` — ULID-keyed Eloquent model
- `Resource.php` — JSON API resource transformer
- `Payload.php` — Data transfer object
