<?php
namespace Juzdy\Http\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareStack implements MiddlewareInterface
{
    private array $middleware = [];

    public function push(MiddlewareInterface ...$middleware): static
    {
        array_push($this->middleware, ...$middleware);
        return $this;
    }

    public function unshift(MiddlewareInterface ...$middleware): static
    {
        array_unshift($this->middleware, ...$middleware);
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Process the middleware stack in order
        foreach ($this->middleware as $middleware) {
            $response = $middleware->process($request, $handler);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        // If no middleware returned a response, call the next handler
        return $handler->handle($request);
    }
}