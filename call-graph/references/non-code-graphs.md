# Graphs for material that isn't code

The tree, the indentation, the `src:` block, the refusal to guess — all of it holds when the nodes aren't functions. What changes is what a node *is*, what an edge *means*, and what counts as evidence. This file covers the three non-code materials.

## Contents

- [One method, four materials](#one-method-four-materials)
- [Evidence when there is no line number](#evidence-when-there-is-no-line-number)
- [Interface flows](#interface-flows)
- [Orchestration and delegation](#orchestration-and-delegation)
- [Process flows](#process-flows)

## One method, four materials

The columns are aligned by construction. Reading across a row tells you what the same idea is called in the material you're working in.

| Question | Code | Interface | Orchestration | Process |
|----------|------|-----------|---------------|---------|
| What are the nodes? | functions, jobs, stores | surfaces, units, fields | tasks, workers, waves | stages, jobs, gates |
| What are the edges? | calls, dispatches | moves the person makes | data dependencies | handoffs, triggers |
| What flows through? | the happy-path value | what is read and done | the work itself | the artifact or request |
| How can it be absent? | retry, recover, fatal | empty, loading, partial, error, denied | wrong context, missing input, misread intent | timeout, rejection, rollback |
| What does a node need first? | dependencies injected | data, permission, prior step, viewport | subgraph, method, verification, WHY | approval, artifact, credential |
| Where does untrusted input enter? | the transport edge | the field being typed in | the delegation prompt | the intake form or webhook |
| What wraps without reshaping? | middleware, interceptors | motion, focus, feedback | logging, session tracking | notifications, audit trail |
| What must be released? | connection, transaction, lock | attention — modals, focus | the worker itself | the lock, the environment |
| How do you prove it holds? | swap dependencies in tests | swap context in review | compare asked vs. built | dry-run, staging replay |

A row where you can't answer the question for your material usually means the graph has a hole, not that the row doesn't apply.

## Evidence when there is no line number

The `src:` block is what separates a graph from a plausible story, so it survives into non-code material — the target just changes. In order of preference:

1. **A file and line** — component file, workflow YAML, plan document, config. Still the best answer, and available more often than you'd think.
2. **A file and a named section** — `docs/plan/checkout.md § Wave 2`, `SOP-onboarding.md § Step 4`. Use when the source is prose without meaningful line stability.
3. **A stable identifier** — a Figma frame name, a Jira key, a workflow job id, a route path. Something the reader can search for and land on.
4. **Nothing openable** — the flow exists only in what the user just told you, or in your own proposal.

Case 4 is the one that matters. Do not dress a proposal up as a trace: label the whole graph `[proposed]` in its title, drop the `src:` block, and say plainly that it reflects the described intent rather than a verified source. A proposed graph is genuinely useful — for review, for a spec, for agreeing on a design before building — but the reader has to know which kind they're holding.

## Interface flows

Nodes are surfaces; edges are the moves a person makes to get somewhere else. The job — what the person came to get done — is the root.

What makes an interface graph worth drawing is rarely the happy path, which everyone already knows. It's the two things the happy path hides:

**Void states.** Every surface has ways its content can fail to be there: *empty* (nothing yet, and that's fine), *loading* (shape known, values not), *partial* (some arrived — show it, mark the rest), *error* (it broke and the person can act), *denied* (they may not see it). A surface with one designed state and four undesigned ones is 20% designed, and those four are the states people meet on their worst day. Enumerating them per surface is most of this graph's value.

**Needs on the edge.** What must be true before a surface may exist: a selected record, a permission, a completed prior step, a minimum viewport. If a surface can be reached without its needs met, the graph has a hole — and that hole is a design bug, not something to leave for whoever implements it.

Mark void states with `?` and needs with `!` so they stay readable inside the tree:

````markdown
graph: invoice review — from list to approved

```ts
Job: approve a pending invoice
  → InvoiceList (many)
    ? empty: "No invoices waiting" + link to import
    ? loading: row skeletons, 5 rows
    ? error: inline retry, keeps filters
    → [select row] InvoiceDetail (one)
      ! needs: selected invoice, invoices.read
      ? partial: header renders, line items still loading
      ? denied: not routed here — row is not selectable without invoices.read
      → [primary] ApproveDrawer
        ! needs: invoices.approve
        ! release: Esc / Cancel / Confirm all return focus to the row
        → [confirm] {api:POST /invoices/:id/approve}
          → InvoiceList (invalidated, row moves to Approved)
```

src:
  InvoiceList → app/invoices/page.tsx:24
  InvoiceDetail → app/invoices/[id]/page.tsx:31
  ApproveDrawer → components/invoices/ApproveDrawer.tsx:18
````

Notes worth adding for interface graphs:
- Cardinality per surface: one record, many, or live. A list masquerading as a card is the most expensive mistake to fix later, because it changes the shape of the surface rather than its styling.
- Where input is validated — at the field being typed in, or only at submit. Submit-only validation has moved the boundary to the wrong place.
- Any overlay without a named release move. That's a leak: the person is holding a surface the graph forgot about.
- How it holds when context changes: day one (everything empty), year three (40,000 rows, titles twice as long), least access (read-only member), small and slow (one hand, poor network, reduced motion). The graph should re-route, not degrade.

## Orchestration and delegation

Nodes are units of work; edges are data dependencies. Independent nodes group into waves that run in parallel, and the gate between waves waits for everything before it.

This graph answers "how should this be split up, and what has to finish first" — which is exactly the question that gets answered badly by a flat checklist, because a checklist can't show that items 2 and 3 are independent while 4 needs both.

````markdown
graph: [proposed] migrate auth to token rotation — 3 waves, 5 tasks

```ts
Task: rotate refresh tokens without logging anyone out
  wave1 [parallel]
    → T1 add rotation columns to sessions
      ! needs: migration conventions, verify: php artisan migrate --pretend
      ! why: rotation cannot be recorded without a place to record it
    → T2 write the rotation policy doc
      ! needs: current token TTLs
      ! why: the policy decides T3's branch conditions
  gate: both complete
  wave2
    → T3 implement TokenService.rotate
      ! needs: T1 schema, T2 policy, verify: php artisan test --filter=Token
      ? break: misreads intent if "rotate" is left ambiguous — pass the target graph
  gate: T3 green
  wave3
    → T4 swap the HTTP handler to rotation
      ! needs: T3 signature
    → T5 backfill existing sessions
      ! needs: T3 signature, verify: dry-run on staging replica
      ? break: unbounded backfill on a large table — require batching in the prompt
```

src:
  T1 → docs/plan/token-rotation.md § Wave 1
  T2 → docs/plan/token-rotation.md § Wave 1
  T3 → docs/plan/token-rotation.md § Wave 2
````

Four things every task node should carry, because a worker missing any of them will guess — and guessing is where delegation goes wrong:

- **the subgraph** — what to build, in the notation its domain uses
- **the method** — which conventions govern this work
- **the verification** — the exact command or check that proves it's done
- **the why** — the reason the node exists, not just the change to make

Delegation break points are worth marking with `?` because they're coordinator problems, not worker problems: *wrong context* (the worker doesn't know what you know), *missing input* (wave 2 needs wave 1's output and nobody passed it — the edge was invisible), *misread intent* (the worker did the letter, not the intent, because the subgraph was ambiguous).

When the work is actually done, the graph has a second use: compare what was asked against what was built. Extra nodes mean someone went off-script; missing nodes mean something was dropped. Both are easier to see as two trees than as a diff.

## Process flows

Nodes are stages — CI jobs, pipeline steps, approval gates, environment promotions. Edges are handoffs and triggers. Evidence is usually a config file, which makes these graphs unusually verifiable.

````markdown
graph: release pipeline — merge to main through production

```ts
push → main
  → .github/workflows/ci.yml
    → wave [parallel]
      → lint
      → unit tests
      → build image
    gate: all green
    → [if main] push image to registry
      → {ecr:app}
  → deploy-staging (auto)
    → migrate
      ! release: migration lock held for the run; not released if the job is cancelled
    → smoke tests
      ? fail: auto-rollback to previous task definition
  → [manual approval] deploy-production
    ! needs: staging smoke green, one approver from @platform
    → migrate
    → shift traffic 10% → 50% → 100%
      ? fail: halt at current percentage, alert, no auto-rollback
```

src:
  ci.yml → .github/workflows/ci.yml:1
  deploy-staging → .github/workflows/deploy.yml:44
  deploy-production → .github/workflows/deploy.yml:88
  approval → .github/environments/production (protection rule)
````

For process graphs the notes almost always want the same three things, because these are the questions people are really asking:

- **Where a failure leaves things.** Auto-rollback, halt-in-place, or half-applied. Half-applied is the answer nobody wants and the one that's usually true — say so when it is.
- **What isn't released on the unhappy path.** A migration lock held by a cancelled job, an environment left claimed, a feature flag left half-flipped.
- **Which gates are real.** A required approver is a gate; a notification nobody blocks on is not. Drawing a notification as a gate makes the pipeline look safer than it is.
