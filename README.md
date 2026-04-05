# Juzdy HTTP Router

A robust, PSR-compliant HTTP router middleware for the JUZDY framework. Provides flexible route registration, middleware support, and group-based route organization.

## Features

- **PSR-15 Middleware Compatible**: Implements `MiddlewareInterface` for seamless integration with PSR middleware stacks
- **Flexible Route Handlers**: Supports callables, service identifiers, and `RequestHandlerInterface` implementations
- **Route Groups**: Organize routes with common prefixes and middleware
- **Route Parameters**: Extract parameters from path patterns using `{paramName}` syntax
- **Middleware Pipeline**: Per-route and per-group middleware with configurable execution order
- **Dependency Injection**: Automatic dependency resolution for handlers and middleware
- **Method Chaining**: Fluent API for route and middleware configuration
- **Config-Based Registration**: Register routes from `http-router` config using nested path nodes and verb keys
- **Event Dispatching**: Router initialization events for custom hooks

## Installation

For local development, this package is in `local_packages/juzdy/http-router/`:

```bash
composer require juzdy/http-router:dev-main
```

## Quick Start

### Basic Route Registration

Register a simple GET route with a callable handler:

```php
$router->get('/hello', function () {
    return 'Hello, World!';
});
```

The router also exposes the common HTTP verb helpers:

```php
$router->post('/posts', CreatePostAction::class);
$router->put('/posts/{id}', ReplacePostAction::class);
$router->patch('/posts/{id}', UpdatePostAction::class);
$router->delete('/posts/{id}', DeletePostAction::class);
$router->head('/health', HealthCheckAction::class);
$router->options('/posts', PostsOptionsAction::class);
```

### Route Parameters

Capture path parameters using `{paramName}` syntax:

```php
$router->get('/users/{id}', function (ServerRequestInterface $request) {
    $routeParams = $request->getQueryParams();

    return 'User ID: ' . $routeParams['id'];
});
```

Route parameters are merged into the current `ServerRequestInterface` query params. The router does not write anything to PHP globals such as `$_GET`.

### PSR-15 Handler

Implement `RequestHandlerInterface` for more complex handlers:

```php
$router->get('/api/posts', PostHandler::class);
```

Where `PostHandler` implements `RequestHandlerInterface`:

```php
class PostHandler implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface {
        // Handle the request
    }
}
```

### Route Middleware

Add middleware to individual routes:

```php
$router->get('/protected', MyHandler::class)
    ->withMiddleware(AuthenticationMiddleware::class, LoggingMiddleware::class);
```

Add inline middleware instances:

```php
$router->get('/demo', function () {
    return 'Demo route';
})
    ->withMiddleware(
        new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                // Pre-processing
                $response = $handler->handle($request);
                // Post-processing
                return $response->withHeader('X-Custom-Header', 'value');
            }
        }
    );
```

## Route Groups

Organize related routes with common prefixes and shared middleware:

```php
$router->group('/api', function (RouterInterface $router) {
    $router->get('/posts', PostListHandler::class);
    $router->get('/posts/{id}', PostDetailHandler::class);
    $router->get('/users', UserListHandler::class);
})
->withMiddleware(ApiAuthMiddleware::class);
```

This registers:
- `GET /api/posts`
- `GET /api/posts/{id}`
- `GET /api/users`

All routes apply `ApiAuthMiddleware` automatically.

### Nested Groups

Groups can be nested for deeper organization:

```php
$router->group('/admin', function (RouterInterface $router) {
    $router->get('/dashboard', AdminDashboardHandler::class);
    
    $router->group('/users', function (RouterInterface $router) {
        $router->get('/', UserListHandler::class);
        $router->get('/{id}', UserDetailHandler::class);
    })
    ->withMiddleware(AdminUserAccess::class);
})
->withMiddleware(AdminAuthMiddleware::class);
```

## Configuration-Based Route Registration

Routes are loaded from the `http-router` config key and processed by `RouteConfigProcessor` during `Package::boot()`.

Think of the structure as a tree of path nodes.

Top-level rule:

- keys that start with `/` are treated as route path nodes

Each path node supports these keys:

- `middleware`: middleware list for that node scope
- HTTP verbs: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, `OPTIONS` (case-insensitive)
- child path nodes: nested keys that also start with `/`

Handler value forms:

- shorthand: `'/health' => HealthHandler::class` (registers `GET /health`)
- verb string: `'GET' => PostListHandler::class`
- verb object: `'POST' => ['handler' => CreatePostHandler::class, 'middleware' => [AuthMiddleware::class]]`

Full example:

```php
'http-router' => [
    '/health' => HealthHandler::class,

    '/posts' => [
        'GET' => PostListHandler::class,
        'POST' => [
            'handler' => CreatePostHandler::class,
            'middleware' => [AuthMiddleware::class],
        ],
    ],

    '/posts/{id}' => [
        'middleware' => [LogMiddleware::class],
        'GET' => PostDetailHandler::class,
        'PATCH' => [
            'handler' => UpdatePostHandler::class,
            'middleware' => [AuthMiddleware::class],
        ],
        'DELETE' => [
            'handler' => DeletePostHandler::class,
            'middleware' => [AuthMiddleware::class],
        ],
    ],

    '/api' => [
        'middleware' => [LogMiddleware::class],
        '/me' => [
            'GET' => ProfileHandler::class,
        ],
    ],
]
```

