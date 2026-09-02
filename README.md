# Slim 4 Todo List CRUD API (with Doctrine DBAL, Symfony Cache & MySQL)

A modern, high-performance RESTful Todo List CRUD API built using **Slim 4**, **Doctrine DBAL 4.4**, **Symfony Cache** (Memcached Cache-Aside with Filesystem fallback), **Symfony RateLimiter**, and **PHP-DI 7**.

---

## 🚀 Features

- **Slim 4 Framework**: Fast and lightweight PSR-7 / PSR-15 micro-framework with native routing, route caching, and body parsing.
- **PHP-DI 7 Composition Root**: Centrally registered PSR interfaces, infrastructure, repositories, services, actions, and middleware.
- **Doctrine DBAL 4.4**: Explicit parameter-bound database persistence with MySQL prepared statements and connection pooling.
- **Symfony Cache (PSR-6)**: Memcached Cache-Aside pattern with transparent Filesystem/Array fallback, O(1) versioned namespace invalidation, and targeted key deletions.
- **Symfony RateLimiter**: Fixed-window rate limiting (300 read / 60 mutation requests per minute) backed by dedicated cache storage and trusted proxy verification.
- **Security & Headers**: Automatic injection of `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, `Strict-Transport-Security`, and CORS origin allowlists.
- **Method Override**: Header-only `X-HTTP-Method-Override` support on POST requests (allowing PUT, PATCH, DELETE).
- **Domain & Error Handling**: Centralized `HttpErrorHandler` mapping domain and HTTP exceptions to structured JSON response envelopes.
- **Location Headers**: `Location` response header automatically generated on todo creation via Slim's `RouteParserInterface`.

---

## 📁 Project Structure

```
.
├── composer.json               # Dependencies and PSR-4 autoloading
├── .env                        # Environment configuration (DB, Memcached, Security, Proxies)
├── .env.example                # Example environment configuration
├── schema.sql                  # MySQL database and table schema
├── nginx.conf.example          # Production Nginx SSL/TLS, HSTS, and redirect configuration
├── Caddyfile.example           # Production Caddy HTTPS configuration
├── scripts/
│   └── update-valet-config.sh  # Laravel Valet Nginx automated configuration script
├── public/
│   ├── index.php               # Application entry point (requires bootstrap/app.php)
│   └── .htaccess               # Apache URL rewrite rules & Security headers
├── bootstrap/
│   └── app.php                 # Creates and bootstraps application instance
└── src/
    ├── Actions/                # Invokable Action controllers
    │   ├── Home/
    │   │   └── ApiInfoAction.php
    │   ├── Health/
    │   │   ├── LivenessAction.php
    │   │   └── ReadinessAction.php
    │   └── Todo/
    │       ├── ListTodosAction.php
    │       ├── GetTodoAction.php
    │       ├── CreateTodoAction.php
    │       ├── UpdateTodoAction.php
    │       └── DeleteTodoAction.php
    ├── Bootstrap/
    │   ├── Bootstrap.php       # Dotenv, PHP runtime ini settings, exception handler
    │   ├── ContainerFactory.php# PHP-DI single composition root
    │   ├── Middleware.php      # PSR-15 Middleware registration pipeline
    │   └── Routes.php          # Named HTTP route mappings
    ├── Config/
    │   ├── Settings.php        # Immutable application configuration
    │   ├── Database.php        # Doctrine DBAL Connection factory
    │   ├── CachePoolFactory.php# PSR-6 CacheItemPoolInterface factory
    │   ├── Cache.php           # Cache helper proxy
    │   └── AppLogger.php       # PSR-3 Monolog Logger factory
    ├── DTOs/                   # Readonly request validation DTOs
    ├── Exceptions/             # ValidationException, TodoNotFoundException
    ├── Handlers/               # HttpErrorHandler (Slim 4 error handler)
    ├── Health/                 # Modular readiness checks (MySQL, Memcached)
    ├── Middleware/             # RequestId, ResponseDecoration, Https, MethodOverride, RateLimit, BodyGuard
    ├── Models/                 # Todo entity model (JsonSerializable)
    ├── Repositories/           # TodoRepositoryInterface & DoctrineTodoRepository
    ├── Response/               # Standardized ApiResponse envelope
    └── Services/               # TodoService & CacheService
