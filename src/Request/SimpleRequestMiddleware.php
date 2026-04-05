<?php

namespace Juzdy\Http\Router\Request;

use Psr\Http\Server\MiddlewareInterface;

class SimpleRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SimpleRequestInterface $simpleRequest,
    ) {
    }

    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
    {
        $this->simpleRequest->withServerRequest($request);
        return $handler->handle($request);
    }
}