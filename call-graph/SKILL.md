---
name: call-graph
description: Trace how code actually executes and answer with a verified, plain-text call graph — entry point → callees, each node backed by a real `path:line`. Language-agnostic; works on any stack. Use whenever the user asks how a flow works, what calls a function, where a request goes after it lands, which layer owns a step, how production wiring differs from test wiring, or wants an architecture trace of a feature. Triggers on "call graph", "trace the flow", "execution flow", "request path", "what calls X", "who calls this", "where does X go", "how does X work", "walk me through the flow", "alurnya gimana", "ini dipanggil dari mana", "trace alur", "jelasin flow", "dari mana masuknya", and on explicit `/call-graph <question>`. Prefer this over prose explanation any time the honest answer is a chain of calls — a graph the user can verify beats a paragraph they have to trust.
---

# call-graph — trace the real execution path

Answer flow questions with a graph, not a paragraph. Read the source, follow the actual calls, and render a hierarchical plain-text tree where every node is a real symbol you can point at with `path:line`. The graph is the answer; prose only covers what a tree can't show.

The discipline that makes this useful is refusing to guess. A call graph that's 90% right is worse than no graph, because the reader can't tell which 10% is wrong and will act on all of it. Every node you draw is a node you opened the file for.

## When to skip the graph

A graph has a cost — for the reader too. Answer directly, no graph, when the question is:

- a single fact: what port, what version, what's the default value
- a single definition: what does this type look like, what does this constant mean
- a rename, a text edit, or anything not about execution
- a diagram request that isn't a call graph (ER diagram, sequence chart, infra topology)

If in doubt: does answering it require naming more than one function? If no, just answer.

## Workflow

**1. Fix the scope before reading anything.**
Turn the question into a specific start and stop. "How does auth work?" is not a scope — "from the login HTTP route to the point a token is persisted" is. State the scope you settled on in the answer, so the user can tell you if you traced the wrong slice.

**2. Read the project's own instructions first.**
`AGENTS.md`, `CLAUDE.md`, `README.md`. They tell you the layering (controller → action → service), the naming conventions, and where the entry points live. A framework's convention beats your prior about how the code is probably organized.

**3. Find the real entry point.**
Route table, CLI command registration, queue consumer, event listener, cron entry, test file — whatever actually starts the flow. Grep the route definition, don't infer it from a controller name. `references/entry-points.md` has per-framework recipes for locating these.

**4. Walk the edges outward, one hop at a time.**
At each node, open the file and read the body to find what it calls next. Resolve indirection instead of stopping at it: an interface call needs the bound implementation (check the DI container / service provider / module registration), a dispatched event needs its listeners, a queued job needs its handler. An unresolved hop is the most common place a call graph quietly becomes fiction.

**5. Check the test wiring only if you suspect it differs.**
Most codebases run the same graph in tests with different dependencies swapped in. That's not a different graph and doesn't deserve a second tree. Show a Tests section only when the shape genuinely changes — a fake layer that short-circuits a branch, a different entry point. Never invent a test graph you didn't read.

**6. Verify every node, then write the evidence block.**
Before you write a node into the graph, you should have seen its definition. Record `path:line` as you go — reconstructing it afterwards is where errors get introduced. If you couldn't verify something, that's information: mark it `[unverified]` and say what you couldn't resolve. Honest gaps are useful; silent guesses are not.

**7. Add only what the tree can't say.**
Conditions, retries, error propagation, N+1 risks, surprising ordering, dead branches. Three to five lines. Don't re-narrate the graph in prose — the reader just read it.

**8. Stop when the scope is covered.**
Don't keep expanding into adjacent subsystems because they're interesting. If you spot something worth tracing next, name it in one line and let the user ask.

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

Lead with the graph. The full grammar, the `src:` evidence block, condition/queue markers, and worked examples live in `references/output-format.md` — read it before your first graph in a session.

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

Core rules: plain text only, never Mermaid — this renders identically in a terminal, a diff, and a commit message, which is where these answers get reused. Two-space indentation carries the hierarchy; the root takes no arrow. The `ts` fence is a formatting choice for monospace and arrow contrast, not a claim about the language — keep it even when tracing PHP, Go, or Python, so graphs from different repos stay comparable.

## Pinning the convention in a project

If the user wants every future answer in this format, not just this one, merge a short rule into the project's `AGENTS.md` or `CLAUDE.md` — append it, never replace existing content:

```markdown
## Call graph answers

For flow, path, trace, caller, and how-it-works questions, lead with a plain-text
hierarchical call graph in a `ts` fence. Two-space-indented `→` children. Show
Production always, Tests only when they differ. Include verified `path:line`
evidence for every node. Skip the graph for trivial single-fact questions.
```

Offer this once, when it's relevant. Don't write to their instruction files unprompted.

## Reference files

- `references/output-format.md` — the full output contract: grammar, markers, evidence block, worked examples. Read before producing a graph.
- `references/entry-points.md` — how to locate the real entry point per framework (Express, NestJS, Next.js, Laravel, Rails, FastAPI, Spring, Go, queues, CLIs), and how to resolve DI, events, and jobs.
