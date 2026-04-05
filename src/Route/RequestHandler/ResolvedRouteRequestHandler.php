<?php

namespace Juzdy\Http\Router\Route\RequestHandler;

use Closure;
use Juzdy\Container\DiInvoker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ResolvedRouteRequestHandler implements RequestHandlerInterface
{
    private mixed $handler;

    public function __construct(
        RequestHandlerInterface|callable $handler,
        private readonly DiInvoker $invoker,
        private readonly Closure $normalizeResponse,
    ) {
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = match (true) {
            $this->handler instanceof RequestHandlerInterface => $this->handler->handle($request),
            default => ($this->invoker)($this->handler),
        };

        return ($this->normalizeResponse)($response);
    }
}
