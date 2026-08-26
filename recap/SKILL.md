---
name: recap
description: Summarize the current session into a short, scannable recap under two headings — What Was Done and Next Actions. Use whenever someone asks "what have we done", "tldr", "recap", "summarize the session", "where are we", "catch me up", "sampai mana", "apa aja yang udah dikerjain", "rangkum dong", or invokes /recap, including when the ask arrives as a casual aside in the middle of other work. Not for summarizing a file, a PR, a diff, or a codebase, and not for a build, deploy, PR, or workflow status check — the subject is always the conversation you are in.
---

# recap — the session in two blocks

Someone asks for a recap because they lost the thread, or they are about to hand the work to someone else. They need to reload state in about ten seconds: what landed, and what is next.

## The shape

```
## What Was Done
- <what changed> — `path/to/file`

## Next Actions
- <imperative> — <blocker, if any>
```

Budget: **3–5 bullets** under What Was Done, **up to 3** under Next Actions. One line per bullet, no nesting. Merge the small ones — "renamed three helpers" is one bullet, not three.

**Drop a heading you have nothing real to put under.** A session still in flight might only have Next Actions. Never pad with "None" or a filler bullet.

**Match the language of the ask.** Asked in Indonesian, answer in Indonesian — the two headings stay in English so the shape stays recognizable.

Print the blocks and stop: no preamble, no title, no closing offer to continue. Then, if you were mid-task when the ask arrived, pick that work back up without announcing that you are.

## What goes where

One section is settled, the other is not. Keep them distinct.

**What Was Done** — results you observed: files written, tests that passed, commands that exited clean, a migration that applied. A verified finding counts too, and in a debugging session it is usually the most valuable line in the recap: "the 429s come from the Sanctum guard resolving after middleware" is a result. The grepping and file-reading that produced it is not — that was your method. Never a bullet for reading, searching, or exploring on its own.

Not plans, not attempts. An approach you tried that broke belongs under **Next Actions**, phrased so the dead end stays visible: `Retry the cache layer — the first attempt broke the feed test and was reverted`.

Order these the way the work happened; a chronological list snaps back into place faster than one sorted by importance. When merging collides with chronology, merging wins — place the merged bullet where the last of its parts landed.

**Next Actions** — imperative, specific, verb-first. Put the thing in flight first, and if it is stuck, name the blocker on the same line: "blocked" plus a reason beats "in progress". Someone picks these up cold, so "fix auth" fails and "key the limiter off the token ID instead of `auth()->id()`" works.

## Anchors

Every bullet, in both sections, anchors to something openable: a file path, a command, an endpoint, a PR number. Use `path/to/file.ts:42` when a specific line is the point — it is clickable in the terminal. Next Actions anchor to where the work goes rather than where it has been: the file to edit, the test to run, the doc to update.

## Grounding

Report what happened, not what was announced. A tool call that ran and returned output is evidence; a sentence saying "now I'll add the index" is not. A finding needs the same standard — you confirmed it, not suspected it.

Your own tool calls are the record of this session. `git log` is not: it also shows commits from before the session, and those are not yours to claim. If the conversation was compacted and you cannot tell whether something landed, spend one cheap check — read the file, or run `git log --oneline` and count only the commits you can match to work you did here. Never guess.

If the session has barely started, one line is the answer: `Nothing yet this session.`

## What not to write

- an opener like "Here's a summary of our session so far", or restating the request
- a bullet for reading, searching, or exploring — that is how you worked, not what you produced
- sub-bullets, tables, or emoji
- a closing offer to keep going

## Examples

A session that added rate limiting and left one thing stuck:

```
## What Was Done
- Added `RateLimitMiddleware`, 60/min default — `app/Http/Middleware/RateLimit.php`
- Wired it onto the `api` route group — `bootstrap/app.php:34`
- 429-path feature tests pass — `php artisan test --filter=RateLimit`

## Next Actions
- Key per-user limits off the token ID — blocked, the Sanctum guard resolves after middleware so `auth()->id()` is null at `app/Http/Middleware/RateLimit.php:28`
- Document the 429 response shape — `docs/api/errors.md`
```

A debugging session, asked for in Indonesian, where the diagnosis is the result:

```
## What Was Done
- Penyebab 500 di checkout ketemu: `total` dihitung sebelum diskon di-apply — `app/Actions/Order/CalculateTotal.php:41`
- Regression test untuk diskon 100% ditambah dan gagal seperti yang diharapkan — `tests/Feature/CheckoutTest.php`

## Next Actions
- Pindahkan perhitungan diskon ke sebelum `total` — `app/Actions/Order/CalculateTotal.php:41`
```