How nested paths resolve:

- parent `'/api'` + child `'/me'` becomes route path `'/api/me'`

Middleware inheritance order:

- group middleware
- node middleware (`middleware` at the current path)
- verb middleware (`middleware` inside a verb definition)

Validation notes:

- non-path keys at root level are ignored
- verb keys are matched case-insensitively (recommended style: uppercase)
- verb definitions must be either a string handler or an array containing `handler`
- invalid node shapes throw runtime exceptions during config processing

## Handler Types

The router accepts three practical handler styles. They differ mainly in how the handler object is created and where dependencies are injected.

### 1. Inline Callable

Use a closure when the route logic is short. The callable is executed through `DiInvoker`, so request-scoped and framework services can be injected directly into the function signature:

```php
$router->get('/inline', function (
    ServerRequestInterface $request,
    ResponseFactoryInterface $responseFactory
) {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write('Inline handler');

    return $response;
});
```

This is the lightest option when you do not need a dedicated handler class.

### 2. Request Handler Interface

Use a PSR-15 handler class when you want an explicit `handle()` method and a handler object with a stable contract:

```php
$router->get('/handler', MyRequestHandler::class);
```

This is a good fit for reusable application endpoints and integration with other PSR-15 tooling.

### 3. Callable Class

Use an invokable class when you want a dedicated handler type but prefer `__invoke()` over `handle()`. The class is first resolved by the container, then its `__invoke()` method is executed through `DiInvoker`.

That gives you two levels of injection:

- constructor injection for long-lived service dependencies
- method injection for request-specific collaborators

```php
$router->get('/reports/summary', ReportSummaryAction::class);
```

Example:

```php
final class ReportSummaryAction
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory
    ): ResponseInterface {
        $response = $responseFactory->createResponse(200);
        $format = $request->getQueryParams()['format'] ?? 'json';
        $environment = (string) $this->config->get('app.env', 'production');

        $response->getBody()->write(
            sprintf('Summary report in %s for %s.', $format, $environment)
        );

        return $response;
    }
}
```

Choose this style when the class needs constructor-injected services but you still want the route entry point to stay compact.

## Route Matching

Routes are matched against the HTTP method and request path:

- Null prefix matches exact path
- Parameters are extracted from path and merged into request query parameters
- Path conflicts are resolved by registration order (first match wins)

### Example Matching

```php
// Registration
$router->get('/users/{id}/posts/{postId}', MyHandler::class);

// Request
GET /users/123/posts/456

// Extracted Parameters
[
    'id' => '123',
    'postId' => '456'
]
```

In handlers and middleware, access those values from the `ServerRequestInterface` instance:

```php
$params = $request->getQueryParams();
$id = $params['id'];
$postId = $params['postId'];
```

## Architecture

### Core Components

#### Router
Implements `RouterInterface` and manages route storage, dispatch logic, and middleware execution.

**Key Methods:**
- `get(string $path, handler): RouteInterface` - Register GET route
- `post(string $path, handler): RouteInterface` - Register POST route
- `put(string $path, handler): RouteInterface` - Register PUT route
- `patch(string $path, handler): RouteInterface` - Register PATCH route
- `delete(string $path, handler): RouteInterface` - Register DELETE route
- `head(string $path, handler): RouteInterface` - Register HEAD route
- `options(string $path, handler): RouteInterface` - Register OPTIONS route
- `group(string $prefix, ?callable $callback, ...$middleware): static` - Create route group
- `dispatch(ServerRequestInterface $request): ?ResponseInterface` - Dispatch request to route
- `withMiddleware(...$middleware): static` - Add router-level middleware

All verb helpers delegate to the same route factory and differ only by the HTTP method assigned to the route.

#### Route
Represents a single HTTP route with method, path, handler, and middleware.

**Key Methods:**
- `matches(string $method, string $path): bool` - Check if request matches route
- `handle(ServerRequestInterface $request): ResponseInterface` - Execute route
- `method(string $method): static` - Set HTTP method
- `path(string $path): static` - Set path pattern
- `handler(handler): static` - Set route handler
- `withMiddleware(...$middleware): static` - Add route middleware

#### MiddlewarePipelineHandler
Manages middleware execution using the Chain of Responsibility pattern. Executes middleware stack before routing to the final handler.

**Key Methods:**
- `append(...$middleware): static` - Add middleware to pipeline
- `handle(ServerRequestInterface $request): ResponseInterface` - Execute middleware chain

#### RouteFactory
Factory for creating `Route` instances with dependency injection.

#### MiddlewareFactory
Factory for creating middleware instances from service names.

### SOLID Design

The router follows SOLID principles:

- **Single Responsibility**: Each class has one reason to change
- **Open/Closed**: Extensible through interfaces without modification
- **Liskov Substitution**: Proper interface implementation
- **Interface Segregation**: Focused, minimal interfaces
- **Dependency Inversion**: Depends on abstractions, not concrete classes

