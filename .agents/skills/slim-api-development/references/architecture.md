# Slim 4 Architecture & Dependency Injection Guide

This reference provides an in-depth breakdown of the architecture, application lifecycle, dependency injection bindings, and middleware stack.

---

## 1. Directory Structure

```
.
├── bootstrap/
│   └── app.php                   # Bootstraps App instance and returns Slim\App
├── public/
│   ├── index.php                 # Entry point (invokes App::bootstrap()->run())
│   └── .htaccess                 # Apache rewrite and security header fallback
├── src/
│   ├── App.php                   # Application factory & production config check
│   ├── Bootstrap/
│   │   ├── Bootstrap.php         # Dotenv, timezone, error reporting
│   │   ├── ContainerFactory.php  # PHP-DI container definition registry
│   │   ├── Middleware.php        # Middleware pipeline registration
│   │   └── Routes.php            # HTTP routes and route groups
│   ├── Config/
│   │   ├── AppConfig.php         # Environment variables & production validation
│   │   ├── AppLogger.php         # Monolog 3 rotation & request correlation
│   │   ├── Cache.php             # Memcached wrapper with fallback & log sanitization
│   │   └── Database.php          # PDO MySQL singleton with SSL/TLS support
│   ├── Controllers/              # HTTP Request handlers
│   ├── DTOs/                     # Readonly request validation objects
│   ├── Exceptions/               # Custom domain & validation exceptions
│   ├── Handlers/                 # Custom HttpErrorHandler for Slim 4
│   ├── Middleware/               # PSR-15 Middleware implementations
│   ├── Models/                   # Entity models implementing JsonSerializable
│   ├── Repositories/             # Direct PDO query execution & mapping
│   ├── Response/                 # Standardized JSON ApiResponse envelope
│   └── Services/                 # Business logic & Cache-Aside coordination
└── tests/                        # PHPUnit / Integration tests
```

---

## 2. Application Bootstrapping Lifecycle

1. **`public/index.php`**:
   Loads Composer autoloader `vendor/autoload.php` and calls `App\App::bootstrap()->run()`.
2. **`src/App.php::bootstrap()`**:
   - Calls `App\Bootstrap\Bootstrap::init()`: loads `.env` via `vlucas/phpdotenv`, configures `error_reporting`, sets default timezone to `UTC`.
   - Calls `src/App.php::create()`:
     1. Validates production configuration (`AppConfig::validateProductionConfig()`). Prohibits blank passwords, missing DB configs, `root` DB user, and missing health check keys in production.
     2. Constructs PHP-DI container via `ContainerFactory::create()`.
     3. Instantiates `Slim\App` using `AppFactory::createFromContainer($container)`.
     4. Registers middleware stack via `Middleware::register($app, $container)`.
     5. Registers route collectors via `Routes::register($app)`.
     6. Returns ready `Slim\App` instance.

---

## 3. Dependency Injection with PHP-DI

The container is configured in [`src/Bootstrap/ContainerFactory.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/ContainerFactory.php).

### Core Rules for Container Bindings:
- **Autowiring**: Use `\DI\autowire()` for Controllers, Services, and Repositories.
- **Factories**: Use `\DI\factory()` for singletons or instances requiring runtime setup (e.g. `PDO`, `LoggerInterface`).
- **Interfaces**: Bind PSR interfaces (`Psr\Log\LoggerInterface`, `Psr\Http\Message\ResponseFactoryInterface`) to their concrete implementations.

```php
use PDO;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use App\Config\AppLogger;
use App\Config\Database;
use App\Response\ApiResponse;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use function DI\autowire;
use function DI\factory;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    LoggerInterface::class          => factory(fn() => AppLogger::getLogger()),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),
    PDO::class                      => factory(fn() => Database::getConnection()),
    ApiResponse::class              => autowire(ApiResponse::class),
    // Repositories
    TodoRepository::class           => autowire(TodoRepository::class),
    // Services
    TodoService::class              => autowire(TodoService::class),
    // Controllers
    TodoController::class           => autowire(TodoController::class),
    HealthController::class         => autowire(HealthController::class),
]);
```

---

## 4. Middleware Execution Pipeline

Slim 4 executes middleware in **LIFO** (Last In, First Out) order relative to the registration order in `$app->add()`. The middleware registered last runs first for incoming requests!

In [`src/Bootstrap/Middleware.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/Middleware.php):

```php
// Order of registration:
$app->addRoutingMiddleware();                                  // 7. Slim Route matching
$app->add(new JsonBodyParserMiddleware($responseFactory));      // 6. Parses JSON body
$app->add(new CorsMiddleware());                               // 5. Handles CORS preflight & headers
$errorMiddleware = $app->addErrorMiddleware($debug, true, true, $logger); // 4. Catches unhandled errors
$errorMiddleware->setDefaultErrorHandler($errorHandler);
$app->add(new RateLimitMiddleware($responseFactory));          // 3. Rate limiting (429)
$app->add(new SecurityHeadersMiddleware());                    // 2. CSP, HSTS, X-Frame-Options
$app->add(new RequestIdMiddleware());                          // 1. X-Request-ID propagation & Monolog
$app->add(new HttpsEnforcementMiddleware($responseFactory));    // 0. Redirect HTTP -> HTTPS
```

### Request Flow:
1. `HttpsEnforcementMiddleware` (Redirects insecure HTTP to HTTPS).
2. `RequestIdMiddleware` (Attaches unique `X-Request-ID` to request attributes, response header, and logger processor).
3. `SecurityHeadersMiddleware` (Sets HSTS, nosniff, CSP, X-Frame-Options).
4. `RateLimitMiddleware` (Checks IP limits, returns 429 if exceeded).
5. `ErrorMiddleware` / `HttpErrorHandler` (Wraps inner execution in try-catch).
6. `CorsMiddleware` (Adds CORS headers).
7. `JsonBodyParserMiddleware` (Decodes JSON payload).
8. `RoutingMiddleware` -> Controller action.
