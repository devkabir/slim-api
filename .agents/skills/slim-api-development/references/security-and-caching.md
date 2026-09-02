# Security & Caching Hardening Guide

This reference provides details on the security mechanisms, caching patterns, and production protection rules implemented in this Slim 4 API.

---

## 1. Memcached Caching Strategy

### Cache-Aside Pattern
1. **Read Path**: Check Memcached for the requested key via injected `Cache $cache`. If found, deserialize and return immediately. If cache misses, query MySQL, write to Memcached with TTL, and return.
2. **Write Path**: Execute mutation in MySQL. Invalidate cache keys (and increment namespace version). Optionally warm up the single-item cache.

### O(1) Versioned Namespace Invalidation
Never use `Memcached::getAllKeys()` or `flush()` in production. Instead, maintain an atomic integer version for collection keys:

```php
// In Service:
private const CACHE_NAMESPACE_LIST = 'todo_list_ns';

// Read:
$nsVersion = $this->cache->getNamespaceVersion(self::CACHE_NAMESPACE_LIST); // e.g., "3"
$cacheKey  = "todo_list_v{$nsVersion}_{$filter}_p{$page}_l{$limit}";

// Mutation (Create/Update/Delete):
$this->cache->incrementNamespaceVersion(self::CACHE_NAMESPACE_LIST); // Bumps to "4"
```
*Any subsequent read will look for `v4_...`, instantly rendering all previous `v3` pages stale without needing to iterate or delete individual list cache keys.*

### Memcached Security Checklist
- **Host Binding**: Bind strictly to `127.0.0.1`, a Unix domain socket, or a private VPC IP. Never bind to `0.0.0.0`.
- **Disable UDP**: In `memcached.conf`, set `-U 0` to mitigate DDoS amplification vulnerabilities.
- **SASL Authentication**: For cloud/remote Memcached instances, enable SASL (`OPT_BINARY_PROTOCOL => true`, `setSaslAuthData($user, $pass)`).
- **Graceful Fallback**: If Memcached goes down or the PHP extension is missing, [`Cache`](file:///Users/devkabir/Sites/slim/src/Config/Cache.php) returns `null`/`false` and allows the application to proceed with direct database queries.

---

## 2. Security Headers & Transport Security

### Security Headers Middleware
Configured in [`src/Middleware/SecurityHeadersMiddleware.php`](file:///Users/devkabir/Sites/slim/src/Middleware/SecurityHeadersMiddleware.php):

| Header | Production Value | Purpose |
| :--- | :--- | :--- |
| `X-Content-Type-Options` | `nosniff` | Prevents MIME-sniffing exploits |
| `X-Frame-Options` | `DENY` | Prevents clickjacking in iframes |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Protects referrer leakage on third-party navigation |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'` | Disallows untrusted asset loads & frame embedding |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enforces HTTPS on client browsers for 1 year |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=()` | Disables unwanted browser capabilities |

### HTTPS Enforcement & Status Codes
- In [`src/Middleware/HttpsEnforcementMiddleware.php`](file:///Users/devkabir/Sites/slim/src/Middleware/HttpsEnforcementMiddleware.php):
  - **Safe Methods (`GET`, `HEAD`)**: Redirect with `301 Moved Permanently`.
  - **Unsafe Methods (`POST`, `PUT`, `PATCH`, `DELETE`)**: Redirect with **`308 Permanent Redirect`** to ensure the HTTP client does **not** change the HTTP method or drop the request payload during redirection.

---

## 3. Rate Limiting

Configured in [`src/Middleware/RateLimitMiddleware.php`](file:///Users/devkabir/Sites/slim/src/Middleware/RateLimitMiddleware.php):
- **Tiers**:
  - `Read` operations (`GET`, `HEAD`, `OPTIONS`): 300 requests / minute.
  - `Mutation` operations (`POST`, `PUT`, `PATCH`, `DELETE`): 60 requests / minute.
- **Client IP Resolution**: Safely extracts client IP from trusted reverse proxy headers (`X-Forwarded-For`) with validation or falls back to `REMOTE_ADDR`.
- **Response Headers**:
  - `X-RateLimit-Limit`: Maximum allowed requests in window.
  - `X-RateLimit-Remaining`: Remaining request quota.
  - `X-RateLimit-Reset`: Unix timestamp when current window resets.
  - `Retry-After`: Seconds until retry (sent with HTTP 429).

---

## 4. Production Configuration Safeguards

In [`src/Config/Settings.php::validateProductionConfig()`](file:///Users/devkabir/Sites/slim/src/Config/Settings.php):
When `APP_ENV=production`:
1. `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` must be non-empty strings.
2. `DB_USER` cannot be `root`.
3. `HEALTH_CHECK_SECRET` (or `HEALTH_CHECK_KEY`) must be configured to protect `/health/ready`.
4. Detailed debug stack traces in `HttpErrorHandler` are strictly disabled.

---

## 5. Protected Health Probes

In [`src/Controllers/HealthController.php`](file:///Users/devkabir/Sites/slim/src/Controllers/HealthController.php):
- **Liveness (`/health/live`)**: Public endpoint returning `{status: "up", timestamp: "..."}`.
- **Readiness (`/health/ready`)**:
  - Requires authorization via `X-Health-Key` header, `Authorization: Bearer <secret>`, or `?key=<secret>`.
  - Verified using timing-attack resistant `hash_equals()`.
  - Executes `SELECT 1` on MySQL PDO and checks Memcached status via injected `Cache`.
  - Returns HTTP 200 `{status: "ready", services: {mysql: "connected", memcached: "connected"}}` or HTTP 503 `{status: "unhealthy"}`.

---

## 6. Incident Correlation & Log Sanitization

- **`X-Request-ID`**: Propagated from upstream proxies or generated as a 32-character cryptographically secure hex string (`bin2hex(random_bytes(16))`). Attached to response headers and pushed to Monolog processors so every log entry contains `extra.request_id`.
- **Log Redaction**: All logged exception messages and database queries are filtered to redact `password`, `pass`, `secret`, `key`, `token`, and `auth` credentials before writing to disk.
