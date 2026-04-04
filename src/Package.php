<?php

namespace Juzdy\Http\Router;

use Juzdy\App\AppInterface;
use Juzdy\Config\ConfigInterface;
use Juzdy\Container\Binder\BindingManager;
use Juzdy\EventBus\EventDispatcherInterface;
use Juzdy\Http\Router\Event\RouterInitialized;
use Juzdy\Http\Router\Example\CallableRequestHandler;
use Juzdy\Http\Router\Example\Event\TestEvent;
use Juzdy\Http\Router\Example\TestRequestHandler;

class Package extends \Juzdy\App\PackageProvider\Package
{
    /**
     * {@inheritDoc}
     */
    public function configure(ConfigInterface $config): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function boot(AppInterface $app): void
    {
        $this->bindings($app);
        
        //$this->registerRoutesExample($app(RouterInterface::class));

        $app->withMiddleware(PHP_INT_MAX - 200, RouterInterface::class);
        
        $routerInitializedEvent = $app(RouterInitialized::class);
        $routerInitializedEvent->fire();
        
    }

    private function bindings(AppInterface $app): void
    {
        $app(BindingManager::class)
        ->bind(RouterInterface::class, Router::class);
    }

    /**
     * Register routes Example:
     *
     * @param RouterInterface $router The router instance
     */    
    private function registerRoutesExample(RouterInterface $router): void
    {
        /**
         * Basic Route Example:
         */
        $router->get('/_demo_/', function () {
            return 'Hello, World from /_demo_!';
        })
            ->withMiddleware(
                new class implements \Psr\Http\Server\MiddlewareInterface {
                    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
                    {
                        echo "Middleware for route \"_demo_\" executed.\n";
                        return $handler->handle($request)
                            ->withHeader('X-Demo-Middleware', 'This is a demo middleware for /_demo_');
                    }
                }
            )
        ;

        /**
         * Group Route Example:
         */
        $router->group('/_demo_', function (RouterInterface $router) {

            /**
             * Basic Route Example:
             */
            $router->get('/_d1/', function (EventDispatcherInterface $eventDispatcher, TestEvent $testEvent) {
                $eventDispatcher->dispatch($testEvent->with('X-Event', 'Dispatched from /_demo_/_d1_'));
                return 'Hello, World from /_demo_/_d1_!';
            });

            /**
             * Psr-15 Handler Route Example:
             * \Psr\Http\Server\RequestHandlerInterface
             */
            $router->get('/_d2/{id}', TestRequestHandler::class); 


            $router->get('/_d3/', CallableRequestHandler::class)
                
            ;
        })
        ->withMiddleware(
                    new class implements \Psr\Http\Server\MiddlewareInterface {
                        public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
                        {
                            echo "Middleware for group \"_demo_\" executed.\n";
                            return $handler->handle($request)
                                ->withHeader('X-Demo-Middleware', 'This is a demo middleware for /_demo_/_d2_/');
                        }
                    }
                )
        
        ;
    }
}