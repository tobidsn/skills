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
| `project-issue` | Issue triage for the current project — repo auto-discovered from `.git/config`, supports GitHub (`gh`) and GitLab (`glab`). `list` open issues or deep-review one into a triage verdict |
| `call-graph` | Answer flow questions with a verified plain-text graph — root → what it reaches, every node backed by an openable `path:line`. Language-agnostic code tracing, plus interface flows, agent orchestration, and pipeline/approval processes |
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
npx skills add tobidsn/skills@project-issue
npx skills add tobidsn/skills@call-graph
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

### Install from a local clone

To try a skill before it's published — or while editing one — symlink it into your global skills directory. Edits then take effect on the next session with no reinstall:

```bash
ln -s "$(pwd)/call-graph" ~/.claude/skills/call-graph
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
- Asking "how does the login flow work" or "what calls this function" → `call-graph` activates

The `ant-laravel-specialist` orchestrator skill will route your request to the right focused skill based on context.

### Using `call-graph`

Ask a flow question in plain language — no special syntax needed:

```
alur login di app ini gimana? dari request masuk sampai token kesimpen
what calls UserRepository::findByEmail?
trace POST /api/v1/campaign/redeem-offers
/call-graph how does token refresh work
```

It also fires on requests that aren't phrased as flow questions but are answered by one — "explain this codebase", "what does this module do", onboarding walkthroughs. It leads with the graph there instead of waiting to be asked, and stays out of the way for single-fact questions ("what port does this run on") where a tree would just be padding.

The answer is always plain text — never Mermaid — so it survives being pasted into a terminal, a PR review, or a commit message:

```ts
POST /api/v1/campaign/redeem-offers
  → middlewares.authMiddleware
    → databaseService.getProject
      → {mongo:projects}
    ? 401: no Bearer, unknown project, verify fails
  → fraudService.checkRedeemQuota
    → databaseService.countRecentRedeems → {mongo:redeem_offers}
  → databaseService.insertPendingRedeem
    ? dup key → 200 replays first response, or 409 in progress
  → casClientService.redeemOffers
    ! on throw: deletePendingRedeem, then rethrow → 500
```

Every node comes with a `src:` block giving its `path:line`, so any hop can be spot-checked in seconds. Per-node facts ride in the tree itself — `?` for how a node fails (retried, recovered, or fatal), `!` for what it needs or must release. The notes below carry only what spans the whole flow: loops that hide N+1s, ordering between sync and deferred work, branches that can no longer run. Trees are capped at ~25 nodes, with anything elided marked `→ … (n more)` rather than silently dropped.

**Four materials.** Code is the default and works on any language. The same notation covers interface flows (surfaces and the moves between them, with empty/loading/partial/error/denied states per surface), agent or task orchestration (waves, gates, data dependencies), and process flows (CI/CD, data pipelines, approval chains). When nothing openable exists yet — a flow you've only described — the graph is labeled `[proposed]` and drops the `src:` block rather than citing sources that don't exist.

**Pinning the format.** To make every future answer in a project use this shape, ask it to add the convention to your `AGENTS.md` or `CLAUDE.md`; it appends a short rule rather than rewriting the file.

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
