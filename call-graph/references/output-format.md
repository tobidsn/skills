# Output format contract

The grammar every call-graph answer follows, whatever the material — code, interface, orchestration, or process. The point of a fixed format is that a reader who has seen one of these can read all of them without re-learning the notation, and that graphs from different sources can be compared side by side. Material-specific guidance lives in `non-code-graphs.md`; the grammar here is shared.

## Contents

- [Skeleton](#skeleton)
- [Grammar rules](#grammar-rules)
- [Node markers](#node-markers)
- [The `src:` evidence block](#the-src-evidence-block)
- [When to show a Tests section](#when-to-show-a-tests-section)
- [Worked example — TypeScript / Effect](#worked-example--typescript--effect)
- [Worked example — Laravel / PHP](#worked-example--laravel--php)
- [Worked example — answering "what calls X?"](#worked-example--answering-what-calls-x)
- [Failure modes](#failure-modes)

## Skeleton

````markdown
graph: <short title of the flow>

Production:
```ts
EntryPoint
  → ComponentA
    → ComponentA.method
      → [condition] ComponentB
        → {queue_or_store}
```

Tests:
```ts
TestEntryPoint
  → ComponentATestLayer
    → ComponentA.method
      → ComponentBTestLayer
```

src:
  EntryPoint → path/to/file.ts:LINE
  ComponentA → path/to/component.ts:LINE
  ComponentA.method → path/to/component.ts:LINE
  ComponentB → path/to/other.ts:LINE
````

Then, below the block, three to five lines of notes covering only what the tree can't express.

## Grammar rules

- **Plain text only. Never Mermaid, never an image.** A plain-text graph survives being pasted into a terminal, a PR review, a commit message, or a Slack thread. That reuse is most of the value.
- **`ts` fence, always** — including for PHP, Python, Go, Ruby, Java. It is a rendering choice: it gives monospace and keeps the `→` from being mangled. It is not a claim about the source language. Keeping it constant is what makes graphs comparable across repos.
- **Two-space indentation per level.** Indentation is the hierarchy; nothing else encodes it.
- **The root has no arrow.** Every child line starts with `→ ` after its indentation.
- **Real symbols only.** `AuthController.__invoke`, `UserRepository::findByEmail`, `POST /api/login` — the names as they appear in the source. Never a paraphrase like "the auth layer" or "validation happens here".
- **One node per call, in call order.** If a method calls three things, they are three siblings at the same indent, in the order they execute.
- **Siblings vs. children.** Same indent = called by the same parent. Deeper indent = called *by the line above it*. Getting this wrong is the most misleading error a graph can contain, because it looks correct.

## Node markers

Use these sparingly — they exist so the tree can carry conditionality without turning into prose.

| Marker | Means | Example |
|--------|-------|---------|
| `[condition]` | Runs only in a specific case | `→ [if cache miss] UserRepository.find` |
| `{name}` | A queue, store, cache, or external boundary — not a function | `→ {redis:sessions}` |
| `[new]` | A node that does not exist yet (planned work) | `→ [new] TokenRotator.rotate` |
| `[unverified]` | Hop you believe exists but could not confirm | `→ [unverified] LegacyHook.fire` |
| `[async]` | Dispatched, not awaited — caller does not block | `→ [async] SendWelcomeEmail` |
| `[proposed]` | The whole graph is a proposal, not a trace — goes in the title | `graph: [proposed] token rotation` |
| `? ` | A way this node fails or is absent, on its own line under the node | `? error: falls back to cached price` |
| `! ` | Something the node needs, or must release, on its own line | `! release: lock freed in finally (job.ts:88)` |

Rules for markers:
- **Never invent a line number for a `[new]` node.** It has no line yet. Omit it from `src:` or write `— not yet implemented`.
- **`[unverified]` is a last resort, not a shortcut.** Prefer opening the file. When you do use it, say in the notes what blocked resolution (dynamic dispatch, generated code, vendor binary).
- **`[proposed]` replaces the `src:` block, it doesn't decorate it.** A proposal has nothing to cite. Say in one line that the graph reflects described intent rather than verified source.
- **`?` and `!` lines are indented as children of the node they describe** but take no arrow, so they read as annotations rather than calls. They come into their own for interface and process graphs, where failure states and preconditions *are* the substance of the question — see `non-code-graphs.md`. In code graphs, prefer keeping this in the notes unless a specific node's failure mode is the point of the question.

## The `src:` evidence block

Every unique node in the graph gets one line. This block is what separates a call graph from a plausible story — the reader can spot-check any hop in two seconds.

```
src:
  POST /api/login → routes/api.php:42
  AuthController.__invoke → app/Http/Controllers/AuthController.php:28
  LoginAction.execute → app/Actions/Auth/LoginAction.php:31
```

- One line per **unique** node — if a node appears twice in the tree, it appears once here.
- Point at the **definition**, not the call site. The reader wants to jump to the implementation.
- Keep the graph's order, so the two blocks read together.
- Paths relative to the repo root.

## When to show a Tests section

Only when the graph shape genuinely differs. Swapping a real repository for an in-memory one does not change the shape — same nodes, same order, different binding, and it earns a note, not a second tree:

> Tests bind `UserRepository` to `InMemoryUserRepository` (`tests/Support/InMemoryUserRepository.php:14`); the graph shape is unchanged.

Show a Tests tree when a fake short-circuits a branch, when tests enter through a different door (calling the action directly instead of the HTTP route), or when a step is skipped entirely under test.

**Never write a Tests section you did not read.** An assumed test graph is the easiest thing in this format to fabricate and the hardest for the reader to catch.

## Worked example — TypeScript / Effect

````markdown
graph: token refresh, from HTTP route to persisted session

Production:
```ts
POST /auth/refresh
  → AuthHttpLive.refreshHandler
    → TokenService.refresh
      → TokenService.verifyRefreshToken
        → JwtLive.verify
      → [if token revoked] TokenError.Revoked
      → SessionRepo.rotate
        → {postgres:sessions}
      → [async] AuditLog.record
        → {queue:audit}
```

src:
  POST /auth/refresh → src/http/routes.ts:88
  AuthHttpLive.refreshHandler → src/http/auth.ts:54
  TokenService.refresh → src/services/token.ts:71
  TokenService.verifyRefreshToken → src/services/token.ts:112
  JwtLive.verify → src/layers/jwt.ts:23
  TokenError.Revoked → src/domain/errors.ts:19
  SessionRepo.rotate → src/repo/session.ts:140
  AuditLog.record → src/services/audit.ts:37
````

Notes:
- `verifyRefreshToken` fails closed — any verification error becomes `TokenError.Revoked` and the rotation never runs (`token.ts:118`).
- `SessionRepo.rotate` deletes and inserts in one transaction, so a crash mid-rotation cannot leave the user with two valid sessions (`session.ts:147`).
- The audit write is fire-and-forget; a queue outage loses audit records silently.

## Worked example — Laravel / PHP

````markdown
graph: create post, from route to cache invalidation

Production:
```ts
POST /api/posts
  → PostController.__invoke
    → StorePostRequest.validated
    → CreatePostAction.execute
      → Post::create
        → {mysql:posts}
      → [if has media] AttachMediaAction.execute
        → {s3:uploads}
      → [async] PostCreated event
        → InvalidatePostCacheListener.handle
          → {redis:posts.index}
```

src:
  POST /api/posts → routes/api.php:31
  PostController.__invoke → app/Http/Controllers/Api/PostController.php:24
  StorePostRequest.validated → app/Http/Requests/StorePostRequest.php:19
  CreatePostAction.execute → app/Actions/Post/CreatePostAction.php:27
  AttachMediaAction.execute → app/Actions/Post/AttachMediaAction.php:22
  PostCreated → app/Events/PostCreated.php:12
  InvalidatePostCacheListener.handle → app/Listeners/InvalidatePostCacheListener.php:18
````

The fence stays `ts` even though every symbol here is PHP — that's the rule working as intended. The fence is presentation; the tree is the contract.

Notes:
- `PostCreated` is queued (`ShouldQueue`, `PostCreated.php:12`), so cache invalidation lags the response — a client re-reading the index immediately can see stale data.
- `AttachMediaAction` runs inside the same request; a slow S3 upload blocks the response.

## Worked example — answering "what calls X?"

Callers invert the tree: X is the root, and the branches point at who reaches it.

````markdown
graph: callers of UserRepository::findByEmail

```ts
UserRepository::findByEmail
  ← LoginAction.execute
    ← AuthController.__invoke
      ← POST /api/login
  ← PasswordResetAction.execute
    ← ForgotPasswordController.__invoke
      ← POST /api/forgot-password
  ← ImportUsersCommand.handle
    ← {cli:users:import}
```

src:
  UserRepository::findByEmail → app/Repositories/UserRepository.php:52
  LoginAction.execute → app/Actions/Auth/LoginAction.php:31
  PasswordResetAction.execute → app/Actions/Auth/PasswordResetAction.php:26
  ImportUsersCommand.handle → app/Console/Commands/ImportUsersCommand.php:44
````

Use `←` for caller direction so the reader can tell at a glance which way the graph runs. Everything else — indentation, evidence block, markers — is unchanged.

## Failure modes

The specific ways these graphs go wrong, worth checking before you send one:

1. **Sibling drawn as child.** Two calls from the same parent, indented as if one calls the other. Re-read the parent body and confirm the nesting.
2. **Indirection left unresolved.** An interface, event, or job name in the tree with no implementation beneath it. Find the binding (container, provider, module, listener map) or mark it `[unverified]`.
3. **Line numbers reconstructed from memory.** They drift. Record them while the file is open.
4. **A fabricated Tests section.** If you did not read a test, there is no Tests section.
5. **A proposal presented as a trace.** The graph came from what someone described, not from a source you opened, but it carries a `src:` block anyway. Title it `[proposed]` and drop the citations.
6. **Scope creep.** The graph kept growing into neighboring subsystems. Answer the question asked; offer the next trace in one line.
7. **Prose that repeats the tree.** Notes exist for what the tree cannot show. If a note restates an arrow, delete it.
