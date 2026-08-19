# Plan mode — `/call-graph plan`

Produce one Markdown file at `docs/plans/<YYYY-MM-DD>-<slug>.md`. Nothing else — no code edits, no branches, no commits.

Trace mode shows what runs. Design mode shows what should. Plan mode carries **both graphs in one document**, so the executor gets a map of what they're walking into, a map of where it's going, and a diff they can see at a glance instead of reconstructing it from bullet points.

That only works if the first graph is true. A graph that's 90% right is worse than no graph, because the reader can't tell which 10% is wrong and will act on all of it. **Every node in the as-is graph is a node you opened the file for.** If you're not willing to read, write a prose plan instead — this format has nothing to offer you.

## Contents

- [Learn the project first](#learn-the-project-first)
- [Trace the as-is path](#trace-the-as-is-path)
- [The two graphs](#the-two-graphs)
- [Wiring the graphs to the prose](#wiring-the-graphs-to-the-prose)
- [When there is nothing to trace](#when-there-is-nothing-to-trace)
- [Updating an existing plan](#updating-an-existing-plan)
- [What this mode refuses](#what-this-mode-refuses)

## Learn the project first

Assume nothing about the language, the framework, or whether the project even has a browser, a test runner, or a build step. Read in this order and let it override every default:

1. **`AGENTS.md` or `CLAUDE.md`** — repo root first, then the nearest one to the work. Source of truth for the stack, the layout, and how to build, test, and run. It usually names the layering too, which tells you what the graph's levels should be. `Done when` has to use this project's real verification commands, and they come from here rather than a guess.
2. **The code the task touches** — you're about to trace it anyway.

No instruction file? Infer conventions from manifests, test directories, and script definitions, and say so in Context.

## Trace the as-is path

Same walk as trace mode, same sizing table, same rules — `rg -n` to find the line, read the body around it, resolve indirection rather than stopping at it, record `path:line` while the file is open. The scope statement matters more here than anywhere else, because the plan is handed to someone who wasn't in the conversation: `scope traced: POST /api/login → the point a token is persisted` lets them tell you immediately that you traced the wrong slice.

If a Small trace keeps growing, stop and say so before spending ten times the budget on a document nobody asked to be that long.

## The two graphs

The `## Graph` section carries exactly two, in this order.

**The as-is graph** is a trace: full `src:` block, every node verified. This is the executor's map of what's load-bearing and easy to break.

**The `[proposed]` graph** is intent: `[new]` nodes marked, never given line numbers, and **no `src:` block at all**. This differs from a standalone design graph, which may carry evidence for nodes that already exist — here the as-is graph sits directly above with that same evidence, so repeating it is noise the reader has to diff by eye.

**Keep the unchanged nodes in both.** That shared spine is what makes them comparable; a proposed graph containing only the new work is a list, and the reader can't see where it attaches.

## Wiring the graphs to the prose

One source of truth, not two descriptions that drift:

- Each `[new]` or changed node in the proposed graph **earns a Goal that names it**. A node with no Goal means the graph is speculative; a Goal with no node means the graph is incomplete. Naming the node in the bullet makes the link checkable and costs nothing.
- Facts about a single node stay **on that node** as `?` / `!` lines, where the reader meets them in context.
- Facts about the flow as a whole become **Notes** bullets — cardinality, ordering, dead branches, and who else calls the path you're about to change. That last one is worth checking every time: adding a guard to a path a cron job already calls will break that caller, and the trace is where you'd have noticed.

## When there is nothing to trace

A plan for a flow that doesn't exist yet has no as-is graph. **Say so in one line and carry only the proposed graph** — never fabricate an empty trace or a skeleton of nodes you haven't read:

> No as-is graph: `/api/register` isn't registered in the route table, so there is no current path to trace. The proposed graph below is the whole design.

The rest of the document is unchanged. Where the plan attaches to existing code — a model, a middleware group, a queue — those nodes still get real evidence in the proposed graph's context, and Context still lists them. This is the one case where the proposed graph may carry a partial `src:` block, for the same reason design mode does: there's no as-is graph above it holding the evidence.

If the design came out of `/call-graph brainstorm`, reuse it rather than redrawing — its `open:` items should all be settled by the time a plan is written. Any that aren't are Notes bullets flagging an unresolved decision, not silent choices.

## Updating an existing plan

Point plan mode at an existing plan file and it edits in place. **Keep the original date prefix** — the date records when planning started, not when you last touched it. Preserve the `Implementation` section and everything already logged in it — that's the executor's work, and re-tracing is not a reason to discard it.

Re-trace the as-is graph if the code has moved: a stale `path:line` is indistinguishable from a wrong one. As work lands, nodes graduate from the proposed graph into the as-is graph with real line numbers — that migration is a useful progress signal, so make it rather than letting the two graphs rot.

## What this mode refuses

- **A node you didn't open**, in the as-is graph. The single failure that makes this format worse than prose. `rg` matching a name is not evidence a call happens — confirm at the call site.
- **A line number on a `[new]` node**, or a `src:` block on the proposed graph. Invented evidence is the one thing a reader can't defend against.
- **Implementation code.** No "the change you'll make" blocks. Goals name outcomes; the proposed graph carries the shape; the diff belongs to whoever builds it.
- **Invented files.** Every `@path` in Context must already exist. Files to be created belong in Goals and appear as `[new]` in the graph.
- **A generic `Done when`.** "It works", "tests pass" with no test named — not checkable, so not done-when.
- **Git.** No branches, no commits, no PRs. Write the file and stop.
- **Padding.** "Write clean code", "follow best practices" — delete.

## The document

Read `references/plan-template.md` before writing the file: the exact section order, what each section is for, and a worked example end to end. It's the single source of truth for the document's structure, which is why none of it is duplicated here.
