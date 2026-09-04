---
name: api-prototyper
description: Build a runnable mock API fast — real routes, HTTP methods, request validation, and response shapes, with hardcoded dummy data and no database — so a frontend can integrate before the real backend exists. Works with any stack by reading the current codebase for its conventions (framework, routing style, response envelope, test runner); accepts prompts, screenshots, API docs (file or URL), OpenAPI/Swagger specs, Postman collections, or example JSON as input. Use when the user says "prototype this API", "mock API", "dummy API", "stub API", "fake endpoints", "API dummy buat FE", "bikin mock API", "quick API for frontend integration", or invokes /api-prototyper. Not for UI/logic sanity-check prototypes and not for production API architecture with a real data layer.
---

# API Prototyper

Build a working mock API fast so the frontend can integrate before the real backend exists. Real routes, real validation, real response shapes — every handler returns hardcoded dummy data. No database, ever.

The prototype is the first draft of the real feature, not a throwaway: generate it the way this codebase writes production code (routing, handler layering, response envelope, naming, test style). Only the data is fake. When the real backend lands, handler bodies get replaced; routes, validation, tests, and contracts survive. Bias to speed everywhere: the fewest files the codebase's conventions allow, no scaffolding beyond what the endpoints need.

## Workflow

### 1. Gather the contract
Inputs may be any mix of: prompt, screenshots (read the image), API docs as file or URL (fetch it), OpenAPI/Swagger spec, Postman collection, example JSON. Docs and screenshots are the source of truth; infer missing details but keep every inference visible in the table below.

### 2. Read the codebase
Detect stack and conventions from what exists: framework, route registration, validation idiom, response envelope, naming, test runner, linter config. Match them — output should look like a teammate wrote it. Empty/greenfield directory: ask the user once which stack; never guess a framework into an empty folder.

### 3. Confirm the endpoint table (gate)
Before writing code, present one table and wait for approval — the cheapest place to catch a misreading. Mark inferred cells `(inferred)`. Running unattended or pre-approved: print it and proceed.

| Method | Route | Params / Body | Validation | Response (example shape) |
|--------|-------|---------------|------------|--------------------------|

### 4. Generate
- Production-shaped, per the codebase's conventions; minimum files.
- Dummy data hardcoded in the handler — static canned responses, no in-memory store.
- Writes return canned success: echo the validated payload + fake id/timestamps so the frontend can render what it submitted.
- Validation is real — bad input must fail in the stack's normal error shape; that's part of the contract.
- Lists: 5–10 realistic items, stable ids. Realistic values beat `"string"` placeholders.
- Image fields: `https://picsum.photos/seed/<id>/600/400` (seeded, stable per record) or `https://placehold.co/600x400`.
- Never create: models, migrations, repositories, database queries/config, external API calls. Must run with zero database setup.

### 5. Verify
- Happy-path tests only, one per endpoint, in the codebase's runner and style: status code + response shape. No edge cases, error branches, or auth flows.
- Run them and confirm each new test appears **by name in verbose output** — runners discover tests by naming convention (Go `Test` prefix, PHPUnit `test`), and a misnamed test silently never runs while the suite stays green.
- Run the project's existing linter/static analysis if configured (golangci-lint, pint/phpstan, eslint/biome, …) on the new files and fix what it flags. Don't install a linter the project doesn't have.

### 6. Hand off
Reply with the exact run command, base URL, sample curl per endpoint, and endpoint list. No README/docs files — the table lives in the conversation, the tests are the durable contract. Leave generated files uncommitted; never `git add` or commit them.

## Scope discipline
In: routes, methods, params/body, validation, response shapes, dummy data, happy-path tests.
Out — skip even if tempting: any data layer, real auth (hardcoded fake token is fine), deep error handling, edge cases, performance work, real pagination logic (fixed page meta), README/OpenAPI files. "Make it real" afterwards is a new task — this skill ends at a runnable mock.
