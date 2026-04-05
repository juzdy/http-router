<?php

namespace Juzdy\Http\Router\Tests;

use Juzdy\Config\ConfigInterface;
use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Route\RouteFactory;
use Juzdy\Http\Router\Route\RouteInterface;
use Juzdy\Http\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

describe('Router', function () {
    beforeEach(function () {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->routeFactory = $this->createMock(RouteFactory::class);
        $this->router = new RouterExposed($this->config, $this->routeFactory);
    });

    test('registers GET route via factory', function () {
        $route = new RouterFakeRoute();

        $this->routeFactory->expects($this->once())
            ->method('createRoute')
            ->with('GET', '/users', 'UserHandler')
            ->willReturn($route);

        $result = $this->router->get('/users', 'UserHandler');

        expect($result)->toBe($route);
    });

    test('registers all HTTP verbs', function () {
        $routeA = new RouterFakeRoute();
        $routeB = new RouterFakeRoute();
        $routeC = new RouterFakeRoute();
        $routeD = new RouterFakeRoute();
        $routeE = new RouterFakeRoute();
        $routeF = new RouterFakeRoute();

        $this->routeFactory->expects($this->exactly(6))
            ->method('createRoute')
            ->willReturnOnConsecutiveCalls($routeA, $routeB, $routeC, $routeD, $routeE, $routeF);

        $this->router->post('/posts', 'PostHandler');
        $this->router->put('/posts/1', 'PutHandler');
        $this->router->patch('/posts/1', 'PatchHandler');
        $this->router->delete('/posts/1', 'DeleteHandler');
        $this->router->head('/health', 'HeadHandler');
        $this->router->options('/posts', 'OptionsHandler');

        expect(true)->toBeTrue();
    });

    test('throws on duplicate route registration', function () {
        $route = new RouterFakeRoute();

        $this->routeFactory->method('createRoute')->willReturn($route);

        $this->router->get('/users', 'UserHandler');
        $this->router->get('/users', 'UserHandler');
    })->throws(RuntimeException::class);

    test('dispatch returns null when no route matches', function () {
        $request = createRouterRequest('GET', '/missing');

        $result = $this->router->dispatch($request);

        expect($result)->toBeNull();
    });

    test('dispatch returns route response when route matches', function () {
        $response = $this->createMock(ResponseInterface::class);
        $route = (new RouterFakeRoute())
            ->method('GET')
            ->path('/users')
            ->setResponse($response);

        $this->routeFactory->method('createRoute')->willReturn($route);

        $this->router->get('/users', 'UserHandler');

        $result = $this->router->dispatch(createRouterRequest('GET', '/users'));

        expect($result)->toBe($response);
    });

    test('process falls back to next handler when no route matches', function () {
        $request = createRouterRequest('GET', '/none');
        $fallbackResponse = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($fallbackResponse);

        $result = $this->router->process($request, $handler);

        expect($result)->toBe($fallbackResponse);
    });

    test('group callback receives router instance', function () {
        $called = false;

        $this->router->group('/api', function (Router $group) use (&$called): void {
            $called = true;
            expect($group)->toBeInstanceOf(Router::class);
        });

        expect($called)->toBeTrue();
    });

    test('router middleware is propagated to routes', function () {
        $middleware = new RouterPassThroughMiddleware();
        $response = $this->createMock(ResponseInterface::class);

        $route = (new RouterFakeRoute())
            ->method('GET')
            ->path('/secure')
            ->setResponse($response);

        $this->routeFactory->method('createRoute')->willReturn($route);

        $this->router->withMiddleware($middleware);
        $this->router->get('/secure', 'SecureHandler');
        $this->router->dispatch(createRouterRequest('GET', '/secure'));

        expect($route->middlewareCount())->toBeGreaterThanOrEqual(1);
    });

    test('dispatches matching route from child group', function () {
        $response = $this->createMock(ResponseInterface::class);

        $groupRoute = (new RouterFakeRoute())
            ->method('GET')
            ->path('/api/users')
            ->setResponse($response);

        $this->routeFactory->expects($this->once())
            ->method('createRoute')
            ->with('GET', '/api/users', 'GroupedHandler')
            ->willReturn($groupRoute);

        $this->router->group('/api', function (Router $group): void {
            $group->get('/users', 'GroupedHandler');
        });

        $result = $this->router->process(createRouterRequest('GET', '/api/users'), $this->createMock(RequestHandlerInterface::class));

        expect($result)->toBe($response);
    });

    test('exposes original config instance through protected getter', function () {
        expect($this->router->exposedConfig())->toBe($this->config);
    });
});

