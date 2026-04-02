---
name: ant-laravel-design-patern
description: Applies common design patterns in Laravel and PHP (Strategy, Factory, Builder, Observer, Decorator, Adapter, pipeline/chain, Actions, Events). Includes full scalable reward-system architecture in references/reward-system-architecture.md. Use when the user asks for design patterns, refactoring for extensibility, reward/processor architecture, "factory in Laravel", "strategy pattern PHP", Antikode-style structure, or merging domain logic with the container and Actions.
---

# Laravel design patterns (Antikode / AntiCMS)

**Canonical detail:** [references/reward-system-architecture.md](references/reward-system-architecture.md) (reward system: folders, interfaces, processors, factory, service, action, controller). For HTTP APIs in this stack, follow skill **ant-laravel-api** (`App\Services\ApiResponse`, Form Requests, Resources).

## When to use

- Implementing or naming a specific pattern (factory, strategy, observer, etc.)
- Designing extensible domains (payment channels, notifications, reward types, pricing rules)
- Refactoring away from large `switch` / `if` ladders on a type field
- Aligning folder layout with Actions, Strategies, Services, Events

## Quick reference: problem → pattern → Laravel shape

| Problem | Pattern | Laravel / PHP approach |
|--------|---------|----------------------|
| Many optional constructor params / staged build | **Builder** | Fluent builder class, `withX()` returning `$this`, `build()`; or small factory methods on the model/DTO |
| Create implementation by runtime type | **Factory** | Enum `match` → class string + `app()->make()`, or tagged bindings / `foreach (contracts as impl)` map |
| Swappable algorithms | **Strategy** | Interface + concrete classes; choose in factory or delegate from service |
| React to domain changes without tight coupling | **Observer** | `Event` + `Listener`, or `Model::observe()`, or `::dispatchesEvents` |
| Add behavior without editing core class | **Decorator** | Wrapper implementing same interface; or middleware-style pipeline |
| Bridge third-party / legacy API | **Adapter** | Thin class implementing your port, delegating to external SDK |
| Sequential handlers, early exit | **Chain / pipeline** | `Illuminate\Pipeline\Pipeline`, or explicit chain of handlers |
| One clear use case per class | **Action** | `app/Actions/...` invokable or `execute()`; inject deps there, keep controllers thin |
| Side effects after core work | **Events** | `event(new ...)` after transaction commits when possible |

## Laravel-first habits

- Prefer **constructor injection** and the container over service locators and singleton abuse.
- Prefer **interfaces + `final` implementations** for strategies and adapters.
- Use **`DB::transaction()`** for multi-step domain writes that must stay consistent.
- Keep **controllers** as orchestration; put rules in Actions/Services/Domain classes.
- For list processing with stages, consider **Pipeline** before a deep decorator stack.

## End-to-end example (reward system)

Strategy + Factory + Actions + Events for check-in / reward flow: processors per reward type, `RewardProcessorFactory`, `ProcessCheckInRewardsAction`, `RewardService`, events for side effects. Full structure, interfaces, enum-to-processor map, and sample controller flow: [references/reward-system-architecture.md](references/reward-system-architecture.md).

## Pattern selection (short)

| Situation | Pattern |
|-----------|---------|
| Complex object graphs or optional fields | Builder, Factory |
| Add behavior dynamically | Decorator, Pipeline |
| Multiple algorithms for one operation | Strategy |
| Decouple reactions from core flow | Observer / Events |
| Wrap external or legacy APIs | Adapter |

## Anti-patterns

| Avoid | Prefer |
|-------|--------|
| Singleton for everything | Container-scoped bindings |
| Factory for every `new` | Direct instantiation when type is fixed |
| Very long decorator / pipeline chains | Few steps, clear names, tests per stage |
| God service with all branches | Strategy + Factory + small Actions |

## Related skills

- **ant-laravel-api** — REST APIs, `ApiResponse`, Sanctum, pagination, validation
- **ant-laravel-eloquent** — query performance, relationships, indexes, pagination
