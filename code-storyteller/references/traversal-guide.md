# Entry-Point Traversal Guide

When the skill receives a prompt about a codebase, it needs to discover the
correct **entry point** for the requested flow. This guide gives per-stack
hints to make that discovery fast.

If no stack hint matches, fall back to **grepping the codebase for the path
literal** (e.g. `/api/login`) and inspecting the surrounding context.

---

## Express (Node.js / TypeScript)

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `app.use(...)`, `router.METHOD(...)`, files like `*.routes.{js,ts}` or under `routes/` |
| Middleware | Functions of shape `(req, res, next) => ...`, often in `middleware/` |
| Controllers | Often `controllers/` folder; methods `(req, res) => ...` |
| Services | `services/`, `lib/`, business logic |
| DB layer | ORMs (Prisma, TypeORM, Sequelize) — look for `prisma/`, `entities/`, `models/` |

Trace order: route file → middleware chain → controller method → service → repository/DAO → ORM model.

## NestJS

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `@Controller('/path')` + `@Get/@Post/@Put/...` decorators |
| Middleware | Classes implementing `NestMiddleware` |
| Guards | Classes implementing `CanActivate` (auth/permission checks) |
| Services | `@Injectable()` classes |
| DB | TypeORM/Prisma repositories injected via DI |

Trace order: controller decorator → guards → service via DI → repository.

## Next.js — App Router

| Looking for | Where to find it |
|-------------|------------------|
| Pages | `app/**/page.{ts,tsx}` |
| API routes | `app/**/route.{ts,js}` exporting `GET`, `POST`, etc. |
| Server actions | Files containing `"use server"` directive at top |
| Layouts | `app/**/layout.{ts,tsx}` |
| Middleware | `middleware.{ts,js}` at project root |
| Server components vs client components | `"use client"` directive marks client components |

Trace order for a page request: middleware → layout(s) → page component → any server actions invoked → DB layer.

## Next.js — Pages Router

| Looking for | Where to find it |
|-------------|------------------|
| Pages | `pages/**/*.{ts,tsx}` (default-exported component) |
| API routes | `pages/api/**/*.{ts,js}` (default-exported handler) |
| Data fetching | `getServerSideProps`, `getStaticProps`, `getStaticPaths` |
| Custom server | `server.{js,ts}` at project root (if present) |

Trace order for an API call: API route handler → service/lib → DB.

## FastAPI (Python)

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `@app.METHOD(...)` or `@router.METHOD(...)` |
| Routers | `APIRouter()` instances, often in `routers/` or `api/` |
| Dependencies | `Depends(...)` parameters in route functions |
| Services | `services/`, plain functions or classes |
| DB | SQLAlchemy `models/`, `schemas/` (Pydantic) |

Trace order: route function → resolved dependencies (auth, db session) → service → ORM model.

## Laravel (PHP)

| Looking for | Where to find it |
|-------------|------------------|
| Web routes | `routes/web.php` — `Route::METHOD(path, [Controller::class, 'method'])` |
| API routes | `routes/api.php` |
| Controllers | `app/Http/Controllers/` |
| Middleware | `app/Http/Middleware/` (registered in `app/Http/Kernel.php`) |
| Form requests | `app/Http/Requests/` (validation) |
| Services | `app/Services/` (convention, not framework-required) |
| Eloquent models | `app/Models/` |
| Jobs / queues | `app/Jobs/` |

Trace order: route → middleware → form request validation → controller method → service → Eloquent model → DB.

## PayloadCMS

| Looking for | Where to find it |
|-------------|------------------|
| Config root | `payload.config.ts` |
| Collections | `src/collections/*.{ts,js}` (registered in `collections` array of config) |
| Globals | `src/globals/` |
| Custom endpoints | `endpoints` array on a collection or global |
| Lifecycle hooks | `hooks` field on a collection: `beforeChange`, `afterChange`, `beforeRead`, `afterRead`, `beforeDelete`, `afterDelete` |
| Access control | `access` field on a collection — functions returning boolean or query constraint |
| Field-level hooks | `hooks` on individual field definitions |

Trace order for a custom endpoint: `payload.config.ts` → collection definition → endpoint handler → invoked hooks → DB write/read.

Trace order for a default CRUD operation: REST/GraphQL request → access control function → field hooks → collection-level hooks → DB.

## Rails (Ruby)

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `config/routes.rb` |
| Controllers | `app/controllers/` |
| Models | `app/models/` (ActiveRecord) |
| Services / interactors | `app/services/` (convention) |
| Background jobs | `app/jobs/` |

Trace order: route → controller action → before_actions → service object (if present) → ActiveRecord model.

## Spring (Java)

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `@RestController` + `@RequestMapping` / `@GetMapping` etc. |
| Services | `@Service` annotated classes |
| Repositories | `@Repository` annotated classes (often `JpaRepository` interfaces) |
| Filters | `Filter` or `OncePerRequestFilter` implementations |

Trace order: controller method → injected services → repositories → JPA entity.

## Go (chi / gin / echo)

| Looking for | Where to find it |
|-------------|------------------|
| Routes | `router.METHOD(path, handler)` patterns |
| Middleware | `router.Use(...)` calls |
| Handlers | Functions of shape `func(w http.ResponseWriter, r *http.Request)` (chi/std) or framework-specific |
| Services | Convention varies; look for `internal/<feature>/service.go` |
| DB | `gorm`, `sqlx`, or hand-rolled `database/sql` queries |

Trace order: router setup → middleware chain → handler → service → repository.

---

## Fallback strategy

If none of the above stacks match (or the codebase mixes patterns):

1. Grep for the literal path string (e.g. `'/api/login'`, `"/api/login"`, `` `/api/login` ``)
2. Grep for the function name mentioned in the user's prompt
3. Grep for an HTTP verb + obvious noun (e.g. `POST.*login`)
4. Read `package.json`, `go.mod`, `composer.json`, `requirements.txt`, etc. to identify the framework, then search for that framework's conventions online
5. If still stuck, ask the user where the entry point is rather than guessing
