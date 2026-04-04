<?php

namespace Juzdy\Http\Router\Route\Handler;

use Psr\Container\ContainerInterface;
use Juzdy\Container\DiInvoker;
use Juzdy\Http\Router\Route\Route;
use Psr\Http\Server\RequestHandlerInterface;

class RouteHandlerFactory //implements HandlerFactoryInterface
{
    public function __construct(
        private ContainerInterface $container,
        private DiInvoker $invoker,
    ) 
    {}

    public function create(callable|string $handler): RouteHandlerInterface
    {
        $handler = null;
        $routeHandler = $this->container->get(RouteHandlerInterface::class);

        if (is_string($handler)) {
            $handler = $this->container->get($handler);
            if (!is_callable($handler)) {
                throw new \InvalidArgumentException("Handler class must be invokable.");
            }
            $handler = $handler;
        }

        if (is_callable($handler)) {
            $handler = fn() => ($this->invoker)($handler);
        }

        return $routeHandler->withHandler($handler);    
    }
}