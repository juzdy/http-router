<?php

namespace Juzdy\Http\Router\Tests;

use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Route\RouteFactory;
use Juzdy\Http\Router\Route\RouteInterface;
use Juzdy\Http\Router\Route\HandlerFactory;
use Juzdy\Http\Router\Route\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

describe('RouteFactory', function () {

    beforeEach(function () {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new RouteFactory($this->container);
    });

    describe('Route Creation', function () {

        test('create route with all parameters', function () {
            $route = new TestRoute();
            $this->container->expects($this->once())
                ->method('get')
                ->with(RouteInterface::class)
                ->willReturn($route);

            $handler = function () {};
            $result = $this->factory->createRoute('GET', '/users', $handler);

            expect($result)->toBe($route);
        });

        test('create route with string handler identifier', function () {
            $route = new TestRoute();
            $this->container->expects($this->once())
                ->method('get')
                ->with(RouteInterface::class)
                ->willReturn($route);

            $result = $this->factory->createRoute('POST', '/users', 'UserHandler');

            expect($result)->toBe($route);
        });

        test('create route with RequestHandlerInterface', function () {
            $route = new TestRoute();
            $this->container->expects($this->once())
                ->method('get')
                ->with(RouteInterface::class)
                ->willReturn($route);

            $handler = new class implements RequestHandlerInterface {
                /**
                 * Handles the incoming request.
                 *
                 * @param ServerRequestInterface $request
                 *
                 * @return ResponseInterface
                 */
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new class implements ResponseInterface {
                        use EmptyResponseMethods;
                    };
                }
            };

            $result = $this->factory->createRoute('PUT', '/users/1', $handler);

            expect($result)->toBe($route);
        });

        test('create route with middleware', function () {
            $route = new TestRoute();
            $this->container->expects($this->once())
                ->method('get')
                ->with(RouteInterface::class)
                ->willReturn($route);

            $middleware = new class implements MiddlewareInterface {
                /**
                 * Processes the request and delegates to the next handler.
                 *
                 * @param ServerRequestInterface  $request
                 * @param RequestHandlerInterface $handler
                 *
                 * @return ResponseInterface
                 */
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            };

            $result = $this->factory->createRoute('GET', '/users', 'UserHandler', $middleware);

            expect($result)->toBe($route);
            expect($route->middlewareCount())->toBe(1);
        });

        test('create route with multiple middleware', function () {
            $route = new TestRoute();
            $this->container->expects($this->once())
                ->method('get')
                ->with(RouteInterface::class)
                ->willReturn($route);

            $middleware1 = new class implements MiddlewareInterface {
                /**
                 * Processes the request and delegates to the next handler.
                 *
                 * @param ServerRequestInterface  $request
                 * @param RequestHandlerInterface $handler
                 *
                 * @return ResponseInterface
                 */
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            };

            $middleware2 = new class implements MiddlewareInterface {
                /**
                 * Processes the request and delegates to the next handler.
                 *
                 * @param ServerRequestInterface  $request
                 * @param RequestHandlerInterface $handler
                 *
                 * @return ResponseInterface
                 */
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            };

            $result = $this->factory->createRoute('GET', '/users', 'UserHandler', $middleware1, $middleware2);

            expect($result)->toBe($route);
            expect($route->middlewareCount())->toBe(2);
        });

    });

});

describe('HandlerFactory', function () {

    beforeEach(function () {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new HandlerFactory($this->container);
    });

    describe('Handler Creation', function () {

        test('create handler from string identifier', function () {
            $handler = function () {};
            $this->container->expects($this->once())
                ->method('get')
                ->with('UserHandler')
                ->willReturn($handler);

            $result = $this->factory->createHandler('UserHandler');

            expect($result)->toBe($handler);
        });

        test('create RequestHandlerInterface from container', function () {
            $handler = new class implements RequestHandlerInterface {
                /**
                 * Handles the incoming request.
                 *
                 * @param ServerRequestInterface $request
                 *
                 * @return ResponseInterface
                 */
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new class implements ResponseInterface {
                        use EmptyResponseMethods;
                    };
                }
            };

            $this->container->expects($this->once())
                ->method('get')
                ->with('UserHandler')
                ->willReturn($handler);

            $result = $this->factory->createHandler('UserHandler');

            expect($result)->toBe($handler);
        });

        test('throw exception for non-callable non-handler', function () {
            $this->container->expects($this->once())
                ->method('get')
                ->with('InvalidHandler')
                ->willReturn('not a handler');

            $this->factory->createHandler('InvalidHandler');
        })->throws(RuntimeException::class);

    });

});

