# LLM integration fix patterns

Read this when the manifest grep found an LLM SDK (`anthropic`, `openai`, `@ai-sdk/*`, `langchain`, `bedrock`, `vertexai`, `ollama`). Three classes, in the order they bite.

## Model output as code — CRIT

A completion is untrusted input. It was assembled from a prompt that contained user text, retrieved documents, tool results, or a web page — every one of those is a channel an attacker can write to. Treating the reply as trusted because *your* code sent the request is the same mistake as trusting a form field because your own page rendered it.

```typescript
// BAD — every one of these is a sink
eval(completion);
db.$queryRawUnsafe(completion);                 // "generate the SQL for this question"
el.innerHTML = completion;                      // markdown rendered as HTML
exec(`ffmpeg ${completion}`);
fs.writeFile(path.join(ROOT, completion), …);   // "pick a filename"

// GOOD — the model chooses, the code executes
const choice = TABLES.find(t => t === completion.trim());   // allowlist, not interpolation
if (!choice) throw new ValidationError('unknown table');
const rows = await db.query('SELECT * FROM ' + choice + ' WHERE owner_id = $1', [userId]);

el.textContent = completion;                                // or sanitize before innerHTML
```

Structured output does not make it trusted: a JSON schema constrains the *shape*, never the *values*. A `{"table": "users; DROP …"}` validates fine.

The same applies to what the model reads. A completion built over a user-uploaded document is carrying that document's instructions — **indirect prompt injection is the delivery mechanism for every finding in this file**, and it is why "we control the system prompt" is not a defense.

Rendering markdown from a completion is XSS unless it is sanitized: image and link URLs in the output can exfiltrate the conversation to an attacker's host as a query string, without the user clicking anything.

## Data in prompts — HIGH

Everything in a prompt leaves the building. It reaches a third-party API, it is often retained for some window, and it may reach a log or a trace on the way.

What to grep for, and what makes each one a row:

| Sev | Finding |
|---|---|
| CRIT | an API key, DB credential, or `.env` value interpolated into a prompt or a system prompt |
| HIGH | a query over a shared table with no owner filter feeding the context — one user's prompt containing another user's rows |
| HIGH | personal data sent to a provider whose retention or region was never chosen (see the personal-data section in `SKILL.md`) |
| MED | full prompts, completions, or tool arguments written to application logs |

```php
// BAD — the whole row, including columns the caller cannot see
$context = Order::find($id)->toArray();

// GOOD — owner-scoped, field-explicit
$context = Order::where('user_id', $user->id)->findOrFail($id)->only(['status', 'total']);
```

The fix for the third row is usually a provider setting plus a contract, not code — say so in `Fix` rather than promising an edit.

## Tool and agent permissions — HIGH

An LLM with tools is a caller with no judgment and an attacker-writable instruction channel. Scope it the way you would scope an untrusted service account.

```python
# BAD
def run_sql(query: str): return db.execute(query)          # arbitrary SQL
def send_email(to, body): return mailer.send(to, body)     # arbitrary recipient
def delete_file(path: str): os.remove(path)                # arbitrary path

# GOOD
def get_orders(status: str):                                # no free-form query
    assert status in ALLOWED_STATUSES
    return db.execute(SQL, [current_user.id, status])       # user bound server-side, not by the model
```

Three checks:

1. **Does the tool's permission come from the session, or from the model's arguments?** A `user_id` parameter the model fills is not authorization — bind the identity server-side and let the model choose only the non-identity arguments.
2. **Can a tool write, spend, send, or delete?** Then the effect needs a human confirmation the model cannot issue on its own, or a hard cap (amount ceiling, recipient allowlist, dry-run default).
3. **Is there a loop with no bound?** An agent that retries tool calls until it succeeds is an unbounded spend and an unbounded blast radius — cap iterations and total tokens per request, and rate-limit the endpoint that starts it (`rate-limits.md`).

Report the finding at the tool definition's `path:line`, not at the model call — that is where the permission actually lives.
