---
name: creator-plan
description: DEPRECATED. Only for when the user explicitly types `/creator-plan`. Tell them it is retired and point them to the AI Hero flow (/grill-with-docs, /to-spec, /to-tickets, /implement, /code-review, then a human smoke test). Do not trigger on generic requests like "fix this bug" or "build a feature".
---

# /creator-plan (deprecated)

Replaced by `/to-tickets` (tracer-bullet tickets with blocking edges), or by `/implement` directly for single-session work.

## Use this instead

The AI Hero flow (`mattpocock-skills` plugin, https://www.aihero.dev/skills) is the standard now:

```
/grill-with-docs  ->  /to-spec  ->  /to-tickets  ->  /implement (drives /tdd)  ->  /code-review
                  ->  SMOKE TEST by the developer (human, never the agent)  ->  MR with evidence
```

- Small, single-session work: skip `/to-spec` and `/to-tickets`, go `/grill-with-docs` -> `/implement`.
- Bugs: `/diagnosing-bugs`. Incoming issues you did not write: `/triage`.
- First time in a repo: `/setup-matt-pocock-skills` (tracker, labels, CONTEXT.md).
- Not sure which skill: `/ask-matt`.

The smoke test is mandatory and is done by a person. The agent may draft the scenario list from the acceptance criteria; it never fills in the results.
