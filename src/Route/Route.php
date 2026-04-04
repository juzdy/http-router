<?php
namespace Juzdy\Http\Router\Route;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Juzdy\Container\DiInvoker;
use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Route\Middleware\MiddlewarePipeline;
use Stringable;

class Route implements RouteInterface
{
    /**
     * @var string|null The HTTP method for the route (e.g., GET, POST).
     */
    private ?string $method = null;
    /**
     * @var string|null The path pattern for the route (e.g., /users/{id}).
     */
    private ?string $path = null;
    /**
     * @var RequestHandlerInterface|callable|string|null The handler for the route, which can be a request handler instance, a callable, or a string representing a service identifier.
     */
    private $handler = null;
    /**
     * @var bool Indicates whether the route has been matched against a request.
     */
    private bool $isMatched = false;
    /**
     * @var array An array of middleware associated with the route, which can contain string identifiers or MiddlewareInterface instances.
     */
    private array $middleware = [];
    /**
     * @var array An associative array of parameters extracted from the route path when a match occurs.
     */
    private array $parameters = [];

    /**
     * @param HandlerFactory            $handlerFactory  The factory for creating route handlers.
     * @param MiddlewareFactory         $middlewareFactory The factory for creating route middleware.
     * @param ResponseFactoryInterface  $responseFactory The factory for creating response instances.
     * @param DiInvoker                 $invoker         The invoker for calling handlers and middleware with dependency injection.
     */
    public function __construct(
        private HandlerFactory $handlerFactory,
        private MiddlewareFactory $middlewareFactory,
        private ResponseFactoryInterface $responseFactory,
        private DiInvoker $invoker,
    ) 
    {}

    /**
     * {@inheritDoc}
     */
    public function matches(string $requestMethod, string $requestPath): bool
    {
        $requestPath = rtrim($requestPath, '/');
        
        if ($this->method !== $requestMethod) {
            return false;
        }

        if ($this->path === $requestPath) {
            return $this->isMatched = true;
        }

        preg_match_all('/{([^}]+)}/', $this->path, $matches);

        if (empty($matches[1])) {
            return false;
        }

        $regex = preg_replace('/{[^}]+}/', '([^/]+)', $this->path);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $requestPath, $paramValues)) {
            array_shift($paramValues);

            $this->parameters = array_combine($matches[1], $paramValues);

            return $this->isMatched = true;
        }
       
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function handler(RequestHandlerInterface|callable|string $handler): static
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function path(string $path): static
    {
        $this->path = rtrim($path, '/');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function method(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withMiddleware(string|MiddlewareInterface ...$middleware): static
    {

        foreach ($middleware as $middlewareClass) {
            if (is_string($middlewareClass) && !is_a($middlewareClass, MiddlewareInterface::class, true)) {
                throw new RuntimeException("Class {$middlewareClass} does not implement MiddlewareInterface.");
            }
        }

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isMatched) {
            throw new RuntimeException("Route has not been matched yet.");
        }

        if (!$this->handler) {
            throw new RuntimeException("No handler defined for this route.");
        }

        $request = $this->mergeQueryParams($request);

        if (($response = $this->processWithMiddleware($request)) instanceof ResponseInterface) {
            return $response;
        }

        // if ($response instanceof Stringable || is_string($response)) {
        //     $r = $this->responseFactory->createResponse();
        //     $r->getBody()
        //         ->write((string)$response);

        //     return $r;
        // }

        throw new RuntimeException(_("Invalid response type returned from route handler."));
    }

    /**
     * Process the request through the route's middleware stack and then invoke the handler.
     *
     * @param ServerRequestInterface $request The incoming server request to process.
     * 
     * @return ResponseInterface|Stringable|string The response generated by the handler, which can be a ResponseInterface, a Stringable, or a string.
     */
    protected function processWithMiddleware(ServerRequestInterface $request): ResponseInterface|Stringable|string
    {
        $next = function (ServerRequestInterface $request) {
            if (is_string($this->handler)) {
                $this->handler = $this->handlerFactory->createHandler($this->handler);
            }

            if ($this->handler instanceof RequestHandlerInterface) {
                return $this->handler->handle($request);
            }

            if (!is_callable($this->handler)) {
                throw new RuntimeException(_("Handler is not callable or a valid request handler instance."));
            }

            return ($this->invoker)($this->handler);
        };

        foreach ($this->middleware as $middleware) {

            $middlewareInstance = match (true) {
                is_string($middleware) => $this->middlewareFactory->createMiddleware($middleware),
                $middleware instanceof MiddlewareInterface => $middleware,
                default => throw new RuntimeException(_('Middleware must be either a string identifier or an instance of MiddlewareInterface.')),
            }
            ;
            $currentNext = $next;
            $next = fn (ServerRequestInterface $req) => $middlewareInstance->process($req, new class($currentNext, $this->responseFactory) implements RequestHandlerInterface {

                public function __construct(private $handler, private ResponseFactoryInterface $responseFactory)
                {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $response = ($this->handler)($request);

                    if ($response instanceof Stringable || is_string($response)) {
                        $r = $this->responseFactory->createResponse();
                        $r->getBody()
                            ->write((string)$response);

                        return $r;
                    }

                    if (!$response instanceof ResponseInterface) {
                        throw new RuntimeException(_("Invalid response type returned from middleware or handler."));
                    }

                    return $response;
                }
            });
        }

        return $next($request);
    }

    /**
     * Get the route parameters extracted from the path.
     *
     * @return array The route parameters as an associative array.
     */
    protected function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Merge route parameters with query parameters, ensuring no conflicts.
     *
     * @param ServerRequestInterface $request The incoming server request.
     * 
     * @return ServerRequestInterface The modified request with merged query parameters.
     * 
     * @throws RuntimeException If there are conflicting parameter names between route parameters and query parameters.
     */
    protected function mergeQueryParams(ServerRequestInterface $request): ServerRequestInterface
    {
        $queryParams = $request->getQueryParams();
        $intersection = array_intersect($this->getParameters(), $queryParams);

        if (!empty($intersection)) {
            throw new RuntimeException(_("Route parameters conflict with query parameters: " . implode(', ', array_keys($intersection))));
        }

        $mergedParams = array_merge($queryParams, $this->getParameters());

        return $request->withQueryParams($mergedParams);
    }
    
}