## Middleware Pipeline

Routes process requests through a middleware pipeline:

1. Router-level middleware
2. Group middleware (for grouped routes)
3. Route-level middleware
4. Final handler

Each middleware can:
- Inspect and modify the request
- Short-circuit and return early
- Modify the response before returning
- Delegate to the next middleware via `$handler->handle($request)`

The route endpoint is resolved before the middleware chain is assembled. That means the same pipeline behavior applies whether the route uses a PSR-15 request handler, a callable, or a string handler resolved from the container, and any middleware added during handler resolution is included in the same request flow.

## Events

The router fires events during its lifecycle:

### RouterInitialized
Fired after the router is fully initialized and ready to dispatch requests.

```php
$eventDispatcher->listen(RouterInitialized::class, function (RouterInitialized $event) {
    // Router is ready
});
```

## Dependencies

The package requires:

- `php: >=8.2`
- `psr/http-message` - HTTP message interfaces
- `psr/http-server-handler` - Request handler interface
- `psr/http-server-middleware` - Middleware interface
- `psr/container` - Service container
- `juzdy/app` - JUZDY application framework
- `juzdy/app-http` - JUZDY HTTP application
- `juzdy/config` - Configuration management
- `juzdy/container` - Dependency injection container

## Configuration

Configuration is typically defined in the parent application. The router is automatically registered with the middleware stack at priority `PHP_INT_MAX - 200` to execute late in the pipeline.

When changing package behavior, keep `README.md` aligned with:

- router public API (`RouterInterface`, `Router`)
- config schema (`RouteConfigProcessor`, `etc/config/config.php`)
- handler/middleware execution semantics

## Example: Complete Application

```php
use Juzdy\Http\Router\RouterInterface;

// In Package boot method
public function boot(AppInterface $app): void {
    $router = $app(RouterInterface::class);
    
    // Register routes
    $router->get('/', HomeHandler::class);
    $router->post('/posts', CreatePostHandler::class);
    
    $router->group('/api', function (RouterInterface $router) {
        $router->get('/posts', PostListHandler::class);
        $router->get('/posts/{id}', PostDetailHandler::class);
        $router->patch('/posts/{id}', UpdatePostHandler::class);
        $router->delete('/posts/{id}', DeletePostHandler::class);
    })
    ->withMiddleware(ApiAuthMiddleware::class);
    
    $router->group('/admin', function (RouterInterface $router) {
        $router->get('/dashboard', AdminDashboardHandler::class);
        $router->options('/users', AdminUsersOptionsHandler::class);
    })
    ->withMiddleware(AdminAuthMiddleware::class);
}
```

## Best Practices

1. **Use Type Hints**: Always type hint handlers for better IDE support
2. **Group Related Routes**: Use groups to organize and share middleware
3. **Extract Handlers**: Move complex logic from closures to handler classes
4. **Service Registration**: Register handlers as services for better testability
5. **Middleware Order**: Place authentication middleware early in the stack
6. **Error Handling**: Use middleware for consistent error handling
7. **Logging**: Use middleware to log requests and responses

## Testing

This package includes a comprehensive Pest test suite covering:

- **Router behavior**: route registration, dispatch, middleware execution
- **Route matching**: exact paths, parameterized paths, method matching
- **Handler resolution**: callables, string identifiers, RequestHandlerInterface
- **Middleware pipeline**: execution order, attachment, resolution
- **Error handling**: invalid handlers, middleware validation, unmatched routes
- **Integration scenarios**: multiple routes, nested groups, parameter extraction

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/RouterTest.php

# Run with coverage report
composer test:coverage
```

Coverage best-practice scope for this package excludes demo/example code and
interface/contract-only files, so the reported percentage reflects runtime
router behavior rather than scaffolding artifacts.

### Test Organization

Tests are in `tests/` directory:
- `RouterTest.php` - Router registration, dispatch, middleware
- `RouteTest.php` - Route matching, configuration, handling
- `FactoriesTest.php` - Handler/middleware/route factories
- `PackageTest.php` - Package configuration and boot

### Writing Tests

When contributing or modifying router code:

1. Include positive tests (valid behavior)
2. Include negative tests (error cases, invalid input)
3. Follow the test structure in existing test files
4. Use mocks/stubs for dependencies
5. Test both happy path and edge cases

Refer to `.github/instructions/juzdy-http-router-tests.instructions.md` for detailed test requirements.

## Troubleshooting

### Route Not Matching
- Verify the HTTP method matches (case-insensitive, uppercase internally)
- Check path is exactly as registered or matches the pattern
- Ensure route is registered before dispatch

### Middleware Not Executing
- Verify middleware is registered with correct method
- Check middleware order in the pipeline
- Ensure middleware implements `MiddlewareInterface`

### Handler Not Found
- Check service identifier exists in container
- Verify class name spelling and namespace
- Ensure class implements `RequestHandlerInterface` or is callable

## License

MIT License - see [LICENSE](LICENSE) file for details

## Author

Victor Galitsky - concept.galitsky@gmail.com
