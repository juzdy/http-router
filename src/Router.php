<?php
namespace Juzdy\Http\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Juzdy\Config\ConfigInterface;
use Juzdy\Container\Contract\Lifecycle\SharedInterface;
use Juzdy\Http\Router\Exception\RuntimeException;
use Juzdy\Http\Router\Proxy\GroupedRouter;
use Juzdy\Http\Router\Route\RouteFactory;
use Juzdy\Http\Router\Route\RouteInterface;
use Traversable;

class Router implements RouterInterface, SharedInterface
{
    /**
     * @var array<string, array<string, RouteInterface>> $routes
     */
    private array $routes = [];

    /**
     * @var RouterInterface[] $groups
     */
    private array $groups = [];

    /**
     * @var array $middleware
     */
    private array $middleware = [];

    /**
     * @param ConfigInterface       $config         The configuration instance
     * @param RouteFactory $routeFactory   The route factory instance
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly RouteFactory $routeFactory,
        //private readonly ShareManager $shareManager,
        private string $prefix = '',
    )
    {
    }

    /**
     * {@inheritDoc}
     */
    public function withMiddleware(MiddlewareInterface|string ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //$this->prepareServerRequest($request);

        return $this->dispatch($request) ?? $this->dispatchGroups($request) ?? $handler->handle($request);
    }

    /**
     * Prepares the server request for route handling by sharing it in the container.
     *
     * @param ServerRequestInterface $request The server request to prepare
     */
    // protected function prepareServerRequest(ServerRequestInterface $request)
    // {
    //     $this->shareManager->share(RouteRequestInterface::class, $request);
    // }

    /**
     * Registers a GET route with the specified path and handler.
     *
     * @param string                                    $path       The path pattern for the route
     * @param RequestHandlerInterface|string|callable   $handler    The handler for the route, 
     *                                                              which can be a request handler instance,
     *                                                              a callable,
     *                                                              or a string representing a service identifier
     * 
     * @return RouteInterface
     */
    public function get(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('GET', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('POST', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('PUT', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function patch(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('PATCH', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('DELETE', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function head(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('HEAD', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function options(string $path, RequestHandlerInterface|string|callable $handler): RouteInterface
    {
        return $this->createRoute('OPTIONS', $path, $handler);
    }

    public function group(string $prefix = '', ?callable $callback = null, MiddlewareInterface|string ...$middleware): static
    {
        $groupRouter = (new static($this->config, $this->routeFactory, $prefix));

        $this->groups[] = 
            $groupRouter
                ->withMiddleware(...$this->middleware, ...$middleware);

        if ($callback) {
            $callback($groupRouter);
        }

        return $groupRouter;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        foreach ($this->getRoutesForMethod($method) as $route) {
            if ($route->matches($method, $path)) {

                foreach ($this->middleware as $middleware)
                {
                    //todo: router middleware first then route middleware
                    $route->withMiddleware($middleware);
                }

                return $route->handle($request);
            }
        }

        return null;
    }

    protected function dispatchGroups(ServerRequestInterface $request): ?ResponseInterface
    {
        foreach ($this->groups as $group) {
            if ($response = $group->dispatch($request)) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Creates a new route for the given HTTP method, path, and handler.
     *
     * @param string                                    $method     The HTTP method (e.g., GET, POST)
     * @param string                                    $path       The path pattern for the route
     * @param callable|string|RequestHandlerInterface   $handler    The handler for the route, 
     *                                                              which can be a callable,
     *                                                              a string representing a service identifier,
     *                                                              or an instance of RequestHandlerInterface
     * 
     * @return RouteInterface The created route instance
     */
    protected function createRoute(string $method, string $path, callable|string|RequestHandlerInterface $handler): RouteInterface
    {
        $method = strtoupper($method);
        $path = $this->prefix . $path;

        if ($this->has($path, $method)) {
            throw new RuntimeException("Route already exists for path '{$path}' and method '{$method}'");
        }

        return $this->routes[$method][$path] = $this->getRouteFactory()
            ->createRoute($method, $path, $handler)
            ->withMiddleware(...$this->middleware);
            ;
    }

    /**
     * Checks if a route exists for the given path and HTTP method.
     *
     * @param string $path   The request path to check
     * @param string $method The HTTP method to check (e.g., GET, POST)
     * 
     * @return bool True if a matching route exists, false otherwise
     */
    public function has(string $path, string $method): bool
    {
        return isset($this->routes[$method][$path]);
    }

    /**
     * Retrieves all routes registered for the specified HTTP method.
     *
     * @param string $method The HTTP method to retrieve routes for (e.g., GET, POST)
     * 
     * @return Traversable An iterable collection of RouteInterface instances for the specified method
     */
    protected function getRoutesForMethod(string $method): Traversable
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            yield $route;
        }
    }

    /**
     * @return ConfigInterface
     */
    protected function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * @return RouteFactory
     */
    protected function getRouteFactory(): RouteFactory
    {
        return $this->routeFactory;
    }
}