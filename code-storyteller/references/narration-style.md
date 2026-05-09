# Narration Style Guide

This guide defines the **technical narrative** tone used by the `code-storyteller` skill. The default tone is `technical`. Two alternatives (`casual`, `tutorial`) are described at the bottom.

## Default tone: technical narrative

**Goal:** the reader feels like they are reading a well-edited engineering blog post — not a tutorial, not a casual chat, not a bulleted summary.

### Voice

- **Present tense, third person.** "The router accepts the request, validates the body, and dispatches to the controller."
- **No filler.** Cut: "Let's dive in.", "As we can see...", "Now we will...", "It's worth noting that..."
- **Concrete identifiers.** Always name the function/class/file you are describing. Don't say "the controller" when you mean `AuthController.login`.
- **Why before what, when non-obvious.** If a step exists for a non-obvious reason (caching, retry, rate-limit), explain *why* before describing *what*.

### Structure

- Each step in the timeline corresponds to one short narrative section (~100–250 words).
- Open the section with a one-sentence framing of *what this step does in the larger flow*.
- Close the section with a one-sentence handoff: what triggers the next step.
- Use prose paragraphs. **Avoid bullet lists in narrative sections** — reserve them for trade-off callouts only.

### Concrete examples

✅ **Good:**

> When the request lands on `POST /api/login`, Express dispatches it to the `validateLoginPayload` middleware before the controller ever sees it. Validation runs first to keep the controller's shape simple — by the time `AuthController.login` executes, `req.body` is guaranteed to have a non-empty `email` and `password`. Once validation passes, control hands off to the controller.

❌ **Bad** (filler, vague, listy):

> Let's dive into the login flow! As we can see, there's a middleware that runs first. It does the following:
> - Validates email
> - Validates password
> - Calls next()
>
> Then the controller is called.

### Dos and don'ts

| Do | Don't |
|----|-------|
| Name the function/file directly | Say "the function" or "the file" |
| Explain *why* a step exists when non-obvious | Restate what the code obviously does |
| Use connector phrases between steps ("Once validated...", "From there...") | Repeat "Then..." mechanically |
| Reference line numbers when zooming in | Quote large code blocks inline (the left column already shows it) |
| Stay under ~250 words per step | Pad to fill space |

## Tone: `casual`

A senior dev explaining over coffee. First-person plural OK ("we"), light analogies welcome ("the middleware is like a bouncer at the door"), but still substantive — no fluff, no emoji-heavy.

## Tone: `tutorial`

For junior devs. Permitted to use second person ("you'll notice that..."), explain *why* more often, and call out idioms ("this is a common pattern in Express — it's called middleware composition"). Slightly longer sections (~300 words) acceptable.

## Selecting a tone

The skill picks a tone in this order:

1. Explicit user flag (`--tone casual` etc.)
2. Inferred from prompt (e.g. "explain like I'm new" → `tutorial`)
3. Default: `technical`
