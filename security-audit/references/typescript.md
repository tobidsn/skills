# TypeScript / Node fix patterns

Loaded when `package.json` is present. BAD/GOOD only — the findings table doesn't need this file.

## SQL injection — CRIT

```typescript
// BAD
await db.query(`SELECT * FROM users WHERE id = '${userId}'`);

// GOOD
await db.query('SELECT * FROM users WHERE id = $1', [userId]);
await prisma.user.findUnique({ where: { id: userId } });
await knex('users').where({ id: userId });
```

An ORM is not automatic safety — `prisma.$queryRawUnsafe`, `knex.raw`, and `sequelize.query` with interpolation are the same bug. Use the tagged form: `prisma.$queryRaw\`… WHERE id = ${userId}\`` parameterizes; `$queryRawUnsafe` does not.

Column names and sort direction can't be bound — allowlist them:

```typescript
const SORTABLE = ['name', 'createdAt'] as const;
if (!SORTABLE.includes(sort)) throw new ValidationError('bad sort');
```

## Command injection — CRIT

```typescript
// BAD
import { exec } from 'node:child_process';
exec(`git checkout ${branch}`);

// GOOD
import { execFile } from 'node:child_process';
execFile('git', ['checkout', branch]);
// or spawn('git', ['checkout', branch], { shell: false })
```

`shell: true` re-introduces the bug even with an argv array. And argv doesn't stop *argument* injection — a `branch` of `--upload-pack=…` is a flag; reject a leading `-` or pass `--` first.

## XSS — HIGH

```tsx
// BAD
el.innerHTML = userInput;
<div dangerouslySetInnerHTML={{ __html: comment }} />

// GOOD
el.textContent = userInput;
<div>{comment}</div>

// Must render HTML:
import DOMPurify from 'dompurify';
<div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(comment) }} />
```

React escapes children, not every position: `href={userInput}` still allows `javascript:`, and a user-controlled `style` or a spread `{...userProps}` onto a DOM element can inject handlers. Vue's `v-html` and Angular's `bypassSecurityTrustHtml` are the same finding.

## Path traversal — HIGH

```typescript
// BAD
fs.readFile(path.join(UPLOADS, req.query.name));

// GOOD
const full = path.resolve(UPLOADS, String(req.query.name));
if (!full.startsWith(UPLOADS + path.sep)) throw new ForbiddenError();
await fs.promises.readFile(full);
```

`path.join` does not stop `../` — `path.resolve` plus the prefix check does. The trailing `path.sep` is what stops `/uploadsfoo` passing as `/uploads`. `express.static` with `dotfiles: 'allow'` re-opens it.

### Destructive targets need two more checks — HIGH

A wrong read leaks one file; a wrong `fs.rm(dir, { recursive: true })` takes the volume. When the path feeds `rm`, `unlink`, `rename`, or a move, the prefix check is necessary and not sufficient:

```typescript
// BAD — path from the DB, ownership never checked
await fs.promises.rm(path.join(UPLOADS, project.folder), { recursive: true, force: true });

// GOOD
const project = await db.project.findFirst({ where: { id, ownerId: user.id } });   // ownership first
if (!project) throw new NotFoundError();

const target = path.resolve(UPLOADS, project.folder);
if (!target.startsWith(UPLOADS + path.sep)) throw new ForbiddenError();
if (path.relative(UPLOADS, target).split(path.sep).length < 2) throw new ForbiddenError();  // depth floor
await fs.promises.rm(target, { recursive: true });                                  // no `force`
```

`force: true` is what turns "the column was empty, so we resolved to the root" from a crash into a data-loss event — drop it and let the missing path throw. The depth floor is the check people skip, and it is the one that stops an empty or `.`-valued field.

## Weak crypto / RNG — HIGH

```typescript
// BAD
crypto.createHash('sha1').update(password).digest('hex');
Math.random().toString(36);
if (token === req.body.token) { … }

// GOOD
import { hash, verify } from '@node-rs/argon2';   // or bcrypt, cost >= 12
crypto.randomBytes(32).toString('hex');
crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b)); // throws on length mismatch
```

`timingSafeEqual` requires equal-length buffers, so hash both sides first if lengths can differ. Encryption: `aes-256-gcm` (authenticated), fresh IV per message, never reuse an IV with the same key.

**The cost argument is the finding, and wrappers hide it.** `await hashPassword(pw)` from an internal `lib/auth` reads as correct; the parameters are one level down:

```typescript
// lib/auth/hash.ts — or a dependency's dist/
export const hashPassword = (pw: string) => bcrypt.hash(pw, 4);   // cost 4, not the 10 default
argon2.hash(pw, { memoryCost: 512 });                             // far below the 19456 default
```

Open the wrapper before concluding hashing is fine, following it into `node_modules/` when that is where it lives. bcrypt below cost 10, or argon2 with memory/time cut down, is a HIGH finding reported at the wrapper's own `path:line` — note in `Fix` when it is upstream and cannot be changed in-repo.

## TLS verification disabled — MED

```typescript
// BAD
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
new https.Agent({ rejectUnauthorized: false });

// GOOD
new https.Agent({ ca: fs.readFileSync('internal-ca.pem') });
```

`NODE_TLS_REJECT_UNAUTHORIZED=0` disables verification process-wide, including calls you didn't write. Check `.env`, Dockerfiles, and CI config, not just source.

## SSRF — CRIT

