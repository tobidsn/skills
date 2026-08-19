# Design graphs — `/call-graph brainstorm`

Trace mode answers *what happens now*. Design mode answers *what should happen*, in the same notation, for a flow that has no source to read yet.

The swap is evidence for decisions. A trace is anchored by `path:line`, and its discipline is refusing to draw a node you didn't open. A design has nothing to open, so it needs a different discipline, and this is it: **every break point in the tree declares what happens when it breaks, and every fork with a defensible alternative is surfaced as a question rather than settled silently.** A design graph that shows only the happy path has told the reader nothing they couldn't have guessed.

That's also why this is a graph and not a paragraph. Prose lets you describe a flow without ever saying what happens when the third step fails. A tree with the checklist applied cannot — the gap is visible as a node with no `?` line under it.

## Contents

- [The answer has a trace's shape](#the-answer-has-a-traces-shape)
- [Ground it first](#ground-it-first)
- [The break-point checklist](#the-break-point-checklist)
- [The `open:` block](#the-open-block)
- [Worked example — register + email verification](#worked-example--register--email-verification)
- [Grounded into an existing codebase](#grounded-into-an-existing-codebase)
- [Iterating](#iterating)
- [Failure modes](#failure-modes)

## The answer has a trace's shape

Nothing about the output changes. Same `graph:` title line, same `ts` fence, same two-space tree, same evidence block underneath, same two-to-five notes. A reader who has seen one trace answer reads a design answer without re-learning anything — which is the entire reason the notation is fixed.

Three differences, all inside that shape:

- the title carries `[proposed]`
- the evidence block is partial or absent, and an `open:` block follows it
- the answer closes by offering the next mode

That last one is not optional. **Once `open:` is empty, the design is settled — say so and offer `/call-graph plan` in one line, then stop.** Something like:

> Design is settled — no open decisions left. Want me to turn this into a plan? `/call-graph plan register + email verification` writes it to `docs/plans/` with the as-is trace alongside this target shape.

Offer it; never run it. The user asked for a design, and writing a file they didn't ask for is the failure this whole mode boundary exists to prevent. While questions are still open, the closing line is the questions — don't repeat the offer every round.

## Ground it first

Design mode reads the repo before it draws. Not a trace — a scout, on the Small budget: **3–6 targeted reads**.

1. `AGENTS.md` / `CLAUDE.md` / `README.md` — the layering, the naming, where things live. This is what stops you proposing `UserRepo.insert` in a project whose convention is `app/Actions/{Resource}/`.
2. The route table, or whatever registers entry points. Tells you the middleware stack the new flow inherits for free.
3. One adjacent flow that already works — the closest existing sibling to what you're designing. A design that matches the shape of the flow next door is one the team can actually merge.

What that buys is a design expressed in the repo's own vocabulary, and it costs about as much as tracing six nodes.

**If the scout finds the flow already exists, stop and say so.** Offer the trace instead. Designing on top of something already built is the most expensive mistake this mode can make, and the scout is what catches it.

No repo, or a genuinely greenfield flow: skip the scout, use generic names, and say in one line that the design isn't anchored to any codebase.

### What evidence survives

| Node | `path:line`? | In `src:`? |
|------|-------------|-----------|
| Already exists — you read it during the scout | yes, real | yes |
| Already exists — you believe it does but didn't open it | no | no; mark it `[unverified]` |
| `[new]` — the work being designed | **never** | **never** |

Close a partial block with `[new] nodes — not yet implemented`, so the reader knows the missing entries are missing on purpose. A fully greenfield design has no `src:` block at all.

The title is always `graph: [proposed] <flow>`. Pasted into Slack without its prompt, an unmarked design tree is indistinguishable from a verified trace — and someone will act on it.

## The break-point checklist

This is the substance of the mode. Walk it before you show the tree; anything undeclared is either a decision you haven't made or a decision you're hiding.

**Every break point declares one of three outcomes.** *retried* (transient — timeout, rate limit, 5xx), *recovered* (falls back to a default, a cache, another path), *fatal* (propagates and the flow dies, with the status the caller sees). A break point with none of the three isn't a design, it's a wish. Break points are: anything crossing a network, anything touching a store, anything validating untrusted input, anything with a timeout.

**Every acquire declares its release, including on the error path.** Transactions, locks, connections, file handles, external sessions. `! acquires: db transaction — released on both commit and rollback` is a design decision; leaving it off means the first implementer picks one, and they'll pick the one that leaks.

**Every `[async]` declares its position relative to the commit.** Before or after, and what that buys. Dispatching a job inside a transaction that then rolls back is the single most common bug in flows shaped like this, and it's invisible unless the graph says which side of the commit the dispatch sits on.

**Every write declares its atomicity.** Two writes that must both land, or neither, belong under one transaction node. Two writes that are independent should say so — otherwise the reader assumes a guarantee you didn't design.

**Every response declares what was written when it's sent.** `429 — nothing written` and `202 — user row committed, email not yet sent` are different promises, and the client's retry behaviour depends on which one it got.

### `?` and `!` stay disjoint

`?` is **failure and absence only** — how this node breaks, or that it doesn't handle breaking. `!` is **everything the node needs, holds, or guarantees** — resources, ordering, cost, preconditions.

The distinction earns its keep because it makes the tree scannable: reading every `?` line in a design gives you the complete risk surface in one pass. Filing an ordering guarantee under `?` puts a non-risk in that list and dilutes it. So:

```
! ordering: dispatched after commit — a failed send never undoes the account
? provider 5xx or timeout: retried — backoff, then dead-letter
```

not `? dispatched after commit`, which reads as a failure and isn't one.

## The `open:` block

Where a trace has `src:`, a design has `open:` — the decisions that are genuinely still open, numbered so they can be answered by number.

```
open:
  1. <the decision> — <what it costs, each way>
     <the question>
```

**A fork belongs here only if it has a defensible alternative.** "Should the password be hashed?" is not open. "Should the hash run before the transaction opens, spending an argon2id on requests that turn out to be duplicates, or after, holding the transaction open across it?" is — both answers are reasonable and they trade different things.

Everything else you decide yourself, annotate in the tree, and move on. A design that surfaces twelve questions has handed the work back to the person who asked for it. **Cap it at five.** If you have more, you're either designing too large a scope or hedging on decisions you're capable of making.

Recommend, don't just ask. `Accept, or check existence first?` is a question; `I'd accept the wasted hash — the alternative is a TOCTOU race — but it's your call` is a recommendation, and it's what makes the block answerable in one line instead of a meeting.

### Recording versus asking

These are two jobs, and the block only does the first.

**The block is the record, so always render it.** It ships inside the answer, which means a design pasted into a PR or a Slack thread carries its open decisions with it, and `plan` mode can read the settled ones into Notes and Goals. A decision that exists only in a conversation is a decision the next reader doesn't have.

**Asking is separate, and the harness may offer something better than text.** Where an interactive question tool is available — Claude Code's `AskUserQuestion`, for one — use it *in addition to* the block for the items that are genuinely two-to-four discrete alternatives. Lead with your recommendation, and use the preview to put the competing tree fragments side by side:

```
Option A: hash before tx        Option B: check existence first
  → hashPassword                  → UserRepo.exists
    ! argon2id, before tx           ? found: recovered — skip the hash
  → createUserTx                  → hashPassword
                                  → createUserTx
                                    ! TOCTOU race between the two
```

Two shapes shown side by side settle a question that two paragraphs of prose only describe.

What stays text-only:

- **numeric and open-ended items** — "5/min per email and 20/min per IP?" is typed, not clicked
- **plain accept-or-modify items**, where forcing a menu invents a fork that isn't really there
- **anything past the tool's limit** — it takes at most four questions, and `open:` allows five

Never let an interactive answer be the only place a decision lives; fold it into the tree and the block as usual. And when the tool isn't there — another harness, a non-interactive run — the block alone is sufficient. It always was.

## Worked example — register + email verification

Greenfield: no repo scouted, so generic names and no `src:` block.

````markdown
graph: [proposed] register + email verification

```ts
POST /api/register
  → {middleware} rateLimit(ip + email)
    ? over limit: fatal — 429 with Retry-After, nothing written
  → validateRegisterInput
    ? invalid: fatal — 422 with per-field errors, nothing written
  → normalizeEmail
  → hashPassword
    ! cost: argon2id — runs before the transaction opens, on purpose
    ? timeout: fatal — 500, nothing written
  → createUserTx
    ! acquires: db transaction — released on both commit and rollback
    → UserRepo.insert
      ? duplicate email: recovered — unique violation caught, diverts to the already-registered branch
      → {store:users}
    → VerificationTokenRepo.insert
      ? insert fails: fatal — tx rolls back, no orphan account
      → {store:verification_tokens}
  → [async] SendVerificationEmailJob
    ! ordering: dispatched after commit — a failed send never undoes the account
    → {queue:mail}
      → MailProvider.send
        ? provider 5xx or timeout: retried — backoff, then dead-letter
  → [on success] RegisterAcceptedResponse
    ! promises: 202 — user row committed, email not yet sent
  → [on duplicate] RegisterAcceptedResponse
    → [async] SendAlreadyRegisteredEmail

POST /api/verify-email
  → consumeVerificationToken
    ? expired, unknown, or already used: fatal — 410, no state change
    → {store:verification_tokens}
  → UserRepo.markVerified
    → {store:users}
  → VerifiedResponse
```

open:
  1. hashPassword runs before the transaction — a duplicate email spends a full
     argon2id for nothing. The alternative, checking existence first, is a TOCTOU
     race and leaks whether an address is registered by timing.
     I'd accept the wasted hash. Agree?
  2. Duplicate registration returns the same 202 as success, so the endpoint can't
     be used to enumerate addresses. The cost is that a real user who forgot they
     registered gets no direct signal — only the already-registered email.
     Confirm that trade, or do you want an explicit 409?
  3. Verification token TTL and reuse are undecided. I'd go 24 hours, single-use,
     invalidating any prior unused token for the same address.
  4. Rate limit is keyed on ip + email but the numbers aren't set. 5/min per email
     and 20/min per IP?
````

Two entry points in one graph, because verification is meaningless without registration — the second root is what makes the token's lifecycle legible. Split them only when they can be built and shipped independently.

Note what the tree does *not* contain: no `//` comments, no line numbers on anything, and no node without a declared outcome at every break point. Notes below the block stay to the same two-to-five lines as any other graph, and cover only what spans both roots.

## Grounded into an existing codebase

Same graph, scouted into a Laravel repo. The shape is unchanged; the names come from the project, and existing nodes carry real evidence.

````markdown
graph: [proposed] register + email verification

```ts
POST /api/register
  → {middleware} throttle:register
    ? over limit: fatal — 429 with Retry-After, nothing written
  → [new] RegisterController.__invoke
    → [new] RegisterRequest.validated
      ? invalid: fatal — 422, nothing written
    → [new] RegisterAction.execute
      ! acquires: db transaction — released on both commit and rollback
      → User::create
        ? duplicate email: recovered — unique violation diverts to the already-registered branch
        → {mysql:users}
```

src:
  throttle:register → app/Providers/RouteServiceProvider.php:52
  User::create → app/Models/User.php:18
  [new] nodes — not yet implemented
````

`throttle:register` carries a line because the scout read the limiter definition; had it not existed yet, it would be `[new] {middleware} throttle:register` with no line and no `src:` entry. That distinction is the whole point of grounding — the reader can see exactly which parts of the design are already standing.

## Iterating

Answers come back by number, or as selections when you asked interactively. Either way:

1. **Redraw the whole tree.** Never a diff, never "and add this node under `createUserTx`". A tree is read as a shape, and a patch to a shape is unreadable — the reader has to reconstruct it mentally, which is the work the graph was supposed to do for them.
2. **Fold settled decisions into the tree** as `?` / `!` lines, and drop them from `open:`.
3. **Renumber what's left**, and add anything the answers exposed. Answers routinely create questions — decision 2 settling on "no explicit 409" raises whether the already-registered email needs its own rate limit.
4. **Stop when `open:` is empty**, and close with the offer described in [The answer has a trace's shape](#the-answer-has-a-traces-shape) — the design is settled, `/call-graph plan` is the next step, and you are not taking it.

A round that produces no change to the tree means the remaining questions aren't about the design, and you should say so rather than iterate for form.

## Failure modes

Specific to this mode, on top of the ones in `output-format.md`:

1. **A line number on a `[new]` node.** The one unrecoverable error here: the reader can check a wrong line, but they can't check a line for a file that doesn't exist, so it survives review and lands in the plan.
2. **Happy path only.** A tree where several nodes cross a network and not one has a `?` line. Run the checklist.
3. **A `TBD` node.** If you don't know what a step is, it isn't a node — it's an `open:` item.
4. **Designing something that already exists.** The scout exists to catch this. Offer the trace.
5. **Deciding a real fork silently.** Every design contains choices; the ones with a defensible alternative belong in `open:` where the user can overrule them. Burying one as a `!` line presents a decision as a fact.
6. **An `open:` block that's really a questionnaire.** More than five, or questions you could answer yourself with one more read, means you've handed the work back.
7. **A design that ignores the scout.** Generic names and a shape that doesn't match the adjacent flow, in a repo that has clear conventions. It'll read fine and be unmergeable.
