<?php

namespace Juzdy\Http\Router;

use Juzdy\App\AppInterface;
use Juzdy\Config\ConfigInterface;
use Juzdy\Container\Binder\BindingManager;
use Juzdy\Http\Router\Config\RouteConfigProcessor;
use Juzdy\Http\Router\Event\RouterInitialized;
use Juzdy\Http\Router\Route\Route;
use Juzdy\Http\Router\Route\RouteInterface;

class Package extends \Juzdy\App\PackageProvider\Package
{
    private const CONFIG_KEY = 'http-router';

    private ?ConfigInterface $config = null;

    /**
     * {@inheritDoc}
     */
    public function configure(ConfigInterface $config): void
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function boot(AppInterface $app): void
    {
        $this->bindings($app);

        $this->registerConfiguredRoutes($app);

        $this->registerExampleRoutes($app);

        $app->withMiddleware(PHP_INT_MAX - 200, RouterInterface::class);

        $app(RouterInitialized::class)->fire();
    }

    /**
     * Registers bindings for the router package.
     *
     * @param AppInterface $app The application instance.
     */
    private function bindings(AppInterface $app): void
    {
        $app(BindingManager::class)
            ->bindMany([
                RouterInterface::class => Router::class,
                RouteInterface::class => Route::class
            ]);
    }

    /**
     * Registers routes defined in the configuration.
     *
     * @param AppInterface $app The application instance.
     */
    private function registerConfiguredRoutes(AppInterface $app): void
    {
        $config = (array) ($this->config?->get(self::CONFIG_KEY) ?? []);

        if (empty($config)) {
            return;
        }

        $app(RouteConfigProcessor::class)->process($app(RouterInterface::class), $config);
    }

    /**
     * Registers example routes for demonstration purposes.
     *
     * @param AppInterface $app The application instance.
     */
    private function registerExampleRoutes(AppInterface $app): void
    {
        // $router = $app(RouterInterface::class);
        // $router->get('/_example/hello', function () {
        //     return 'Hello, world!';
        // });

        // $router->group('/_example/api', function (RouterInterface $router) {
        //     $router->get('/hello', function () {
        //         return 'Hello from API!';
        //     });
        // });
    }
}
