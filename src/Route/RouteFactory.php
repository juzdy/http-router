<?php
namespace Juzdy\Http\Router\Route;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteFactory
{
    public function __construct(
        protected readonly ContainerInterface $container,
    ) 
    {}

    public function createRoute(
        string $method, string $path, 
        RequestHandlerInterface|callable|string $handler, 
        string|MiddlewareInterface ...$middleware
    ): RouteInterface
    {
        $route = $this->container->get(RouteInterface::class);
        $route->method($method)
              ->path($path)
              ->handler($handler)
              ->withMiddleware(...$middleware);

        return $route;
    }
}