```

---

## 🛠️ Setup & Installation

### 1. Database Setup

Create database and table:

```bash
mysql -u root < schema.sql
```

### 2. Dependencies

Install composer packages:

```bash
composer install
```

### 3. Environment Configuration

Copy `.env.example` to `.env` and configure credentials:

```env
APP_ENV=development
APP_DEBUG=true
HEALTH_CHECK_SECRET=your-secret-health-key-here

# Routing & Base Path
APP_BASE_PATH=
ROUTE_CACHE_ENABLED=false

# Proxy & CORS Configuration
TRUSTED_PROXIES=127.0.0.1
CORS_ALLOWED_ORIGINS=*

# Rate Limiting (Requests per minute)
RATE_LIMIT_READ=300
RATE_LIMIT_MUTATION=60

# Request Body Limit (Bytes)
MAX_BODY_SIZE_BYTES=1048576

# Security & HTTPS Configuration
FORCE_HTTPS=false
HSTS_ENABLED=true
HSTS_MAX_AGE=31536000
HSTS_INCLUDE_SUBDOMAINS=true
HSTS_PRELOAD=false
REFERRER_POLICY=strict-origin-when-cross-origin
CONTENT_SECURITY_POLICY="default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"

# Database Configuration (Least-privileged dedicated user)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=slim_todo_db
DB_USER=todo_app
DB_PASS=your-database-password

# Memcached Configuration (Bind to 127.0.0.1 / private VPC interface only)
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
MEMCACHED_TTL=3600
```

### 4. Run Development Server

#### Option A: Laravel Valet (Recommended)

```bash
chmod +x scripts/update-valet-config.sh
./scripts/update-valet-config.sh
```

Access your API at: `https://slim.test`

#### Option B: Built-in PHP Server

```bash
php -S 127.0.0.1:8000 -t public
```

---

## 📡 API Endpoints

| Method   | Endpoint          | Route Name      | Description                                              | Cache / Behavior                                       |
| :------- | :---------------- | :-------------- | :------------------------------------------------------- | :----------------------------------------------------- |
| `GET`    | `/`               | `home`          | API status & dynamically generated endpoint routes       | Public info                                            |
| `GET`    | `/health`         | `health`        | Public liveness probe                                    | Process check                                          |
| `GET`    | `/health/live`    | `health.live`   | Public liveness probe                                    | Process check                                          |
| `GET`    | `/health/ready`   | `health.ready`  | Protected readiness probe (`X-Health-Key` / Bearer token)| Checks MySQL DBAL & Memcached                          |
| `GET`    | `/api/todos`      | `todos.index`   | List all todos (`?completed=1`, `?completed=0`)         | Versioned list cache (`todo_list_v{N}_*`)              |
| `GET`    | `/api/todos/{id}` | `todos.show`    | Get single todo by ID                                    | Item cache (`todo_item_{id}`)                          |
| `POST`   | `/api/todos`      | `todos.create`  | Create a new todo (returns `Location` header)            | Writes to DB, warms item cache, invalidates list cache |
| `PUT`    | `/api/todos/{id}` | `todos.update`  | Update an existing todo                                  | Updates DB, invalidates item & list caches             |
| `PATCH`  | `/api/todos/{id}` | `todos.patch`   | Partially update an existing todo                        | Updates DB, invalidates item & list caches             |
| `DELETE` | `/api/todos/{id}` | `todos.delete`  | Delete a todo                                            | Deletes from DB, invalidates item & list caches        |
