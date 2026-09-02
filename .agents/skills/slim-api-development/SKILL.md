---
name: slim-api-development
description: >-
  Expert guide and runbook for developing, extending, testing, and maintaining high-performance,
  secure Slim 4 REST API applications with PHP 8.4+, PHP-DI, PDO MySQL, Memcached (Cache-Aside),
  and hardened PSR-7/PSR-15 middleware. Use whenever working on Slim 4 PHP micro-framework projects,
  adding CRUD endpoints, creating DTOs with strict validation, implementing repository/service layers,
  configuring cache-aside strategies, managing DI container definitions, or hardening API security.
---

# Slim 4 REST API Development Guide & Runbook

This skill provides comprehensive instructions, patterns, and runbooks for building, maintaining, and extending high-performance, secure **Slim 4** REST API services following modern **PHP 8.4+ Clean Architecture**.

---

## 🏛️ Architecture Overview & Standards

The codebase follows strict separation of concerns across layered components:

```
Request ──► [PSR-15 Middleware Pipeline] ──► [Slim 4 Route] ──► [Controller]
                                                                     │
                                                                 (DTO Validation)
                                                                     │
                                                                     ▼
                                                                 [Service]
                                                                /         \
                                                     (Cache-Aside)     [Repository]
                                                          │                 │
                                                     [Memcached]        [PDO MySQL]
```

