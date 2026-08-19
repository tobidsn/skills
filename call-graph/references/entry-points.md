# Finding the real entry point, and resolving indirection

Two things decide whether a call graph is true: starting at the door the request actually comes through, and not stopping when the call goes through something indirect. This file covers both.

## Contents

- [Why the entry point matters more than it looks](#why-the-entry-point-matters-more-than-it-looks)
- [Locating entry points by stack](#locating-entry-points-by-stack)
- [Non-HTTP entry points](#non-http-entry-points)
- [Resolving indirection](#resolving-indirection)
- [Search recipes](#search-recipes)

## Why the entry point matters more than it looks

Starting one layer too deep produces a graph that is locally correct and globally misleading — it omits the middleware that already rejected half the traffic, or the router that dispatches to three handlers rather than one. Starting from a controller because its name matches the feature is the classic version of this mistake. Find the registration, not the plausible-looking file.

## Locating entry points by stack

| Stack | Where routes are registered | Grep for |
|-------|-----------------------------|----------|
| Express / Koa | `app.js`, `server.js`, `routes/` | `app.get(`, `router.post(`, `\.use\(` |
| NestJS | Controller decorators | `@Controller\(`, `@Get\(`, `@Post\(` |
| Next.js App Router | `app/**/route.ts`, `app/**/page.tsx` | `export async function (GET\|POST)` |
| Next.js Pages Router | `pages/api/**` | `export default.*handler` |
| Laravel | `routes/web.php`, `routes/api.php` | `Route::(get\|post\|put\|delete)\(`, `Route::apiResource` |
| Rails | `config/routes.rb` | `resources :`, `get '`, `post '` |
| FastAPI | app module + routers | `@app\.(get\|post)`, `@router\.(get\|post)` |
| Django | `urls.py` | `path\(`, `re_path\(` |
| Spring | Controller annotations | `@RestController`, `@RequestMapping`, `@GetMapping` |
| Go (net/http, chi, gin) | `main.go`, router setup | `HandleFunc\(`, `r\.Get\(`, `router\.POST\(` |
| GraphQL (any) | Schema + resolver map | `Query:`, `Mutation:`, `@Resolver` |
| tRPC | Router definition | `\.query\(`, `\.mutation\(` |

Once you have the route line, the handler it names is your root's first child — and the route line itself is the root (`POST /api/login`, not `AuthController`). Naming the HTTP method and path as the root tells the reader which door this is.

Also check what wraps the handler before it runs: middleware, guards, filters, interceptors, `before_action`. These are real nodes in the graph and they are easy to miss because they're registered somewhere else. If a middleware can reject the request, it belongs in the tree with a `[condition]` marker.

## Non-HTTP entry points

Flows that don't start with a request are where graphs are most useful, because nothing about them is guessable from the directory layout.

| Kind | Where to look |
|------|---------------|
| Queue / worker | Job class `handle`, consumer registration, `Worker` setup, `@Processor` |
| Scheduled | `Kernel::schedule`, crontab, `@Scheduled`, GitHub Actions cron, k8s CronJob |
| Event listener | Listener/subscriber registration map, `EventServiceProvider`, `@EventPattern` |
| CLI | Command class `handle`/`run`, `argparse`/`cobra`/`commander` registration |
| Webhook | Route + signature-verification middleware — verification is part of the graph |
| Message bus | Topic/queue subscription, `@KafkaListener`, SQS consumer |
| Startup | `main`, bootstrap, service provider `boot`, module `onModuleInit` |
| Test | The test function itself, when the user asked about the test path |

## Resolving indirection

An unresolved hop is where a call graph turns into fiction. Each of these looks like a leaf but isn't:

**Interface / abstract dependency.** The tree shows an interface name and stops. Find the binding:
- Laravel: `app/Providers/*ServiceProvider.php` → `$this->app->bind(Contract::class, Impl::class)`
- NestJS: module `providers: [{ provide: TOKEN, useClass: Impl }]`
- Spring: `@Component` / `@Bean` on the implementation, or a qualifier
- Go: whatever is passed at the construction site in `main`
- Effect / layer systems: the `Live` layer provided at the top

Then draw the concrete implementation, and note the interface it satisfies.

**Dispatched event.** The dispatch is not the end of the flow — the listeners are the next level. Find the listener map (`EventServiceProvider`, `@EventsHandler`, subscriber registration) and draw each listener as a child. If the event is queued, mark it `[async]`, because the caller doesn't wait and the ordering guarantee is different.

**Queued job.** `dispatch(SomeJob)` is one node; `SomeJob::handle` is a child of it, running later. Marking it `[async]` is what tells the reader the response returned before this ran.

**Dynamic dispatch.** `$container->make($class)`, reflection, a handler registry keyed by string, `__call`. Follow the registry to the concrete set when it's enumerable. When it truly isn't resolvable statically, mark `[unverified]` and say why — that's a real finding about the codebase, not a failure to trace.

**Framework magic.** Model events (`created`, `saving`), ORM lifecycle hooks, decorators that wrap behavior, aspect-oriented interceptors. These fire without an explicit call site and are invisible to a caller search. Grep for the hook names on the model/entity when a flow's effects don't match its visible calls.

**Middleware chains.** Ordered, and the order matters. Read the registration list, not the individual files.

## Search recipes

Start broad with `rg`, confirm structurally, then read the call site. A name match is a candidate, never evidence.

```bash
# Where is this symbol defined?
rg -n "(function|def|fn|func|class|const)\s+methodName" --type-add 'src:*.{ts,js,php,py,go,rb,java}' -t src

# Who calls it? (candidates — each still needs the call site read)
rg -n "methodName\s*\(" -g '!*test*' -g '!vendor' -g '!node_modules'

# Callers including tests, when comparing production vs test wiring
rg -n "methodName\s*\("

# Which route maps to this handler?
rg -n "AuthController" routes/ config/ app/Providers/

# What binds this interface?
rg -n "UserRepositoryInterface" --glob '*Provider*' --glob '*module*' --glob '*config*'

# Structural search — real call sites of a method on any receiver
ast-grep --pattern '$A.methodName($$$)'

# Structural search — a static call
ast-grep --pattern 'ClassName::methodName($$$)'
```

`ast-grep` is worth reaching for when a method name is common (`handle`, `execute`, `run`, `get`) and `rg` returns hundreds of lines — it matches call *shape*, so it filters out comments, strings, and unrelated definitions in one step.

When a language server is available, its "find references" beats all of the above and should be the default. The greps are the fallback for when it isn't.
