# Slim 4 Todo List CRUD API (with Memcached & MySQL)

A RESTful Todo List CRUD API built using **Slim 4**, **MySQL** (via PDO), and **Memcached** (Cache-Aside pattern).

---

## 🚀 Features

- **Slim 4 Framework**: Fast and lightweight PSR-7 / PSR-15 micro-framework.
- **HTTPS & Transport Security**: Reverse proxy redirection, HSTS enforcement, and strict `public/` web server exposure.
- **Security Headers**: Automatic injection of `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, and `Permissions-Policy`.
- **Hardened Memcached**: Private interface binding, optional SASL authentication, O(1) versioned cache namespaces & targeted key deletions avoiding `getAllKeys()`, and sanitized failure logging.
- **MySQL Persistence**: PDO prepared statements for secure, SQL-injection safe CRUD operations.
- **CORS & JSON Middleware**: Cross-origin requests support & automatic JSON request body decoding.
- **Input Validation**: Clean validation for required fields and request payloads.

---

## 📁 Project Structure

```
.
├── composer.json               # Dependencies and PSR-4 autoloading
├── .env                        # Environment configuration (DB, Memcached, Security)
├── .env.example                # Example environment configuration
├── schema.sql                  # MySQL database and table schema
├── nginx.conf.example          # Production Nginx SSL/TLS, HSTS, and redirect configuration
├── Caddyfile.example           # Production Caddy HTTPS configuration
├── public/
│   ├── index.php               # Application entry point and route definitions
│   └── .htaccess               # Apache URL rewrite rules & Security headers
└── src/
    ├── Config/
    │   ├── AppConfig.php       # Environment configuration helper
    │   ├── Database.php        # PDO MySQL connection
    │   └── Cache.php           # Memcached connection & hardened helper methods
    ├── Controllers/
    │   ├── HealthController.php# Liveness and protected readiness checks
    │   └── TodoController.php  # Handles RESTful requests & responses
    ├── Models/
    │   └── Todo.php            # Todo entity model
    ├── Repositories/
    │   └── TodoRepository.php  # Direct MySQL query execution
    ├── Services/
    │   └── TodoService.php     # Business logic & Cache-Aside coordination
    └── Middleware/
        ├── HttpsEnforcementMiddleware.php # HTTP -> HTTPS 301/308 redirect
        ├── SecurityHeadersMiddleware.php  # nosniff, CSP, Referrer-Policy, HSTS
        ├── JsonBodyParserMiddleware.php
        └── CorsMiddleware.php
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

# Security & HTTPS Configuration
FORCE_HTTPS=false
HSTS_ENABLED=true
HSTS_MAX_AGE=31536000
HSTS_INCLUDE_SUBDOMAINS=true
HSTS_PRELOAD=false
REFERRER_POLICY=strict-origin-when-cross-origin
CONTENT_SECURITY_POLICY="default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"

# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=slim_todo_db
DB_USER=root
DB_PASS=

# Memcached Configuration (bind strictly to private interface)
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
MEMCACHED_TTL=3600
# Optional SASL Authentication
MEMCACHED_USERNAME=
MEMCACHED_PASSWORD=
```

### 4. Run Development Server
```bash
php -S 127.0.0.1:8000 -t public
```

---

## 🔒 Security Hardening

### 1. Web Server & HTTPS Deployment
- **Expose ONLY `public/`**: Set your web server document root strictly to the `public/` directory (see [`nginx.conf.example`](file:///Users/devkabir/Sites/slim/nginx.conf.example)). Never expose the repository root directory.
- **HTTP -> HTTPS Redirection**: Handled at the reverse proxy / web server layer (301) and backed up by [`HttpsEnforcementMiddleware`](file:///Users/devkabir/Sites/slim/src/Middleware/HttpsEnforcementMiddleware.php) (301 for safe methods, 308 for mutations to preserve request payload).
- **HSTS**: Enabled via `Strict-Transport-Security: max-age=31536000; includeSubDomains` at the HTTPS termination layer and in [`SecurityHeadersMiddleware`](file:///Users/devkabir/Sites/slim/src/Middleware/SecurityHeadersMiddleware.php).
- **Security Headers**:
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'`
  - `X-Frame-Options: DENY`

### 2. Memcached Hardening
- **Private Binding & Firewall**: Memcached is configured to bind strictly to `127.0.0.1` or a private VPC interface (never `0.0.0.0`). In production, restrict port 11211 with firewall rules and disable UDP (`-U 0`).
- **SASL Authentication**: Supported via `MEMCACHED_USERNAME` and `MEMCACHED_PASSWORD` in binary protocol mode.
- **No `getAllKeys()` Scans**: Cache invalidation uses O(1) versioned cache namespaces (`Cache::incrementNamespaceVersion('todo_list_ns')`) and targeted direct multi-key deletions (`todo_list_all`, `todo_list_completed`, `todo_list_pending`).
- **Sanitized Logging**: All cache warnings and connection failures redact credentials and tokens.

---

## 📡 API Endpoints

| Method   | Endpoint          | Description                                              | Cache Behavior                                         |
|:---------|:------------------|:---------------------------------------------------------|:-------------------------------------------------------|
| `GET`    | `/`               | Health check & API status                                | Public info                                            |
| `GET`    | `/health/live`    | Public liveness probe                                    | Process check                                          |
| `GET`    | `/health/ready`   | Protected readiness probe                                | Checks MySQL & Memcached                               |
| `GET`    | `/api/todos`      | List all todos (filter `?completed=1` or `?completed=0`) | Versioned list cache (`todo_list_v{N}_*`)              |
| `GET`    | `/api/todos/{id}` | Get single todo by ID                                    | Item cache (`todo_item_{id}`)                          |
| `POST`   | `/api/todos`      | Create a new todo                                        | Writes to DB, warms item cache, invalidates list cache |
| `PUT`    | `/api/todos/{id}` | Update an existing todo                                  | Updates DB, invalidates item & list caches             |
| `PATCH`  | `/api/todos/{id}` | Partially update an existing todo                        | Updates DB, invalidates item & list caches             |
| `DELETE` | `/api/todos/{id}` | Delete a todo                                            | Deletes from DB, invalidates item & list caches        |
