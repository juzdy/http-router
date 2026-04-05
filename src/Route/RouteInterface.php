<?php

namespace Juzdy\Http\Router\Route;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteInterface //extends RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;

    /**
     * Determines if the given path matches the route's path pattern and HTTP method.
     * @param string $requestMethod The HTTP method of the incoming request to match against the route's method.
     * @param string $requestPath   The request path to match against the route's path pattern.
     * 
     * @return bool True if the path matches the route's pattern and method, false otherwise
     */
    public function matches(string $requestMethod, string $requestPath): bool;

    /**
     * Sets the HTTP method for the route.
     * @param string $method The HTTP method to set for the route (e.g., "GET", "POST", etc.).
     * 
     * @return static Returns the current instance of the route for method chaining.
     */
    public function method(string $method): static;
    
    /**
     * Sets the path pattern for the route.
     * @param string $path The path pattern to set for the route (e.g., "/users/{id}").
     * 
     * @return static Returns the current instance of the route for method chaining.
     */
    public function path(string $path): static;

    /**
     * Sets the handler for the route.
     * @param RequestHandlerInterface|callable|string $handler The handler to set for the route, 
     *                                                        which can be a request handler instance,
     *                                                        a callable, or a string representing a service identifier.
     * 
     * @return static Returns the current instance of the route for method chaining.
     */
    public function handler(RequestHandlerInterface|callable|string $handler): static;
    
    /**
     * Adds middleware to the route.
     * 
     * @template T of class-string|MiddlewareInterface
     * @param T ...$middleware One or more middleware class names or instances to add to the route.
     * 
     * @return static Returns the current instance of the route for method chaining.
     */
    public function withMiddleware(string|MiddlewareInterface ...$middleware): static;
}
