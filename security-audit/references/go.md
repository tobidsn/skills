# Go fix patterns

Loaded when `go.mod` is present. BAD/GOOD only — the findings table doesn't need this file.

## SQL injection — CRIT

```go
// BAD
db.Query(fmt.Sprintf("SELECT * FROM users WHERE id = '%s'", id))

// GOOD
db.Query("SELECT * FROM users WHERE id = ?", id)          // MySQL/SQLite
db.Query("SELECT * FROM users WHERE id = $1", id)         // Postgres
```

`fmt.Sprintf` anywhere near `db.Query`/`db.Exec`/`db.QueryRow` is the signal. Column names and sort direction can't be bound — allowlist against a fixed slice:

```go
var sortable = map[string]bool{"name": true, "created_at": true}
if !sortable[sort] { return errBadSort }
```

## Command injection — CRIT

```go
// BAD
exec.Command("bash", "-c", "git checkout "+branch)

// GOOD
exec.Command("git", "checkout", branch)
```

`exec.Command` without a shell is already safe from metacharacters, so the finding is specifically `sh -c` / `bash -c`. Argv does not stop *argument* injection — a `branch` of `--upload-pack=…` is parsed as a flag. Reject a leading `-`, or pass `--` first.

## XSS — HIGH

```go
// BAD — text/template does not escape for an HTML context
t := template.Must(template.New("p").Parse(`<p>{{.Body}}</p>`)) // "text/template"
template.HTML(userInput)                                        // asserts safe; it isn't

// GOOD
import "html/template"
t := template.Must(template.New("p").Parse(`<p>{{.Body}}</p>`))
```

Check the import, not the call — `text/template` and `html/template` share the same API surface, so the two look identical at the call site. `html/template` is context-aware: it escapes differently inside an attribute, a URL, and a `<script>`. `template.HTML`, `template.JS`, and `template.URL` are explicit escape hatches — each one is a finding unless the value is a constant.

## Path traversal — HIGH

```go
// GOOD — Go 1.24+, the root is enforced below the path layer
root, err := os.OpenRoot(uploadsDir)
if err != nil { return err }
defer root.Close()
f, err := root.Open(name)   // anything escaping the root returns an error

// Pre-1.24
if !filepath.IsLocal(name) { return errBadPath }
full := filepath.Join(uploadsDir, name)
```

Never `filepath.Clean` + `strings.HasPrefix` alone — it misses symlinks and the `/rootfoo` vs `/root/foo` boundary. On archive extraction, cap decompressed size (`io.LimitReader`) and reject traversing entry names.

## Weak crypto / RNG — HIGH

```go
// BAD
import "math/rand"
rand.Int()                              // predictable, seedable, reproducible
if token == want { … }                  // short-circuits, leaks timing
md5.Sum([]byte(password))

// GOOD
import "crypto/rand"
b := make([]byte, 32)
if _, err := rand.Read(b); err != nil { return err }

argon2.IDKey(pw, salt, 1, 64*1024, 4, 32)                        // x/crypto/argon2
subtle.ConstantTimeCompare([]byte(token), []byte(want)) == 1      // crypto/subtle
```

Encryption: AES-GCM (`cipher.NewGCM`), never ECB or bare CBC — unauthenticated modes let an attacker modify ciphertext undetected. Fresh nonce per message from `crypto/rand`, never reused with one key. And never ignore a crypto error (`_, _ = encrypt(data)`) — fail closed.

## TLS verification disabled — MED

```go
// BAD
&tls.Config{InsecureSkipVerify: true}

// GOOD
pool := x509.NewCertPool()
pool.AppendCertsFromPEM(caPEM)
&tls.Config{RootCAs: pool, MinVersion: tls.VersionTLS12}
```

Always set `MinVersion` explicitly — an unset `MinVersion` is a separate LOW finding.

## Rate limiting — MED

```go
lim := rate.NewLimiter(rate.Every(time.Minute/6), 6)   // x/time/rate
if !lim.Allow() {
    http.Error(w, "too many requests", http.StatusTooManyRequests)
    return
}
```

One global limiter is a shared bucket, so one client starves everyone — key it per IP/user in a map guarded by a mutex, with eviction. Just as important, and usually missing entirely:

```go
srv := &http.Server{
    ReadHeaderTimeout: 5 * time.Second,   // Slowloris
    ReadTimeout:       15 * time.Second,
    WriteTimeout:      30 * time.Second,
}
r.Body = http.MaxBytesReader(w, r.Body, 1<<20)
```

A `http.Server{}` literal with no timeouts is itself a MED finding.

## Race conditions — HIGH

```go
// BAD
counter++                            // from multiple goroutines
cache[key] = val                     // concurrent map write -> runtime panic

// GOOD
mu.Lock(); counter++; mu.Unlock()
atomic.AddInt64(&counter, 1)
var cache sync.Map
```

Races bypass authorization under concurrency, not just corrupt data — a check-then-act on a permission flag is a security bug, not a correctness nit.

```bash
go test -race ./...
```

`-race` only reports races the test actually exercises, so a clean run is evidence, not proof. Never ship a known `-race` finding.

## Missing security headers — LOW

One finding for the app, never one per file.

```go
func secure(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        h := w.Header()
        h.Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains")
        h.Set("X-Content-Type-Options", "nosniff")
        h.Set("X-Frame-Options", "DENY")
        h.Set("Content-Security-Policy", "default-src 'self'")
        next.ServeHTTP(w, r)
    })
}
```

Set headers before the first `Write` or `WriteHeader` — after that they're silently dropped. Cookies: `http.Cookie{HttpOnly: true, Secure: true, SameSite: http.SameSiteLaxMode}`.

## Go-specific extras

- **Binding to `0.0.0.0`** when the service only needs localhost — MED, widens the attack surface for free.
- **`unsafe` and integer conversion** — `int(int64Val)` truncates silently on 32-bit; `len()` arithmetic that can go negative panics or wraps.
- **`gosec ./...`** as an optional SAST pass if it's already installed; don't install it as part of an audit.
