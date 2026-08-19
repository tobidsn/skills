---
name: call-graph
description: Trace how something actually runs, then answer with a verified plain-text tree where every node carries an openable `path:line`. For execution-path, request-path, and "what calls X" questions ("alurnya gimana", "ini dipanggil dari mana"), for architecture overviews, and — on "user flow" / "approval flow" — for non-code flows. Not for single-fact lookups.
---

# call-graph — trace the real path, then draw it

Answer flow questions with a graph, not a paragraph. Read the source, follow the actual edges, and render a hierarchical plain-text tree where every node is a real thing you can point at. The graph is the answer; prose only covers what a tree can't show.

The discipline that makes this useful is refusing to guess. A graph that's 90% right is worse than no graph, because the reader can't tell which 10% is wrong and will act on all of it. Every node you draw is a node you opened the file for.

Not this skill: `code-storyteller` (a narrated HTML walkthrough, one move at a
time), `explain-code` (teaching how a mechanism works, no trace needed),
`mindmap-architect` (a hierarchy of ideas, not of calls). If the user wants to
*understand a concept*, they want those. This one answers *what reaches what*.

## Step 0 — pick the material

The method is one thing; the material varies. Decide which you're tracing before you start, because it changes what a node is and what counts as evidence.

| Material | Nodes are | Edges are | Evidence is |
|----------|-----------|-----------|-------------|
| **Code** (default) | functions, methods, jobs, queues, stores | calls and dispatches | `path:line` of the definition |
| **Interface** | surfaces — screens, panes, lists, drawers, fields | moves the person makes | component/route file, or the design frame name |
| **Orchestration** | tasks, agents, workers, waves | data dependencies between tasks | plan/spec file section, workflow step, task ID |
| **Process** | stages — pipeline steps, CI jobs, approval gates | handoffs and triggers | config file line, SOP section, ticket state |

Most requests are code, so default there unless the question is clearly about something else. When the user says "user flow", "screen flow", or names a design, that's interface. When they ask how a multi-agent run or a plan will be split up, that's orchestration. When they ask about a deploy, a data pipeline, or an approval chain, that's process.

`references/non-code-graphs.md` covers the three non-code materials — read it when the material isn't code. Everything below applies to all four.

## When to skip the graph

A graph has a cost — for the reader too. Answer directly, no graph, when the question is:

- a single fact: what port, what version, what's the default value
- a single definition: what does this type look like, what does this constant mean
- a rename, a text edit, or anything not about how something runs
- a diagram request that isn't a flow (ER diagram, infra topology, org chart)

If in doubt: does answering it require naming more than one node? If no, just answer.

## Lead with the graph, don't wait to be asked

Flow questions are the obvious case, but the graph earns its place in a wider set: **architecture summaries, project overviews, onboarding explanations, code walkthroughs, PR descriptions for a feature that spans layers.** In all of those the reader's real question is "what reaches what", and a tree answers it in a fraction of the words prose needs.

So when someone asks "explain this codebase" or "what does this module do", open with the graph of its main flow, then narrate around it. Don't ask permission first — a graph they didn't want costs them ten seconds to skip.

The exception is the skip list above. A graph pasted onto a single-fact question reads as padding, which teaches the reader to stop trusting the format.

## Workflow

**1. Fix the scope before reading anything.**
Turn the question into a specific start and stop. "How does auth work?" is not a scope — "from the login HTTP route to the point a token is persisted" is. State the scope you settled on in the answer, so the user can tell you if you traced the wrong slice.

**2. Read the project's own instructions first.**
`AGENTS.md`, `CLAUDE.md`, `README.md`. They tell you the layering (controller → action → service), the naming conventions, and where the entry points live. A framework's convention beats your prior about how the code is probably organized.

**3. Find the real root.**
Route table, CLI registration, queue consumer, event listener, cron entry, test file — whatever actually starts the flow. Grep the route definition, don't infer it from a controller name. `references/entry-points.md` has per-framework recipes.

**4. Walk the edges outward, one hop at a time.**
At each node, open the file and read the body to find what it reaches next. Resolve indirection instead of stopping at it: an interface call needs the bound implementation (check the DI container / service provider / module registration), a dispatched event needs its listeners, a queued job needs its handler. An unresolved hop is the most common place a graph quietly becomes fiction.

**5. Check the test wiring only if you suspect it differs.**
Most codebases run the same graph in tests with different dependencies swapped in. That's not a different graph and doesn't deserve a second tree. Show a Tests section only when the shape genuinely changes — a fake that short-circuits a branch, a different entry point. Never invent a test graph you didn't read.

**6. Verify every node, then write the evidence block.**
Before you write a node into the graph, you should have seen its definition. Record `path:line` as you go — reconstructing it afterwards is where errors get introduced. If you couldn't verify something, that's information: mark it `[unverified]` and say what you couldn't resolve. Honest gaps are useful; silent guesses are not.

