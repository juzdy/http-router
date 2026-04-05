<?php

namespace Juzdy\Http\Router\Example\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds X-Request-Log header to every response passing through this middleware.
 * Demonstrates route/group middleware usage in configuration.
 */
final class LogMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);

        $response = $handler->handle($request);

        $elapsed = round((microtime(true) - $startTime) * 1000, 2);

        return $response->withHeader(
            'X-Request-Log',
            sprintf('%s %s — %s ms', $request->getMethod(), $request->getUri()->getPath(), $elapsed),
        );
    }
}
