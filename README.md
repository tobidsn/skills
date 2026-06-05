# tobidsn/skills

A collection of Claude Code skills for Laravel development following **Antikode Architecture**, plus tools for autonomous ML research, knowledge mapping, and skill management.

## Skills

### Laravel / Antikode

| Skill | Description |
|-------|-------------|
| `ant-laravel-specialist` | Orchestrator — routes tasks to the right ant-* skill automatically |
| `ant-laravel-api` | REST API architecture: ApiResponse, Sanctum, Form Requests, Resources, Actions |
| `ant-laravel-eloquent` | Eloquent optimization: N+1 prevention, indexes, pagination, chunking |
| `ant-laravel-design-patern` | Design patterns: Strategy, Factory, Builder, Observer, Actions, Events |
| `ant-dedoc-scramble` | OpenAPI 3.1 documentation via Laravel Scramble |
| `ant-important-code` | Antikode discipline: minimal code, no unsolicited implementation |

### ML Research

| Skill | Description |
|-------|-------------|
| `autoresearch` | Autonomous LLM training experiments with [karpathy/autoresearch](https://github.com/karpathy/autoresearch) — modify `train.py`, iterate on `val_bpb`, run overnight |

### Agentic Workflow

Domain-agnostic, spec-driven skills that take a task from idea → plan → build → verify with minimal hand-holding. They read the project's `AGENTS.md` / `CLAUDE.md` for stack and verification conventions instead of hardcoding any, so they port to any project.

| Skill | Description |
|-------|-------------|
| `workflow` | Orchestrator for one unit of work — drives `spec → plan → build → verify → done`. Delegates spec-writing to `creator-spec` and plan-writing to `creator-plan`. Dispatched via `/workflow <slug> <prompt>` |
| `creator-spec` | Writes the contract (`docs/spec/<slug>.md`) — Problem/Why, Scope, Requirements, Acceptance Criteria. Mandatory for new features, opt-in for fix/improve |
| `creator-plan` | Writes the implementation plan (`docs/plan/<slug>.md`) — Context, Goals, Notes, Done-when, Implementation. Derives from the spec when one exists |

### Knowledge & Visualization

| Skill | Description |
|-------|-------------|
| `mindmap-architect` | Convert prompts, YouTube transcripts, files, Lark Docs, or images into interactive SVG mindmaps. Single vault at `~/Mindmaps/` with a shared viewer and thin per-mindmap stubs |

### Utilities

| Skill | Description |
|-------|-------------|
| `skill-creator` | Framework for creating, evaluating, and packaging new skills |
| `agent-memory` | Persistent cross-conversation memory storage |
| `find-skills` | Discover and install skills from the public ecosystem |

## Installation

Skills are installed via the **Skills CLI** — a package manager for Claude Code skills.

### Install a single skill

```bash
npx skills add tobidsn/skills@ant-laravel-api
```

### Install `mindmap-architect`

```bash
npx skills add tobidsn/skills@mindmap-architect -g
```

After install, run the skill from any project — outputs land in `~/Mindmaps/` (override with `MINDMAP_VAULT`). Open `~/Mindmaps/index.html` directly, or run `python ~/Mindmaps/serve.py` for the live catalog.

### Install all skills at once

```bash
npx skills add tobidsn/skills@ant-laravel-specialist
npx skills add tobidsn/skills@ant-laravel-api
npx skills add tobidsn/skills@ant-laravel-eloquent
npx skills add tobidsn/skills@ant-laravel-design-patern
npx skills add tobidsn/skills@ant-dedoc-scramble
npx skills add tobidsn/skills@ant-important-code
npx skills add tobidsn/skills@autoresearch
npx skills add tobidsn/skills@mindmap-architect
npx skills add tobidsn/skills@workflow
npx skills add tobidsn/skills@creator-spec
npx skills add tobidsn/skills@creator-plan
```

### Install globally (recommended)

Add the `-g` flag to install for all your projects:

```bash
npx skills add tobidsn/skills@ant-laravel-specialist -g
```

### Skip confirmation prompts

```bash
npx skills add tobidsn/skills@ant-laravel-api -g -y
```

### Update installed skills

```bash
npx skills update
```

### Check for outdated skills

```bash
npx skills check
```

## Usage

Once installed, skills activate automatically when you work on relevant tasks in Claude Code. For example:

- Asking Claude to build a REST endpoint → `ant-laravel-api` activates
- Asking about an N+1 query issue → `ant-laravel-eloquent` activates
- Asking to document an API → `ant-dedoc-scramble` activates
- Asking to "mindmap this YouTube video" or "make a mindmap of these notes" → `mindmap-architect` activates

The `ant-laravel-specialist` orchestrator skill will route your request to the right focused skill based on context.

## Antikode Architecture Principles

All `ant-*` skills enforce a consistent coding discipline:

- **Only implement what's asked** — no bonus features, unsolicited refactors, or extra files
- **Thin controllers** — logic belongs in `app/Actions/{Resource}/` or Services
- **Final classes** — controllers, models, services are `final`
- **Strict types** — `declare(strict_types=1)` and explicit return types everywhere
- **Constructor DI** — no service locators or `app()` helpers in business logic
- **PSR-12** — consistent formatting

## Browse More Skills

Discover the full skills ecosystem at **[skills.sh](https://skills.sh/)**.

```bash
npx skills find laravel
npx skills find php
```
