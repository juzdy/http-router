<?php

namespace Juzdy\Http\Router\Tests;

use Juzdy\App\AppInterface;
use Juzdy\Config\ConfigInterface;
use Juzdy\Container\Binder\BindingManager;
use Juzdy\Http\Router\Config\RouteConfigProcessor;
use Juzdy\Http\Router\Package;
use Juzdy\Http\Router\Route\Route;
use Juzdy\Http\Router\Route\RouteInterface;
use Juzdy\Http\Router\Router;
use Juzdy\Http\Router\RouterInterface;
use Psr\Http\Server\MiddlewareInterface;

describe('Package', function () {
    test('boot registers expected core bindings', function () {
        $package = new Package();
        $config = test()->createMock(ConfigInterface::class);
        $config->method('get')->with('http-router')->willReturn([]);

        $bindingManager = new BindingManager();
        $app = new PackageTestApp([
            BindingManager::class => $bindingManager,
            RouteConfigProcessor::class => new class {
                /**
                 * @param RouterInterface $router
                 * @param array<int|string, mixed> $config
                 * @return void
                 */
                public function process(RouterInterface $router, array $config): void
                {
                }
            },
            RouterInterface::class => test()->createMock(RouterInterface::class),
            \Juzdy\Http\Router\Event\RouterInitialized::class => new class {
                public bool $fired = false;
                /**
                 * @return void
                 */
                public function fire(): void
                {
                    $this->fired = true;
                }
            },
        ]);

        $package->configure($config);
        $package->boot($app);

        expect($bindingManager->has(RouterInterface::class))->toBeTrue();
        expect($bindingManager->has(RouteInterface::class))->toBeTrue();
        expect($bindingManager->get(RouterInterface::class))->toBe(Router::class);
        expect($bindingManager->get(RouteInterface::class))->toBe(Route::class);
    });

    test('boot registers router middleware with fixed priority', function () {
        $package = new Package();
        $config = test()->createMock(ConfigInterface::class);
        $config->method('get')->with('http-router')->willReturn([]);

        $app = new PackageTestApp([
            BindingManager::class => new BindingManager(),
            RouteConfigProcessor::class => new class {
                /**
                 * @param RouterInterface $router
                 * @param array<int|string, mixed> $config
                 * @return void
                 */
                public function process(RouterInterface $router, array $config): void
                {
                }
            },
            RouterInterface::class => test()->createMock(RouterInterface::class),
            \Juzdy\Http\Router\Event\RouterInitialized::class => new class {
                /**
                 * @return void
                 */
                public function fire(): void
                {
                }
            },
        ]);

        $package->configure($config);
        $package->boot($app);

        expect($app->middlewareCalls)->toHaveCount(1);
        expect($app->middlewareCalls[0]['priority'])->toBe(PHP_INT_MAX - 200);
        expect($app->middlewareCalls[0]['middleware'])->toBe([RouterInterface::class]);
    });

    test('boot processes configured routes when config is present', function () {
        $package = new Package();

        $httpConfig = [
            '/health' => 'App\\Handler\\HealthHandler',
        ];

        $config = test()->createMock(ConfigInterface::class);
        $config->method('get')->with('http-router')->willReturn($httpConfig);

        $processor = new class {
            public bool $called = false;
            /**
             * @var array<int|string, mixed>
             */
            public array $lastConfig = [];

            /**
             * @param RouterInterface $router
             * @param array<int|string, mixed> $config
             * @return void
             */
            public function process(RouterInterface $router, array $config): void
            {
                $this->called = true;
                $this->lastConfig = $config;
            }
        };

        $app = new PackageTestApp([
            BindingManager::class => new BindingManager(),
            RouteConfigProcessor::class => $processor,
            RouterInterface::class => test()->createMock(RouterInterface::class),
            \Juzdy\Http\Router\Event\RouterInitialized::class => new class {
                /**
                 * @return void
                 */
                public function fire(): void
                {
                }
            },
        ]);

        $package->configure($config);
        $package->boot($app);

        expect($processor->called)->toBeTrue();
        expect($processor->lastConfig)->toBe($httpConfig);
    });
});

/**
 * Fake app implementation for package tests.
 */
final class PackageTestApp implements AppInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $services;

    /**
     * @var array<int, array{priority:int, middleware:array<int, string|MiddlewareInterface>}>
     */
    public array $middlewareCalls = [];

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * @return void
     */
    public function run(): void
    {
    }

    /**
     * @param string $service
     * @return mixed
     */
    public function __invoke(string $service): mixed
    {
        if (!array_key_exists($service, $this->services)) {
            throw new \LogicException("Service '{$service}' was not provided to PackageTestApp.");
        }

        return $this->services[$service];
    }

    /**
     * @param int $priority
     * @param string|MiddlewareInterface ...$middleware
     * @return static
     */
    public function withMiddleware(int $priority, string|MiddlewareInterface ...$middleware): static
    {
        $this->middlewareCalls[] = [
            'priority' => $priority,
            'middleware' => $middleware,
        ];

        return $this;
    }
}
