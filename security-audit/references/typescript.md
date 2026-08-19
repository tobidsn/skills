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

## TLS verification disabled — MED

```typescript
// BAD
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
new https.Agent({ rejectUnauthorized: false });

// GOOD
new https.Agent({ ca: fs.readFileSync('internal-ca.pem') });
```

`NODE_TLS_REJECT_UNAUTHORIZED=0` disables verification process-wide, including calls you didn't write. Check `.env`, Dockerfiles, and CI config, not just source.

## Rate limiting — MED

```typescript
import rateLimit from 'express-rate-limit';

app.use('/api/', rateLimit({ windowMs: 15 * 60_000, max: 100 }));
app.use('/api/auth/', rateLimit({ windowMs: 15 * 60_000, max: 10 }));
```

Behind a proxy, set `app.set('trust proxy', 1)` — otherwise every request looks like one IP and the limit is either useless or a global outage. Also cap body size: `express.json({ limit: '100kb' })`.

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
