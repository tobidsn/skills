# tobidsn/skills

A collection of Claude Code skills for Laravel development following **Antikode Architecture** — minimalist, type-safe, and explicit PHP.

## Skills

| Skill | Description |
|-------|-------------|
| `ant-laravel-specialist` | Orchestrator — routes tasks to the right ant-* skill automatically |
| `ant-laravel-api` | REST API architecture: ApiResponse, Sanctum, Form Requests, Resources, Actions |
| `ant-laravel-eloquent` | Eloquent optimization: N+1 prevention, indexes, pagination, chunking |
| `ant-laravel-design-patern` | Design patterns: Strategy, Factory, Builder, Observer, Actions, Events |
| `ant-dedoc-scramble` | OpenAPI 3.1 documentation via Laravel Scramble |
| `ant-important-code` | Antikode discipline: minimal code, no unsolicited implementation |
| `skill-creator` | Framework for creating, evaluating, and packaging new skills |
| `agent-memory` | Persistent cross-conversation memory storage |
| `find-skills` | Discover and install skills from the public ecosystem |

## Installation

Skills are installed via the **Skills CLI** — a package manager for Claude Code skills.

### Install a single skill

```bash
npx skills add tobidsn/skills@ant-laravel-api
```

### Install all skills at once

```bash
npx skills add tobidsn/skills@ant-laravel-specialist
npx skills add tobidsn/skills@ant-laravel-api
npx skills add tobidsn/skills@ant-laravel-eloquent
npx skills add tobidsn/skills@ant-laravel-design-patern
npx skills add tobidsn/skills@ant-dedoc-scramble
npx skills add tobidsn/skills@ant-important-code
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
