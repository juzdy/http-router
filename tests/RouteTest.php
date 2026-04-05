<?php

namespace Juzdy\Http\Router\Tests;

use Juzdy\Container\DiInvoker;
use Juzdy\Http\Router\Attribute\WithMiddleware;
use Juzdy\Http\Router\Contract\HasAttributes;
use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Route\HandlerFactory;
use Juzdy\Http\Router\Route\MiddlewareFactory;
use Juzdy\Http\Router\Route\Route;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

describe('Route', function () {
    beforeEach(function () {
        $this->handlerFactory = $this->createMock(HandlerFactory::class);
        $this->middlewareFactory = $this->createMock(MiddlewareFactory::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->invoker = $this->createMock(DiInvoker::class);

        $this->route = new Route(
            $this->handlerFactory,
            $this->middlewareFactory,
            $this->responseFactory,
            $this->invoker,
        );
    });

    test('normalizes method and path for matching', function () {
        $this->route->method('get')->path('/users/');

        expect($this->route->matches('GET', '/users'))->toBeTrue();
        expect($this->route->matches('POST', '/users'))->toBeFalse();
    });

    test('matches parameterized route', function () {
        $this->route->method('GET')->path('/users/{id}');

        expect($this->route->matches('GET', '/users/42'))->toBeTrue();
        expect($this->route->matches('GET', '/users'))->toBeFalse();
    });

    test('returns false for non-parameterized path mismatch on same method', function () {
        $this->route->method('GET')->path('/users');

        expect($this->route->matches('GET', '/posts'))->toBeFalse();
    });

    test('accepts middleware instance and class-string implementing interface', function () {
        $middleware = new class implements MiddlewareInterface {
            /**
             * @param ServerRequestInterface $request
             * @param RequestHandlerInterface $handler
             * @return ResponseInterface
             */
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $result = $this->route
            ->withMiddleware($middleware)
            ->withMiddleware(RouteTestMiddleware::class);

        expect($result)->toBe($this->route);
    });

    test('rejects invalid middleware class-string', function () {
        $this->route->withMiddleware('NonExistentMiddleware');
    })->throws(RuntimeException::class);

    test('throws when handle is called before match', function () {
        $this->route->method('GET')->path('/users')->handler(new RouteTestRequestHandler());

        $this->route->handle(createRouteRequest([], []));
    })->throws(RuntimeException::class, 'Route has not been matched yet.');

    test('throws when route matched but no handler defined', function () {
        $this->route->method('GET')->path('/users')->matches('GET', '/users');

        $this->route->handle(createRouteRequest([], []));
    })->throws(RuntimeException::class, 'No handler defined for this route.');

    test('handles matched request with request-handler instance', function () {
        $response = $this->createMock(ResponseInterface::class);
        $handler = new RouteTestRequestHandler($response);

        $request = createRouteRequest([], []);

        $this->route->method('GET')->path('/users')->handler($handler);
        $this->route->matches('GET', '/users');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });

    test('resolves string handler through handler factory', function () {
        $response = $this->createMock(ResponseInterface::class);
        $resolvedHandler = new RouteTestRequestHandler($response);

        $this->handlerFactory->expects($this->once())
            ->method('createHandler')
            ->with('service.handler')
            ->willReturn($resolvedHandler);

        $request = createRouteRequest([], []);

        $this->route->method('GET')->path('/users')->handler('service.handler');
        $this->route->matches('GET', '/users');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });

    test('throws on route/query parameter conflict', function () {
        $response = $this->createMock(ResponseInterface::class);
        $handler = new RouteTestRequestHandler($response);

        $request = createRouteRequest(['id' => '100'], []);

        $this->route->method('GET')->path('/users/{id}')->handler($handler);
        $this->route->matches('GET', '/users/100');

        $this->route->handle($request);
    })->throws(RuntimeException::class, 'Route parameters conflict with query parameters');

    test('normalizes string handler response using response factory', function () {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $stream->expects($this->once())
            ->method('write')
            ->with('ok');

        $this->invoker->expects($this->once())
            ->method('__invoke')
            ->willReturn('ok');

        $handler = static fn (): string => 'ignored';

        $request = createRouteRequest([], []);

        $this->route->method('GET')->path('/plain')->handler($handler);
        $this->route->matches('GET', '/plain');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });

    test('registers middleware from handler attributes when resolving string handler', function () {
        $response = $this->createMock(ResponseInterface::class);
        $resolvedHandler = new AttributedHandler($response);

        $this->handlerFactory->expects($this->once())
            ->method('createHandler')
            ->with('service.handler')
            ->willReturn($resolvedHandler);

        $request = createRouteRequest([], []);

        $this->route->method('GET')->path('/attr')->handler('service.handler');
        $this->route->matches('GET', '/attr');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });

    test('resolves class-string middleware during handling', function () {
        $response = $this->createMock(ResponseInterface::class);
        $handler = new RouteTestRequestHandler($response);
        $middleware = new RouteTestMiddleware();

        $this->middlewareFactory->expects($this->once())
            ->method('createMiddleware')
            ->with(RouteTestMiddleware::class)
            ->willReturn($middleware);

        $request = createRouteRequest([], []);

        $this->route
            ->method('GET')
            ->path('/mw')
            ->handler($handler)
            ->withMiddleware(RouteTestMiddleware::class);

        $this->route->matches('GET', '/mw');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });

    test('uses middleware instance directly during handling', function () {
        $response = $this->createMock(ResponseInterface::class);
        $handler = new RouteTestRequestHandler($response);
        $middleware = new RouteTestMiddleware();

        $this->middlewareFactory->expects($this->never())
            ->method('createMiddleware');

        $request = createRouteRequest([], []);

        $this->route
            ->method('GET')
            ->path('/mw-instance')
            ->handler($handler)
            ->withMiddleware($middleware);

        $this->route->matches('GET', '/mw-instance');

        $result = $this->route->handle($request);

        expect($result)->toBe($response);
    });
});

/**
 * Creates a request mock for route tests.
 *
 * @param array<string, mixed> $query
 * @param array<string, mixed> $mergedResult
 *
 * @return ServerRequestInterface
 */
function createRouteRequest(array $query, array $mergedResult): ServerRequestInterface
{
    $request = test()->createMock(ServerRequestInterface::class);

    $request->method('getQueryParams')->willReturn($query);

    $request->method('withQueryParams')->willReturnCallback(
        static function (array $params) use ($request, $mergedResult): ServerRequestInterface {
            if ($mergedResult !== []) {
                test()->assertSame($mergedResult, $params);
            }

            return $request;
        }
    );

    return $request;
}

/**
 * Middleware class used for class-string validation tests.
 */
final class RouteTestMiddleware implements MiddlewareInterface
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
 * Request handler test double.
 */
final class RouteTestRequestHandler implements RequestHandlerInterface
{
    /**
     * @param ResponseInterface|null $response
     */
    public function __construct(private ?ResponseInterface $response = null)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->response === null) {
            throw new \LogicException('Response not configured for RouteTestRequestHandler');
        }

        return $this->response;
    }
}

#[WithMiddleware([RouteTestMiddleware::class])]
final class AttributedHandler implements RequestHandlerInterface, HasAttributes
{
    /**
     * @param ResponseInterface $response
     */
    public function __construct(private ResponseInterface $response)
    {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
