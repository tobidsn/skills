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
| `ant-laravel-design-patern` | Design patterns: Strategy, Factory, Builder, Observer, Actions, Events |
| `ant-dedoc-scramble` | OpenAPI 3.1 docs via Laravel Scramble |
| `ant-important-code` | Antikode discipline enforcer: minimal code, no unsolicited implementation |
| `skill-creator` | Framework for creating, evaluating, and packaging new skills |
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