/**
 * Creates a request mock for router tests.
 *
 * @param string $method
 * @param string $path
 *
 * @return ServerRequestInterface
 */
function createRouterRequest(string $method, string $path): ServerRequestInterface
{
    $uri = new class ($path) implements UriInterface {
        /**
         * @param string $path
         */
        public function __construct(private string $path)
        {
        }

        /** @return string */
        public function getScheme(): string { return ''; }
        /** @param string $scheme @return static */
        public function withScheme($scheme): static { return $this; }
        /** @return string */
        public function getAuthority(): string { return ''; }
        /** @return string */
        public function getUserInfo(): string { return ''; }
        /** @param string $user @param string|null $password @return static */
        public function withUserInfo($user, $password = null): static { return $this; }
        /** @return string */
        public function getHost(): string { return ''; }
        /** @param string $host @return static */
        public function withHost($host): static { return $this; }
        /** @return int|null */
        public function getPort(): ?int { return null; }
        /** @param int|null $port @return static */
        public function withPort($port): static { return $this; }
        /** @return string */
        public function getPath(): string { return $this->path; }
        /** @param string $path @return static */
        public function withPath($path): static { $this->path = $path; return $this; }
        /** @return string */
        public function getQuery(): string { return ''; }
        /** @param string $query @return static */
        public function withQuery($query): static { return $this; }
        /** @return string */
        public function getFragment(): string { return ''; }
        /** @param string $fragment @return static */
        public function withFragment($fragment): static { return $this; }
        /** @return string */
        public function __toString(): string { return $this->path; }
    };

    $request = test()->createMock(ServerRequestInterface::class);
    $request->method('getMethod')->willReturn($method);
    $request->method('getUri')->willReturn($uri);

    return $request;
}

/**
 * Fake route implementation for router tests.
 */
final class RouterFakeRoute implements RouteInterface
{
    private string $httpMethod = '';
    private string $routePath = '';
    private mixed $routeHandler = null;
    /**
     * @var array<int, string|MiddlewareInterface>
     */
    private array $routeMiddleware = [];
    private ?ResponseInterface $response = null;

    /**
     * @param ResponseInterface $response
     * @return static
     */
    public function setResponse(ResponseInterface $response): static
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @param string $requestMethod
     * @param string $requestPath
     * @return bool
     */
    public function matches(string $requestMethod, string $requestPath): bool
    {
        return $this->httpMethod === $requestMethod && $this->routePath === $requestPath;
    }

    /**
     * @param string $method
     * @return static
     */
    public function method(string $method): static
    {
        $this->httpMethod = strtoupper($method);

        return $this;
    }

    /**
     * @param string $path
     * @return static
     */
    public function path(string $path): static
    {
        $this->routePath = $path;

        return $this;
    }

    /**
     * @param RequestHandlerInterface|callable|string $handler
     * @return static
     */
    public function handler(RequestHandlerInterface|callable|string $handler): static
    {
        $this->routeHandler = $handler;

        return $this;
    }

    /**
     * @param string|MiddlewareInterface ...$middleware
     * @return static
     */
    public function withMiddleware(string|MiddlewareInterface ...$middleware): static
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->response === null) {
            throw new \LogicException('Response must be set for fake route.');
        }

        return $this->response;
    }

    /**
     * @return int
     */
    public function middlewareCount(): int
    {
        return count($this->routeMiddleware);
    }
}

/**
 * Pass-through middleware for router tests.
 */
final class RouterPassThroughMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

/**
 * Exposes protected router internals for focused tests.
 */
final class RouterExposed extends Router
{
    /**
     * @return ConfigInterface
     */
    public function exposedConfig(): ConfigInterface
    {
        return $this->getConfig();
    }
}
