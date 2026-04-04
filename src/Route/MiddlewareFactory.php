<?php
namespace Juzdy\Http\Router\Route;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

class MiddlewareFactory
{
    /**
     * @param ContainerInterface $container The container used to resolve middleware instances.
     */
    public function __construct(
        protected readonly ContainerInterface $container,
    ) 
    {}

    /**
     * Creates a middleware instance from the given class name.
     *
     * @param string $middleware The class name of the middleware to create.
     * 
     * @return MiddlewareInterface The created middleware instance.
     */
    public function createMiddleware(string $middleware): MiddlewareInterface
    {
        return $this->container->get($middleware);
    }
}