**7. Annotate the nodes, then add only what the tree still can't say.**
Work the checklist below: per-node facts become `?` / `!` lines on the node itself, flow-wide facts become two to five notes underneath. Don't re-narrate the graph in prose — the reader just read it.

**8. Stop when the scope is covered, and cap the graph at ~25 nodes.**
Don't keep expanding into adjacent subsystems because they're interesting. If you spot something worth tracing next, name it in one line and let the user ask.

Past roughly 25 nodes the `src:` block outgrows the tree and the answer becomes less readable than the prose it replaced — the exact failure this format exists to prevent. Collapse the deepest or least relevant subtree to `→ … (n more)`, say in one line what you elided and why, and offer to expand it. Scope creep and node creep are the same mistake: the graph stopped answering the question and started describing the codebase.

## What to say beyond the arrows

A tree shows what reaches what. It can't show how the flow behaves under stress, and that's usually what the reader is actually worried about. Work through these — mention what applies, skip what doesn't.

**Route each one by whether it has a single owning node.** If it belongs to one node, it goes in the tree as a `?` or `!` line directly under that node, where the reader meets it in context. If it spans the flow, it goes in the notes underneath. A fact about `SessionRepo.rotate` buried three paragraphs below a forty-line tree has been filed where nobody will connect it back.

In the tree, on the owning node:

- **Failure handling** (`?`). At each break point, which of three things happens: *retried* (transient — timeout, rate limit), *recovered* (falls back to a default, a cache, an alternative path), or *fatal* (propagates up and the flow dies). Naming which one it is tells the reader far more than "there's error handling here". If a break point has none of the three, say so — an unhandled break point is a finding.
- **Resource lifecycle** (`!`). Nodes that acquire something needing release: a transaction, a connection, a file handle, a lock, an external session. Say where release happens, and whether it still happens on the error path. Acquire-without-guaranteed-release is a leak worth flagging even when nobody asked.

In the notes, two to five lines:

- **Cardinality.** Does a node run once, or repeatedly per item? A loop or a per-row call is where N+1 problems live, and it's invisible in a tree that shows the node once.
- **Ordering and timing.** What's synchronous vs. deferred, what the caller waits for, what can arrive out of order.
- **Conditions and dead branches.** Paths that only run in a specific case, and paths that can no longer run at all.

## Navigation

Use tools that resolve symbols, in roughly this order of trust:

| Tool | Use for |
|------|---------|
| Language server / IDE "find references" | Ground truth for callers and definitions, when available |
| `ast-grep` | Structural search — call sites of a specific shape, not just a name |
| `rg` | Fast first pass: symbol names, route strings, class names |
| Compiler / type checker output | Proves a binding exists rather than assuming it |
| Targeted test run | Confirms a path is actually reachable |

`rg` alone is a starting point, not evidence — a name match is not a call. Confirm the hop by reading the call site.

Never guess a path, a symbol name, a caller, or a line number. If you're tempted to write a line number you didn't see, that's the signal to go open the file.

## Output

Lead with the graph. The full grammar, the `src:` evidence block, markers, and worked examples live in `references/output-format.md` — read it before your first graph in a session.

The shape, in brief:

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

src:
  EntryPoint → path/to/file.ts:LINE
  ComponentA → path/to/component.ts:LINE
```
````

Core rules: plain text only, never Mermaid — this renders identically in a terminal, a diff, and a commit message, which is where these answers get reused. Two-space indentation carries the hierarchy; the root takes no arrow. The fence is always `ts`, for every language and for non-code graphs — the coloring is incidental but it falls on the brackets, braces, and dotted names this notation is built from, and keeping it constant is what lets graphs from different sources be compared. Colour is a bonus: Slack and commit messages have none, so the tree must read on indentation alone.

## Pinning the convention in a project

If the user wants every future answer in this format, not just this one, merge a short rule into the project's `AGENTS.md` or `CLAUDE.md` — append it, never replace existing content:

```markdown
## Call graph answers

For flow, path, trace, caller, architecture, and how-it-works questions, lead with
a plain-text hierarchical call graph in a `ts` fence. Two-space-indented `→`
children. Show Production always, Tests only when they differ. Include verified
`path:line` evidence for every node. Cap the tree at ~25 nodes, eliding the rest
as `→ … (n more)`. Include a graph in project overviews, architecture summaries,
and code explanations. Skip it for trivial single-fact questions.
```

Offer this once, when it's relevant. Don't write to their instruction files unprompted.

## Reference files

- `references/output-format.md` — the full output contract: grammar, markers, evidence block, worked examples. Read before producing a graph.
- `references/entry-points.md` — locating the real entry point per framework (Express, NestJS, Next.js, Laravel, Rails, FastAPI, Spring, Go, queues, CLIs), and resolving DI, events, and jobs.
- `references/non-code-graphs.md` — the three non-code materials: interface flows, orchestration/delegation, and process flows. Read when the material isn't code.
