---
name: ant-dedoc-scramble
description: Keeps Dedoc Scramble OpenAPI 3.1 docs accurate for Laravel APIs—Form Requests for inputs, API Resources for responses, Sanctum middleware for auth, PHPDoc for summaries and edge-case responses, config/scramble.php, export and UI. Use when the user mentions Scramble, Dedoc, OpenAPI, Swagger, scramble:export, /docs/api, or API documentation generation for Laravel.
---

# Dedoc Scramble (Laravel OpenAPI)

[Scramble](https://scramble.dedoc.co/) generates OpenAPI **3.1.0** from routes, Form Requests, API Resources, and controllers. Prefer code-first docs; annotations are optional enrichments.

## Configuration

Tune `config/scramble.php`:

| Setting | Role |
|--------|------|
| `api_path` | Document routes under this prefix (commonly `api`) |
| `info.version` | Often from `API_VERSION` |
| `info.description` | Docs homepage blurb |
| `ui.title`, `ui.theme`, `ui.hide_try_it` | UI (Try It when not hidden) |
| `servers` | API base URLs; `null` may derive from `api_path` / `api_domain` |

## Requests

- Use **dedicated Form Request** classes; avoid inline `$request->validate()` on documented endpoints.
- Use **explicit rules** so Scramble infers types (`required`, `string`, `integer`, `Rule::in`, `date`, `email`, etc.).
- Put **query** and **body** rules in the Form Request `rules()` (e.g. `sometimes` + `page`, `per_page`, filters).
- Optional: method PHPDoc with `@bodyParam`, `@queryParam`, `@urlParam` for human-readable descriptions.

## Responses

- Return **JsonResource** / `JsonResource::collection` (or project wrappers like `ApiResponse::json(Resource::make(...))`) so Scramble reads `toArray()` shape.
- Add `@return array<string, mixed>` on `toArray()` when helpful.
- **Do not** add `@response { ... }` on controller methods when the payload is already shaped by an API Resource—Scramble infers from the Resource.
- Use `@response` (or other Scramble annotations) when **not** using a Resource and the schema must be described explicitly.

## Authentication

- Routes behind `auth:sanctum` are documented as requiring authentication automatically.
- For custom auth, state requirements in controller PHPDoc (e.g. Bearer header).

## Errors and custom envelopes

- Validation: messages in Form Request `messages()` still feed doc/error UX.
- Multiple shapes (e.g. errors vs success): use separate `@response` blocks where Scramble supports them.
- **Custom pagination** or non-resource top-level keys: document with `@response` / PHPDoc so the OpenAPI schema matches the real JSON (e.g. nested `pagination`).

## Viewing and export

- UI (often `/docs/api`—confirm route in your app).
- `php artisan scramble:export` for the spec (e.g. `api.json`).

## When docs look wrong

- `php artisan route:clear` and `php artisan config:clear`
- Confirm Resource `toArray()` lists all fields; confirm Form Request rules cover all inputs; confirm return types are explicit.

## References

- [Scramble docs](https://scramble.dedoc.co/)
- [OpenAPI 3.1](https://spec.openapis.org/oas/v3.1.0)
- [Stoplight Elements](https://stoplight.io/open-source/elements) (typical Scramble UI)
