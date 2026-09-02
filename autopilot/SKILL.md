---
name: autopilot
description: Use when someone hands over work and leaves the keyboard, so nothing can be confirmed until they return — "gw mau mandi dulu", "gw tinggal dulu ya", "lanjut sampai kelar", "jangan tunggu gw", "gw approve semua pertanyaan di depan", "kerjain aja sampai jadi", "I'm heading out", "finish this while I'm gone", "just build it, don't wait for me", "jalan sendiri aja", "autopilot", or /autopilot. Also when queued prompts must run start to finish unattended, or when an ambiguous decision surfaces mid-task and the user has already said they will not be around to answer. Not for ordinary long-running work where the user is present and watching.
---

# autopilot — build while they are gone

They are not coming back to answer anything. They are coming back to **review**. Often this runs off a queue: prompts fire back to back with nobody at the keyboard, so a single stop stalls everything behind it.

So the work is only half the job. The other half is making an hour of unsupervised judgment auditable in about two minutes. Someone who returns to "sudah jadi" and has to read every touched file to find out what you decided got no leverage from leaving.

## Never stop, and say so out loud

Take the reading best supported by evidence in the sources, **state it in your visible output the moment you make it**, and keep going. Order the work so ambiguity-dependent parts come last — the longer you defer a guess, the more you have read before making it.

Saying it out loud as you go is what replaces a written log. A long run gets compacted; an assumption you only planned to mention at the end is one that may never be mentioned.

Surface a decision when two readings are both defensible, you invented something the sources do not contain, you narrowed the scope you were given, or two sources contradict each other. Not for craft — variable names, CSS ordering, which helper to extract. That is how you worked, not what you decided.

## Blocked work is inert and greppable

The default failure is not inventing things. It is that three unattended runs leave "not done" in three different states — one ships a placeholder to production, one reads as finished, one is commented out — and all three report "done".

A blocked slot has exactly two parts:

1. An `AUTOPILOT-BLOCKED` comment naming **what unblocks it**.
2. The slot's real markup, commented out beside it.

The slot renders nothing, runs nothing, ships nothing.

**Test it by deletion.** Delete your `AUTOPILOT-BLOCKED` comment. If a user, a test, or a build would still meet a word you invented — `KUTIPAN`, `HARGA`, `TODO`, `NEEDS INPUT`, `PLACEHOLDER` — the slot was never blocked. It shipped, and the comment above it changed nothing.

<Bad>
```html
<!-- AUTOPILOT-BLOCKED: butuh izin kutip dari beta user -->
<blockquote>KUTIPAN<cite>NAMA</cite></blockquote>
```
The comment is inert. The blockquote is not — a visitor reads "KUTIPAN".
</Bad>

<Good>
```html
<!-- AUTOPILOT-BLOCKED: butuh izin kutip dari beta user. Isi tiga blockquote di bawah,
     lalu buka comment-nya.
<blockquote>KUTIPAN<cite>NAMA</cite></blockquote>
-->
```
</Good>

`grep -rn AUTOPILOT-BLOCKED` is the complete list of what is unfinished. If it is not in that list, it is done — so never leave a section reading as finished when it is not. An obvious hole costs less than half-built work that looks complete.

## The handoff

Report with **recap** — same two headings, same budget, same anchors, no preamble. Do not write a report file; the terminal output is the deliverable.

One block goes above recap's headings, because with no file the assumptions live nowhere else:

```
## Assumptions
- <what you decided> — reverse: `path/to/file:41`
```

Each line names the decision and where to undo it. Cap at 6 and merge the rest. Nothing assumed and nothing blocked means the block is dropped, not filled with "none".

An assumption you cannot name the undo for is not an assumption — it is a commitment you made on their behalf. If the undo is expensive, that is the signal to leave the slot blocked instead.

Next Actions is where blocked slots land, anchored to `grep -rn AUTOPILOT-BLOCKED`.

Match the language of the handoff. Asked in Indonesian, report in Indonesian; the headings stay in English.

## What still stops you

Autopilot removes the **questions**, not the irreversible outward step. Do not push, merge, deploy, delete, or send anything outward — email, message, ticket, third-party call. Build it, stage it, name it in Next Actions, leave it one keystroke away.

"I approve everything in advance" is approval for judgment calls inside the work. It is not approval to do something irreversible and outward-facing with nobody watching.

## Red flags

- A placeholder that renders, runs, or ships
- An `AUTOPILOT-BLOCKED` comment sitting above live invented text
- A section left looking finished when it is not
- Assumptions visible only by inference from the output
- Saving every assumption for the closing report instead of stating it as you go
- "They said approve everything" as the reason to push or send

## Example

Landing page copy. Product notes say pricing is unsettled and no customer has agreed to be quoted.

```
## Assumptions
- Copy ditulis Bahasa Indonesia, bukan Inggris — reverse: `index.html:13`
- Nama tier "Gratis" dan "Pro" dikarang, README cuma menyebut "free + one paid" — reverse: `index.html:49`

## What Was Done
- Hero, tiga kartu fitur, footer, dan empat meta tag terisi — `index.html`
- Testimoni dan angka harga diblokir, tidak ada yang tayang — `grep -rn AUTOPILOT-BLOCKED`

## Next Actions
- Isi tiga blockquote testimoni setelah izin kutip turun — `index.html:26`
- Isi angka harga begitu final — `index.html:49`
```
