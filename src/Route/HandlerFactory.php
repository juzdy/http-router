<?php
namespace Juzdy\Http\Router\Route;

use Juzdy\Http\Router\Exception\RuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HandlerFactory
{
    /**
     * @param ContainerInterface $container The container used to resolve handlers
     */
    public function __construct(
        protected readonly ContainerInterface $container,
    ) 
    {}

    /**
     * Creates a handler instance from the given class name.
     *
     * @param string $handler The class name of the handler to create.
     * 
     * @return RequestHandlerInterface|callable The created handler instance.
     */
    public function createHandler(string $handler): RequestHandlerInterface|callable
    {
        $handler = $this->container->get($handler);
        
        if (!$handler instanceof RequestHandlerInterface && !is_callable($handler)) {
            throw new RuntimeException(_("Handler string identifier did not resolve to a valid request handler instance or callable."));
        }

        return $handler;
    }
}