```typescript
// BAD
const preview = await fetch(req.body.url).then(r => r.text());

// GOOD — allowlist the host, refuse redirects, bound the time
const { hostname, protocol } = new URL(req.body.url);
if (protocol !== 'https:' || !ALLOWED_HOSTS.has(hostname)) throw new ValidationError('host not allowed');

const { address } = await dns.promises.lookup(hostname);
if (ip.isPrivate(address) || ip.isLoopback(address)) throw new ValidationError('host not allowed');

await fetch(req.body.url, { redirect: 'manual', signal: AbortSignal.timeout(5000) });
```

`redirect: 'manual'` is the line people leave out: `fetch` follows by default, so an allowlisted host can 302 the request to `169.254.169.254` and hand back cloud credentials. Also check the scheme — `new URL('file:///etc/passwd')` parses fine — and note that the address you resolved is not necessarily the one the socket connects to (DNS rebinding). A blocklist of `localhost`/`127.0.0.1` misses `0.0.0.0`, `[::1]`, `2130706433`, and any hostname whose A record you control.

Next.js `/api/proxy` routes, image optimizers with an unrestricted `remotePatterns`, webhook registration, and SSR fetches from a user-supplied URL are where this shows up.

## Secrets in responses — HIGH

```typescript
// BAD — every column, including the ones added after this line was written
const user = await prisma.user.findUnique({ where: { id } });
res.json(user);                                   // passwordHash, resetToken, stripeCustomerId…

// GOOD — the field list is the contract
res.json(await prisma.user.findUnique({
  where: { id },
  select: { id: true, name: true, avatarUrl: true },
}));
```

A Prisma query with no `select`/`omit` reaching a response is the whole finding — grep `res.json(` and the return of a route handler back to the query. Prisma's `omit` in the client config (`omit: { user: { passwordHash: true } }`) is a good backstop but not the control, same as `$hidden` in Laravel. Server Components and server actions count: whatever a component returns to the client is serialized into the payload and is readable in view-source, `select` or no `select`.

## Rate limiting — MED

```typescript
import rateLimit from 'express-rate-limit';

app.use('/api/', rateLimit({ windowMs: 15 * 60_000, max: 100 }));
app.use('/api/auth/', rateLimit({ windowMs: 15 * 60_000, max: 10 }));
```

Behind a proxy, set `app.set('trust proxy', 1)` — otherwise every request looks like one IP and the limit is either useless or a global outage. Also cap body size: `express.json({ limit: '100kb' })`.

## Missing object-level authorization (IDOR) — CRIT

An auth middleware proves the caller is logged in, not that the row is theirs.

```typescript
// BAD — any authenticated user reads any project by id
const project = await prisma.project.findUnique({ where: { id: req.params.id } });

// GOOD — the owner is part of the query, not a comment
const project = await prisma.project.findFirst({
  where: { id: req.params.id, ownerId: req.user.id },
});
if (!project) throw new NotFoundError();      // 404, not 403 — don't confirm the id exists
```

The check has to sit on every resource route, including indirect loads (`campaign.projectId` → still verify the project's owner). A codebase with per-user data and zero ownership filters anywhere is one CRIT row for the app.

## Open registration — HIGH

A public `/register` or `/signup` handler that creates a user and issues a session, on an admin panel or multi-tenant dashboard, turns "logged in" into "anyone on the internet". Remove it, put it behind invite tokens, or create accounts disabled until approved — and rate-limit it either way. With NextAuth/Auth.js, an OAuth provider with no `signIn` callback allow-list is the same finding.

## Client headers as authorization — HIGH

```typescript
// BAD — Origin/Referer are client-controlled; curl sends anything
if (allowlist.includes(new URL(req.headers.origin).host)) return next();
if (allowlist.length === 0) return next();     // fail-open: no config = allow all

// GOOD — a server-side credential, and fail closed
if (!apiKeyValid(req.headers['x-api-key'])) throw new UnauthorizedError();
```

CORS is a browser courtesy, not authentication — a permissive `cors()` mount is LOW, but an Origin check *standing in for* auth is HIGH.

## Debug surface in production — HIGH

Swagger UI / GraphQL playground / introspection reachable without auth hands out the endpoint map; a default error handler that returns `err.stack` leaks paths and internals. Gate docs behind auth *and* role (a check that any registered user passes is no gate on a multi-tenant app), set `introspection: false` in prod, and delete leftover `/test`/`/debug` routes — scaffolding that shipped has no safe version.

## Race conditions — HIGH

```typescript
// BAD — two requests both read 1 remaining
const coupon = await prisma.coupon.findUnique({ where: { id } });
if (coupon.remaining > 0) await prisma.coupon.update({ where: { id }, data: { remaining: coupon.remaining - 1 } });

// GOOD — let the database enforce it, conditionally, in one statement
await prisma.$transaction(async (tx) => {
  const { count } = await tx.coupon.updateMany({
    where: { id, remaining: { gt: 0 } },
    data: { remaining: { decrement: 1 } },
  });
  if (count === 0) throw new ConflictError();
});
```

A single Node process is still concurrent: every `await` is a yield point, so read-modify-write across one is interleavable. Module-level caches and in-flight maps are shared state.

## Missing security headers — LOW

One finding for the app, never one per file.

```typescript
import helmet from 'helmet';
app.use(helmet());  // HSTS, nosniff, frameguard, referrer-policy
app.use(helmet.contentSecurityPolicy({
  directives: { defaultSrc: ["'self'"], scriptSrc: ["'self'"] },
}));
```

Next.js has no helmet — headers go in `next.config.js` `headers()` or middleware. Session cookies want `httpOnly: true, secure: true, sameSite: 'lax'`, and auth tokens belong in a cookie, not `localStorage`.
