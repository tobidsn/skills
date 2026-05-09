#!/usr/bin/env bash
# Rebuilds examples/sample-story.html — the runtime quality target.
# Authoring model: a STORY = { title, steps[] } JSON object that
# drives the step-through experience. Backticks `inside strings`
# render as inline code chips at runtime.
set -euo pipefail

python3 <<'PY'
import json
from pathlib import Path

skill = Path.home() / ".claude/skills/code-storyteller"
src = (skill / "template.html").read_text()
fixture = skill / "examples/_fixture"


def code(rel: str) -> str:
    """Return the raw source of a fixture file (no escaping —
    the runtime sets it via textContent then Prism highlights it)."""
    return (fixture / rel).read_text().rstrip()


STORY = {
    "title": "EXPRESS · LOGIN FLOW",
    "steps": [
        {
            "chapter": "Setup",
            "filename": "routes/auth.ts",
            "code": code("routes/auth.ts"),
            "typeline": "`authRouter.post(path, ...handlers)` → `Router`",
            "title": "Wire the pipeline before the controller sees a thing",
            "body": "When a client posts to `/api/login`, the request enters the auth router. The router declares one middleware (`validateLoginPayload`) ahead of `authController.login`. That ordering is the first architectural guarantee in the flow.",
            "problem": "Controllers that defensively re-check `req.body` shapes drift toward dozens of duplicated guards.",
            "move": "Push validation up to the middleware layer; the controller assumes shape-correctness from the moment it runs.",
            "closing": "Express resolves the handler array left-to-right, so by listing `validateLoginPayload` first, the route author makes shape-checking a *precondition*, not an afterthought.",
        },
        {
            "filename": "middleware/validateLoginPayload.ts",
            "code": code("middleware/validateLoginPayload.ts"),
            "typeline": "`(req, res, next) => void` — 400 on miss",
            "title": "A middleware whose only job is the shape contract",
            "body": "`validateLoginPayload` confirms `req.body` contains both `email` and `password`, and that both are strings. Either check failing short-circuits the pipeline with `400 Bad Request`. The controller never runs on a malformed body.",
            "problem": "Missing `express.json()` makes `req.body` `undefined`; naive destructuring then crashes with a 500.",
            "move": "Default with `?? {}` so destructuring never throws — fail loud with a 400 instead of a noisy 500.",
            "closing": "Once both fields pass, `next()` hands control downstream. From this point on, downstream code can read `req.body.email` without a guard.",
        },
        {
            "chapter": "Hand-off",
            "filename": "controllers/AuthController.ts",
            "code": code("controllers/AuthController.ts"),
            "typeline": "`AuthController.login(req, res)` → `void`",
            "title": "Keep the controller thin and the failure mode generic",
            "body": "`AuthController.login` is intentionally minimal. It delegates the actual auth check to `authService.verify` and translates two outcomes into HTTP responses: success becomes a 200 with the service's result; any thrown error becomes a 401.",
            "problem": "Distinct error messages (`no such user` vs `wrong password`) leak account-existence information to attackers.",
            "move": "Catch every service error; respond with one opaque `401 { error }`. The specifics stay in server logs.",
            "closing": "The try/catch boundary is where HTTP-shaped error handling lives. The service throws — the controller speaks HTTP.",
        },
        {
            "chapter": "Verification",
            "filename": "services/AuthService.ts",
            "code": code("services/AuthService.ts"),
            "typeline": "`AuthService.verify({email, password})` → `{ token: string }`",
            "title": "Where the actual authentication happens",
            "body": "`AuthService.verify` looks up the user by email via `User.findOne`, delegates password comparison to the model's `comparePassword`, and — only when both checks pass — signs a JWT.",
            "problem": "Either failure path ought to be indistinguishable to the caller, or timing/error differences leak which leg failed.",
            "move": "Throw the same `'invalid credentials'` for both \"user not found\" and \"wrong password\". The constants matter as much as the order.",
            "closing": "Sign the JWT only after *both* the lookup and the password check succeed — never short-circuit either. `signJwt` runs last, on the success path only.",
        },
        {
            "chapter": "Persistence",
            "filename": "models/User.ts",
            "code": code("models/User.ts"),
            "typeline": "`User.findOne(query)` → `Promise<User | null>`",
            "title": "Encapsulate the password-comparison primitive",
            "body": "The Mongoose `User` model owns persistence and the password-comparison primitive. The schema stores `passwordHash` only — never the plaintext — and the instance method `comparePassword` wraps `bcrypt.compare`.",
            "problem": "Comparing password hashes with `===` opens a timing-attack channel — equal-string comparison is not constant-time.",
            "move": "Use `bcrypt.compare` inside the model so consumers never touch raw bcrypt and the timing-safe comparison stays put.",
            "closing": "A future hashing-strategy change touches one file. The `email` uniqueness constraint is enforced at the schema level, backed by a database index. From here, the result bubbles up through service, controller, and out as JSON.",
        },
    ],
}


# Substitute placeholders
out = (src
  .replace("{{TITLE}}", STORY["title"])
  .replace("{{STORY_JSON}}", json.dumps(STORY)))

dest = skill / "examples/sample-story.html"
dest.write_text(out)
print(f"Wrote {dest} ({len(out):,} bytes)")
PY