### Core Conventions
- **PHP Version**: PHP 8.4+ with `declare(strict_types=1);` in **every** PHP file.
- **Class Modifiers**: Classes are `final` or `final readonly` where inheritance or mutation is unnecessary.
- **DTOs**: `final readonly class` with static `fromArray()` / `fromQueryParams()` methods enforcing strict payload validation.
- **Configuration**: Immutable [`Settings`](file:///Users/devkabir/Sites/slim/src/Config/Settings.php) object created during bootstrap via `Settings::fromEnv()` and injected into infrastructure and middleware (no static environment lookups).
- **Dependency Injection**: `php-di/php-di` v7 via [`src/Bootstrap/ContainerFactory.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/ContainerFactory.php) as the single composition root. Zero static singletons or service locators.
- **Application Factory**: App created via `AppFactory::createFromContainer($container)` with support for prebuilt containers.
- **HTTP / PSR Standards**: PSR-7 (`slim/psr7`), PSR-15 Middleware, PSR-11 Container, PSR-3 Logger (`monolog/monolog`).
- **Response Format**: Standardized envelope via [`ApiResponse`](file:///Users/devkabir/Sites/slim/src/Response/ApiResponse.php).

---

## 🚀 Adding a New Resource / CRUD Module (Step-by-Step)

When introducing a new entity (e.g., `Post`, `User`, `Category`):

### 1. Database Schema
Create the table definition with proper indexes, utf8mb4 collation, and foreign keys in `schema.sql` (or migration):
```sql
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Entity Model (`src/Models/<Entity>.php`)
Implement `JsonSerializable` and provide `fromArray()` / `jsonSerialize()`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

final readonly class Item implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $description,
        public string $status,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            title: (string)$data['title'],
            description: isset($data['description']) && $data['description'] !== '' ? (string)$data['description'] : null,
            status: (string)($data['status'] ?? 'draft'),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
```

### 3. Data Transfer Objects (`src/DTOs/`)
Create strict DTOs throwing [`ValidationException`](file:///Users/devkabir/Sites/slim/src/Exceptions/ValidationException.php):
- **`Create<Entity>DTO`**: Reject unknown fields with `array_diff()`, check required strings, trim inputs, enforce max lengths.
- **`Update<Entity>DTO`**: Check partial payload fields, validate types, provide `toArray()`.
- **`<Entity>ListQueryDTO`**: Parse query params (`page`, `limit` capped by `HARD_MAX_LIMIT = 100`, filter options).

### 4. Repository Layer (`src/Repositories/<Entity>Repository.php`)
- `final readonly class` injecting `PDO` and optional `Psr\Log\LoggerInterface`.
- Use **real server-side prepared statements** (`PDO::ATTR_EMULATE_PREPARES => false`).
- Bind explicit parameter types (`PDO::PARAM_INT`, `PDO::PARAM_STR`).
- Catch `Throwable`, log error context, and re-throw.

### 5. Service Layer (`src/Services/<Entity>Service.php`)
- `final readonly class` injecting `<Entity>Repository`, [`App\Config\Cache`](file:///Users/devkabir/Sites/slim/src/Config/Cache.php), and optional `Psr\Log\LoggerInterface`.
- Implement Cache-Aside caching via injected `$this->cache`:
- Use **O(1) versioned list invalidation**:
  ```php
  private const CACHE_NAMESPACE_LIST = 'item_list_ns';
  private const CACHE_PREFIX_ITEM    = 'item_';
  private const CACHE_PREFIX_LIST    = 'item_list_';

  // Read with versioning
  $nsVersion = $this->cache->getNamespaceVersion(self::CACHE_NAMESPACE_LIST);
  $cacheKey  = self::CACHE_PREFIX_LIST . "v{$nsVersion}_{$filter}_p{$page}_l{$limit}";

  // Invalidate on mutation
  $this->cache->incrementNamespaceVersion(self::CACHE_NAMESPACE_LIST);
  $this->cache->delete(self::CACHE_PREFIX_ITEM . $id);
  ```

### 6. Controller (`src/Controllers/<Entity>Controller.php`)
- `final readonly class` injecting `<Entity>Service` and [`ApiResponse`](file:///Users/devkabir/Sites/slim/src/Response/ApiResponse.php).
- Handle HTTP requests, parse body/params into DTOs, invoke service, return formatted JSON response.
- Validate path parameter IDs (`validateId()`) to ensure positive integers.

### 7. Dependency Injection & Routing
1. **Register in [`src/Bootstrap/ContainerFactory.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/ContainerFactory.php)**:
   ```php
   ItemRepository::class => autowire(ItemRepository::class),
   ItemService::class    => autowire(ItemService::class),
   ItemController::class => autowire(ItemController::class),
   ```
2. **Register in [`src/Bootstrap/Routes.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/Routes.php)**:
   ```php
   $app->group('/api/items', function (RouteCollectorProxy $group) {
       $group->get('', [ItemController::class, 'index']);
       $group->get('/{id:[0-9]+}', [ItemController::class, 'show']);
       $group->post('', [ItemController::class, 'create']);
       $group->put('/{id:[0-9]+}', [ItemController::class, 'update']);
       $group->patch('/{id:[0-9]+}', [ItemController::class, 'update']);
       $group->delete('/{id:[0-9]+}', [ItemController::class, 'delete']);
   });
   ```

---

## 🛡️ Response Envelope & Error Handling Standard

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully.",
  "pagination": {
    "total": 50,
    "page": 1,
    "per_page": 20,
    "total_pages": 3,
    "has_next_page": true,
    "has_prev_page": false
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "type": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "title": "The title field cannot be blank."
    }
  }
}
```

### Error Type Mapping
| Exception / Situation | HTTP Status | Error Type Code |
| :--- | :--- | :--- |
| `ValidationException` | `422 Unprocessable Entity` | `VALIDATION_ERROR` |
| `HttpNotFoundException` | `404 Not Found` | `NOT_FOUND` |
| `HttpMethodNotAllowedException` | `405 Method Not Allowed` | `NOT_ALLOWED` |
| `HttpUnauthorizedException` / Invalid Health Key | `401 Unauthorized` | `UNAUTHORIZED` |
| `HttpForbiddenException` | `403 Forbidden` | `FORBIDDEN` |
| `HttpBadRequestException` | `400 Bad Request` | `BAD_REQUEST` |
| Rate Limit Exceeded | `429 Too Many Requests` | `RATE_LIMIT_EXCEEDED` |
| Payload > Max Size | `413 Content Too Large` | `CONTENT_TOO_LARGE` |
| Unsupported Media Type | `415 Unsupported Media Type` | `UNSUPPORTED_MEDIA_TYPE` |
| Unhandled Exceptions | `500 Internal Server Error` | `SERVER_ERROR` |

---

## 🔒 Security & Middleware Pipeline

Middleware executes in FIFO order on requests and LIFO on responses:
1. **`HttpsEnforcementMiddleware`**: Enforces HTTPS (301 for GET/HEAD, 308 for POST/PUT/PATCH/DELETE to maintain payload integrity).
2. **`RequestIdMiddleware`**: Sanitizes or generates `X-Request-ID` (UUID/hex), sets it on response and pushes processor to Monolog logger.
3. **`SecurityHeadersMiddleware`**: Injects `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Referrer-Policy`, `X-Frame-Options: DENY`, and `Strict-Transport-Security`.
4. **`RateLimitMiddleware`**: Distinguishes Read vs Mutation limit tiers (e.g., 300 req/min for reads, 60 req/min for mutations), returns 429 with `Retry-After`.
5. **`ErrorMiddleware` (`HttpErrorHandler`)**: Catches all unhandled exceptions, logs sanitized context (redacting secrets/passwords), hides debug traces in production.
6. **`CorsMiddleware`**: Handles preflight `OPTIONS` and CORS headers (`Access-Control-Allow-*`).
7. **`JsonBodyParserMiddleware`**: Validates JSON content type and decodes request bodies safely.

---

## ⚡ Caching Strategy (Memcached Cache-Aside)

1. **Private Binding**: Memcached must bind to `127.0.0.1` or private VPC IP, never `0.0.0.0`.
2. **Resilience & Fallback**: If Memcached is offline, [`Cache`](file:///Users/devkabir/Sites/slim/src/Config/Cache.php) falls back transparently to direct MySQL queries without crashing the API.
3. **Never Scan All Keys**: Do **not** use `Memcached::getAllKeys()` or flush the entire cache in production. Use O(1) versioned namespace keys (`v{version}_...`) to invalidate paginated queries instantly.
4. **Log Sanitization**: Redact any potential passwords, DB credentials, or tokens before writing to log files.

---

## 🧪 Testing & Verification Runbook

### 1. Local Development
```bash
# Option A: Laravel Valet (Recommended)
./scripts/update-valet-config.sh
curl -k https://slim.test/health/live

# Option B: Built-in PHP Server
composer start
curl http://127.0.0.1:8000/health/live
```

### 2. Health & Readiness Verification
```bash
# Liveness probe
curl -i https://slim.test/health/live

# Protected readiness probe (checks MySQL and Memcached connectivity)
curl -i -H "X-Health-Key: <HEALTH_CHECK_SECRET>" https://slim.test/health/ready
```

### 3. API CRUD Verification
```bash
# 1. Create item
curl -i -X POST https://slim.test/api/todos \
  -H "Content-Type: application/json" \
  -d '{"title": "Test Todo", "description": "Verification item", "completed": false}'

# 2. List items
curl -i "https://slim.test/api/todos?page=1&limit=20"

# 3. Get single item
curl -i https://slim.test/api/todos/1

# 4. Update item
curl -i -X PATCH https://slim.test/api/todos/1 \
  -H "Content-Type: application/json" \
  -d '{"completed": true}'

# 5. Delete item
curl -i -X DELETE https://slim.test/api/todos/1
```

---

## 📚 Detailed References

For detailed code recipes, architecture deep-dives, and security checklists:
- [Architecture & Dependency Injection Deep-Dive](./references/architecture.md)
- [Complete CRUD Boilerplate Recipe](./references/crud-recipe.md)
- [Security & Caching Guide](./references/security-and-caching.md)
