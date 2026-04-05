<?php

namespace Juzdy\Http\Router\Tests;

use Juzdy\Http\Router\Attribute\WithMiddleware;
use Juzdy\Http\Router\Config\RouteConfigProcessor;
use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Route\RouteInterface;
use Juzdy\Http\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

describe('WithMiddleware Attribute', function () {
    test('yields valid middleware entries', function () {
        $attribute = new WithMiddleware([
            AttributeDummyMiddleware::class,
        ]);

        $result = iterator_to_array($attribute->getMiddleware());

        expect($result)->toBe([AttributeDummyMiddleware::class]);
    });

    test('throws for invalid middleware entries', function () {
        $attribute = new WithMiddleware([
            123,
        ]);

        iterator_to_array($attribute->getMiddleware());
    })->throws(\InvalidArgumentException::class);
});

describe('RouteConfigProcessor', function () {
    beforeEach(function () {
        $this->processor = new RouteConfigProcessor();
    });

    test('registers shorthand GET route and ignores non-path root keys', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            'not-path' => 'ignored',
            '/health' => 'HealthHandler',
        ]);

        expect($router->calls)->toHaveCount(1);
        expect($router->calls[0]['verb'])->toBe('get');
        expect($router->calls[0]['path'])->toBe('/health');
    });

    test('registers verb routes with merged middleware', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/posts' => [
                'middleware' => [AttributeDummyMiddleware::class],
                'POST' => [
                    'handler' => 'CreatePostHandler',
                    'middleware' => [AttributeDummyMiddleware::class],
                ],
            ],
        ]);

        expect($router->calls)->toHaveCount(1);
        expect($router->calls[0]['verb'])->toBe('post');
        expect($router->calls[0]['path'])->toBe('/posts');
        expect($router->calls[0]['route']->middlewareCount())->toBe(2);
    });

    test('ignores non-string node keys while collecting verbs', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/posts' => [
                0 => 'ignored',
                'GET' => 'ListPostsHandler',
            ],
        ]);

        expect($router->calls)->toHaveCount(1);
        expect($router->calls[0]['verb'])->toBe('get');
        expect($router->calls[0]['handler'])->toBe('ListPostsHandler');
    });

    test('registers nested child nodes through group callback', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/api' => [
                'middleware' => [AttributeDummyMiddleware::class],
                '/me' => [
                    'GET' => 'ProfileHandler',
                ],
            ],
        ]);

        expect($router->groupCalls)->toHaveCount(1);
        expect($router->groupCalls[0]['prefix'])->toBe('/api');
        expect($router->groupCalls[0]['middleware'])->toBe([AttributeDummyMiddleware::class]);
    });

    test('throws on invalid node config type', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/invalid' => 42,
        ]);
    })->throws(RuntimeException::class);

    test('throws when verb config array misses handler key', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/posts' => [
                'POST' => [
                    'middleware' => [AttributeDummyMiddleware::class],
                ],
            ],
        ]);
    })->throws(RuntimeException::class);

    test('throws when verb config is neither string nor array', function () {
        $router = new ProcessorRouterFake();

        $this->processor->process($router, [
            '/posts' => [
                'POST' => 42,
            ],
        ]);
    })->throws(RuntimeException::class);
});

final class ProcessorRouterFake implements RouterInterface
{
    /**
     * @var array<int, array{verb:string,path:string,handler:mixed,route:ProcessorRouteFake}>
     */
    public array $calls = [];

    /**
     * @var array<int, array{prefix:string,middleware:array<int, string|MiddlewareInterface>}>
     */
    public array $groupCalls = [];

    /**
     * @var array<int, string|MiddlewareInterface>
     */
    private array $routerMiddleware = [];

    /**
     * @param MiddlewareInterface|string ...$middleware
     * @return static
     */
    public function withMiddleware(MiddlewareInterface|string ...$middleware): static
    {
        $this->routerMiddleware = array_merge($this->routerMiddleware, $middleware);

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface|null
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        return null;
    }

    /**
     * @param string $prefix
     * @param callable|null $callback
     * @param MiddlewareInterface|string ...$middleware
     * @return static
     */
    public function group(string $prefix = '', ?callable $callback = null, MiddlewareInterface|string ...$middleware): static
    {
        $this->groupCalls[] = [
            'prefix' => $prefix,
            'middleware' => $middleware,
        ];

        if ($callback !== null) {
            $groupRouter = new self();
            $callback($groupRouter);
        }

        return $this;
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function get(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('get', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function post(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('post', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function put(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('put', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function patch(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('patch', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function delete(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('delete', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function head(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('head', $path, $handler);
    }

    /**
     * @param string $path
     * @param RequestHandlerInterface|string|callable $handler
     * @return RouteInterface
     */
    public function options(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->addCall('options', $path, $handler);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }

    /**
     * @param string $verb
     * @param string $path
     * @param mixed $handler
     * @return RouteInterface
     */
    private function addCall(string $verb, string $path, mixed $handler): RouteInterface
    {
        $route = new ProcessorRouteFake();

        $this->calls[] = [
            'verb' => $verb,
            'path' => $path,
            'handler' => $handler,
            'route' => $route,
        ];

        return $route;
    }
}

final class ProcessorRouteFake implements RouteInterface
{
    /**
     * @var array<int, string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @param string $requestMethod
     * @param string $requestPath
     * @return bool
     */
    public function matches(string $requestMethod, string $requestPath): bool
    {
        return false;
    }

    /**
     * @param string $method
     * @return static
     */
    public function method(string $method): static
    {
        return $this;
    }

    /**
     * @param string $path
     * @return static
     */
    public function path(string $path): static
    {
        return $this;
    }

    /**
     * @param RequestHandlerInterface|callable|string $handler
     * @return static
     */
    public function handler(RequestHandlerInterface|callable|string $handler): static
    {
        return $this;
    }

    /**
     * @param string|MiddlewareInterface ...$middleware
     * @return static
     */
    public function withMiddleware(string|MiddlewareInterface ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \LogicException('Not used in config tests.');
    }

    /**
     * @return int
     */
    public function middlewareCount(): int
    {
        return count($this->middleware);
    }
}

final class AttributeDummyMiddleware implements MiddlewareInterface
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