describe('MiddlewareFactory', function () {

    beforeEach(function () {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new MiddlewareFactory($this->container);
    });

    describe('Middleware Creation', function () {

        test('create middleware from string identifier', function () {
            $middleware = new class implements MiddlewareInterface {
                /**
                 * Processes the request and delegates to the next handler.
                 *
                 * @param ServerRequestInterface  $request
                 * @param RequestHandlerInterface $handler
                 *
                 * @return ResponseInterface
                 */
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            };

            $this->container->expects($this->once())
                ->method('get')
                ->with('AuthMiddleware')
                ->willReturn($middleware);

            $result = $this->factory->createMiddleware('AuthMiddleware');

            expect($result)->toBeInstanceOf(MiddlewareInterface::class);
        });

        test('create middleware instance', function () {
            $middleware = new class implements MiddlewareInterface {
                /**
                 * Processes the request and delegates to the next handler.
                 *
                 * @param ServerRequestInterface  $request
                 * @param RequestHandlerInterface $handler
                 *
                 * @return ResponseInterface
                 */
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            };

            $this->container->expects($this->once())
                ->method('get')
                ->with('LoggingMiddleware')
                ->willReturn($middleware);

            $result = $this->factory->createMiddleware('LoggingMiddleware');

            expect($result)->toBe($middleware);
        });

    });

});

/**
 * Minimal route fake for factory tests.
 */
final class TestRoute implements RouteInterface
{
    private string $methodValue = '';
    private string $pathValue = '';
    private mixed $handlerValue = null;
    /**
     * @var array<int, string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Checks whether the route matches the request.
     *
     * @param string $requestMethod
     * @param string $requestPath
     *
     * @return bool
     */
    public function matches(string $requestMethod, string $requestPath): bool
    {
        return $this->methodValue === $requestMethod && $this->pathValue === $requestPath;
    }

    /**
     * Sets the HTTP method.
     *
     * @param string $method
     *
     * @return static
     */
    public function method(string $method): static
    {
        $this->methodValue = $method;

        return $this;
    }

    /**
     * Sets the route path.
     *
     * @param string $path
     *
     * @return static
     */
    public function path(string $path): static
    {
        $this->pathValue = $path;

        return $this;
    }

    /**
     * Sets the route handler.
     *
     * @param RequestHandlerInterface|callable|string $handler
     *
     * @return static
     */
    public function handler(RequestHandlerInterface|callable|string $handler): static
    {
        $this->handlerValue = $handler;

        return $this;
    }

    /**
     * Adds middleware.
     *
     * @param string|MiddlewareInterface ...$middleware
     *
     * @return static
     */
    public function withMiddleware(string|MiddlewareInterface ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Handles request by returning a placeholder response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new class implements ResponseInterface {
            use EmptyResponseMethods;
        };
    }

    /**
     * Returns number of middleware entries.
     *
     * @return int
     */
    public function middlewareCount(): int
    {
        return count($this->middleware);
    }
}

/**
 * Minimal no-op ResponseInterface implementation for tests.
 */
trait EmptyResponseMethods
{
    /**
     * @return string
     */
    public function getProtocolVersion(): string { return '1.1'; }
    /**
     * @param string $version
     * @return static
     */
    public function withProtocolVersion($version): static { return $this; }
    /**
     * @return array<string, array<int, string>>
     */
    public function getHeaders(): array { return []; }
    /**
     * @param string $name
     * @return bool
     */
    public function hasHeader($name): bool { return false; }
    /**
     * @param string $name
     * @return array<int, string>
     */
    public function getHeader($name): array { return []; }
    /**
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name): string { return ''; }
    /**
     * @param string $name
     * @param string|array<int, string> $value
     * @return static
     */
    public function withHeader($name, $value): static { return $this; }
    /**
     * @param string $name
     * @param string|array<int, string> $value
     * @return static
     */
    public function withAddedHeader($name, $value): static { return $this; }
    /**
     * @param string $name
     * @return static
     */
    public function withoutHeader($name): static { return $this; }
    /**
     * @return \Psr\Http\Message\StreamInterface
     */
    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        return new class implements \Psr\Http\Message\StreamInterface {
            public function __toString(): string { return ''; }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return 0; }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek($offset, $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return false; }
            public function write($string): int { return 0; }
            public function isReadable(): bool { return false; }
            public function read($length): string { return ''; }
            public function getContents(): string { return ''; }
            public function getMetadata($key = null): mixed { return null; }
        };
    }
    /**
     * @param \Psr\Http\Message\StreamInterface $body
     * @return static
     */
    public function withBody(\Psr\Http\Message\StreamInterface $body): static { return $this; }
    /**
     * @return int
     */
    public function getStatusCode(): int { return 200; }
    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return static
     */
    public function withStatus($code, $reasonPhrase = ''): static { return $this; }
    /**
     * @return string
     */
    public function getReasonPhrase(): string { return ''; }
}
