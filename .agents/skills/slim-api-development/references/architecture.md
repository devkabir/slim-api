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
│   ├── App.php                   # Application factory supporting prebuilt containers
│   ├── Bootstrap/
│   │   ├── Bootstrap.php         # Dotenv, settings initialization, error reporting
│   │   ├── ContainerFactory.php  # PHP-DI single composition root
│   │   ├── Middleware.php        # Middleware pipeline registration
│   │   └── Routes.php            # HTTP routes and route groups
│   ├── Config/
│   │   ├── Settings.php          # Immutable application configuration
│   │   ├── AppLogger.php         # PSR-3 Monolog logger factory
│   │   ├── Cache.php             # Memcached service instance with fallback
│   │   └── Database.php          # PDO MySQL connection factory
│   ├── Controllers/              # HTTP Request handlers (final readonly)
│   ├── DTOs/                     # Request validation DTOs (final readonly)
│   ├── Exceptions/               # Custom domain & validation exceptions
│   ├── Handlers/                 # Custom HttpErrorHandler for Slim 4
│   ├── Middleware/               # PSR-15 Middleware implementations
│   ├── Models/                   # Entity models implementing JsonSerializable
│   ├── Repositories/             # PDO repository implementations (final readonly)
│   ├── Response/                 # Standardized JSON ApiResponse envelope
│   └── Services/                 # Business logic & Cache-Aside coordination (final readonly)
└── tests/                        # Integration and unit tests
```

---

## 2. Application Bootstrapping Lifecycle

1. **`public/index.php`**:
   Loads Composer autoloader `vendor/autoload.php` and calls `App\App::bootstrap()->run()`.
2. **`src/App.php::bootstrap()`**:
   - Calls `App\Bootstrap\Bootstrap::init()`: loads `.env` via `vlucas/phpdotenv`, instantiates immutable `Settings::fromEnv()`, validates production requirements, and configures runtime error reporting.
   - Calls `src/App.php::create()`:
     1. Constructs PHP-DI container via `ContainerFactory::create($settings)`.
     2. Instantiates `Slim\App` using `AppFactory::createFromContainer($container)`.
     3. Registers middleware stack via `Middleware::register($app, $container)` (resolving middleware instances directly from the container).
     4. Registers route collectors via `Routes::register($app)`.
     5. Returns ready `Slim\App` instance.

3. **Prebuilt Container Support (`App::create(?ContainerInterface $container, ?Settings $settings)`)**:
   Allows external callers (such as test runners or custom execution environments) to provide a pre-configured DI container while reusing standard middleware and route registrations.

---

## 3. Dependency Injection with PHP-DI

The container is configured in [`src/Bootstrap/ContainerFactory.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/ContainerFactory.php) as the application's single composition root.

### Core Rules for Container Bindings:
- **Zero Static State**: No static singletons (`AppLogger`, `Database`, `Cache` are instance-based or factory-driven).
- **Autowiring**: Use `\DI\autowire()` for Controllers, Services, Repositories, and Middleware.
- **Factories**: Use `\DI\factory()` for runtime construction (e.g. `PDO`, `LoggerInterface`).
- **Interfaces**: Bind PSR interfaces (`Psr\Log\LoggerInterface`, `Psr\Http\Message\ResponseFactoryInterface`) to their concrete implementations.

```php
use PDO;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use App\Config\Settings;
use App\Config\AppLogger;
use App\Config\Database;
use App\Config\Cache;
use App\Response\ApiResponse;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use function DI\autowire;
use function DI\factory;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // Immutable Settings
    Settings::class                 => $settings,

    // PSR Interfaces
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),
    LoggerInterface::class          => factory(fn(Settings $s) => AppLogger::createLogger($s)),
    PDO::class                      => factory(fn(Settings $s, LoggerInterface $log) => Database::createConnection($s, $log)),

    // Services & Handlers
    Cache::class                    => autowire(Cache::class),
    ApiResponse::class              => autowire(ApiResponse::class),
    TodoRepository::class           => autowire(TodoRepository::class),
    TodoService::class              => autowire(TodoService::class),
    TodoController::class           => autowire(TodoController::class),
    HealthController::class         => autowire(HealthController::class),

    // Middleware
    CorsMiddleware::class             => autowire(CorsMiddleware::class),
    HttpsEnforcementMiddleware::class => autowire(HttpsEnforcementMiddleware::class),
    JsonBodyParserMiddleware::class   => autowire(JsonBodyParserMiddleware::class),
    RateLimitMiddleware::class        => autowire(RateLimitMiddleware::class),
    RequestIdMiddleware::class        => autowire(RequestIdMiddleware::class),
    SecurityHeadersMiddleware::class  => autowire(SecurityHeadersMiddleware::class),
]);
```

---

## 4. Middleware Execution Pipeline

Slim 4 executes middleware in **LIFO** (Last In, First Out) order relative to the registration order in `$app->add()`. The middleware registered last runs first for incoming requests!

In [`src/Bootstrap/Middleware.php`](file:///Users/devkabir/Sites/slim/src/Bootstrap/Middleware.php):

```php
// Order of registration:
$app->addRoutingMiddleware();                                       // 7. Slim Route matching
$app->add($container->get(JsonBodyParserMiddleware::class));        // 6. Parses JSON body
$app->add($container->get(CorsMiddleware::class));                 // 5. Handles CORS preflight & headers
$errorMiddleware = $app->addErrorMiddleware($debug, true, true, $logger); // 4. Catches unhandled errors
$errorMiddleware->setDefaultErrorHandler($errorHandler);
$app->add($container->get(RateLimitMiddleware::class));            // 3. Rate limiting (429)
$app->add($container->get(SecurityHeadersMiddleware::class));      // 2. CSP, HSTS, X-Frame-Options
$app->add($container->get(RequestIdMiddleware::class));            // 1. X-Request-ID propagation & Monolog
$app->add($container->get(HttpsEnforcementMiddleware::class));      // 0. Redirect HTTP -> HTTPS
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
