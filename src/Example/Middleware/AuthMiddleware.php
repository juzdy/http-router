<?php

namespace Juzdy\Http\Router\Example\Middleware;

use Juzdy\Http\Router\Exception\RuntimeException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rejects requests that do not carry an X-Auth-Token header.
 * Demonstrates per-method middleware in configuration.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('X-Auth-Token')) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
