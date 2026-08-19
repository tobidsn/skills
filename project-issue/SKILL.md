---
name: project-issue
description: Review, triage, and act on issues for whatever project the user is currently in, using the gh CLI (GitHub) or glab CLI (GitLab). The repo is discovered from the project's own .git/config — nothing is hardcoded. Sub-command dispatched via `project-issue` (list open issues), `project-issue list`, `project-issue <id|url>` (deep-review one issue), or `project-issue <search term>`. Fetches the issue, parses any stack trace or repro steps, investigates the referenced code in the current project, and produces a triage verdict (real bug / needs-info / duplicate / wontfix + severity + root cause). Use whenever the user types `project-issue ...`, or says "cek issue", "check issue", "review issue", "triage issue", "issue #42", "ada issue baru gak", "issue mana yang perlu difix", pastes a github.com or gitlab.com issue URL, or otherwise wants to see or understand issues in the project they're working in.
---

# project-issue — issue triage for the current project

Turn an issue into a decision. Discover the repo from the project's own git remote, read the issue with the matching CLI (`gh` for GitHub, `glab` for GitLab), understand it against the code in the working directory, and end with a triage report: what's happening, the root cause, a verdict, and a recommended next step. This skill reads and recommends — it never branches, commits, or writes to the platform without confirmation.

## Step 0 — resolve the repo and platform (always, before anything else)

Every invocation starts here. Nothing about the repo is assumed.

```bash
git config --get remote.origin.url
```

1. **No git repo, or no `remote.origin.url`** → stop. Tell the user this skill needs a git remote to know which project's issues to look at, and show them what was checked.
2. **Parse the URL** into `host` and `path` (the `owner/repo` part). Both common forms must work:
   - SSH: `git@github.com:owner/repo.git` → host `github.com`, path `owner/repo`
   - HTTPS: `https://gitlab.com/group/subgroup/repo.git` → host `gitlab.com`, path `group/subgroup/repo`
   - Strip a trailing `.git`. GitLab paths may have more than two segments (subgroups) — keep the full path.
3. **Pick the CLI by host:**
   - `github.com` → `gh`
   - `gitlab.com` → `glab`
   - anything else → stop. Say the platform isn't recognized (only github.com and gitlab.com are supported) and show the host that was found.
4. **Preflight the CLI.** Check it exists and is authenticated:
   - GitHub: `gh auth status`
   - GitLab: `glab auth status`

   If the binary is missing or auth fails → stop with the concrete fix (`brew install gh` / `brew install glab`, then `gh auth login` / `glab auth login`). There is no fallback path — better a clear stop than a half-working guess.

Pass the repo explicitly on every call (`--repo <path>` for gh, `--repo <path>` for glab) so the skill behaves the same regardless of which subdirectory the user is in.

If the user pasted an issue URL for a *different* repo than the local remote, use the URL's repo instead and say so — but the local codebase investigation only makes sense when the URL matches the project you're standing in; flag it when they differ.

## Sub-command dispatch

Parse the first token after `project-issue`:

| Input                                   | Action                                       |
| --------------------------------------- | -------------------------------------------- |
| *(empty)* or `list`                     | List open issues (the `list` flow)           |
| a number (`42`, `#42`)                  | Review that issue (the `review` flow)        |
| an issue URL                            | Extract the trailing number, then review     |
| anything else                           | Treat as a search term over open issues      |

---

## `list` — survey what's open

Goal: a scannable table the user can pick from, sorted so the things that matter float up.

```bash
# GitHub
gh issue list --repo <path> --state open \
  --json number,title,labels,createdAt,comments --limit 30

# GitLab
glab issue list --repo <path> --output json --per-page 30
```

Render a compact table: **#**, **title** (trimmed), **labels** (surface anything that looks like severity/priority), **age**, **comments**. Sort live-breakage signals to the top: severity/priority labels, `bug` over `enhancement`, and recent activity. An old issue with fresh comments is hotter than its creation date suggests — lead with activity when it tells a different story than age.

For search terms:

```bash
gh issue list --repo <path> --search "<term>" --state open --json number,title,labels
glab issue list --repo <path> --search "<term>" --output json
```

End with one line: *"Review one with `project-issue <number>`."* Don't auto-review the whole list — reviewing is expensive and the user picks.

---

## `review` — understand one issue, then recommend a move

End state: a triage report in chat. This is the heart of the skill.

### 1. Fetch

```bash
# GitHub
gh issue view <number> --repo <path> \
  --json number,title,state,labels,author,body,comments,url,createdAt

# GitLab
glab issue view <number> --repo <path> --output json --comments
```

If the issue is closed, say so up front and ask whether the user still wants the analysis. Don't silently analyze a closed issue as if it were open.

### 2. Read the issue

Two kinds of issue need two kinds of reading:

**Machine-filed / error-tracker issue** — the giveaway is a bot author, monitoring labels, or a body dominated by an exception block and stack trace. Pull out:

- the **exception type + message**,
- every **`file:line`** frame in the trace — these are your investigation entry points,
- **severity and environment** if the body carries them (a production error is urgent; a dev-only warning usually isn't).

**Human-filed issue** — read for intent. Is it a bug (expected vs actual + repro), a feature request, or a question? A bug with no repro steps earns a `needs-info` verdict with specific questions — that's more useful than guessing.

### 3. Investigate against the current project

This is what makes the review worth more than re-reading the issue. For each `file:line` in the trace (or each file a human issue implicates), **read the actual code** in the working directory and one layer of neighbors. Confirm the line still exists and matches — traces come from released versions and the local branch may have drifted. Form a hypothesis about the root cause, not just where the error surfaced. Respect whatever conventions the project's own docs (CLAUDE.md, AGENTS.md, contributing guides) establish — a "fix" that violates them isn't a fix.

### 4. Check for duplicates

```bash
gh issue list --repo <path> --state all --search "<key error phrase>" --json number,title,state
glab issue list --repo <path> --all --search "<key error phrase>" --output json
```

Distinct issues can share a root cause (same bug, two stack traces). If you find siblings, name them — one fix may close several.

### 5. Report — use this structure

```md
## #<number> — <title>

**State:** <open/closed> · **Source:** <error-tracker / human> · **Severity:** <…, if known>
**Link:** <url>

### What's happening

<1–3 sentences: the error/request in plain language.>

### Root cause

<Your hypothesis, grounded in the code you read. Cite `path:line`. If the trace
is stale or you couldn't confirm, say exactly what's unverified.>

### Verdict

<real-bug | needs-info | duplicate-of-#N | wontfix/expected> — <one line why.>

### Recommended next step

<fix | plan | reply | close> — <one line why, in plain terms.>
```

Be honest in the verdict. "I can't reproduce this from the trace alone, here's what I'd need" is a valid and useful outcome. A confident wrong root cause is worse than a flagged uncertainty.

---

## Conventions

- **Never invent issue content.** Everything in the report traces to the issue body, comments, or code you actually read. If the CLI returns nothing or auth fails, say so — don't fabricate a plausible issue.
- **CLI auth is the user's.** Read freely. Any *write* to the platform (comment, label, close, reopen) is proposed as the exact command (`gh issue comment …`, `glab issue note …`, `gh issue close …`, `glab issue close …`) and run only after the user confirms — it's public and outward-facing.
- **Don't touch git.** No branches, no commits, no pushes. This skill reads and recommends; acting on the recommendation is a separate decision the user makes.
- **Nothing hardcoded.** The repo, platform, and conventions all come from the project the user is standing in. If any of them can't be determined, stop and say what's missing rather than guessing.
