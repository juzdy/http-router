<?php
/**
 ▄▄▄
  █ J █ u z d y
   ▀▀▀
 */
namespace Juzdy\Http\Router\Attribute;

use Attribute;
use Traversable;
use Psr\Http\Server\MiddlewareInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class WithMiddleware
{
    
    public function __construct(
        private array $middleware
    ) {}

    public function getMiddleware(): Traversable
    {
        foreach ($this->middleware as $middleware) {
            if (!is_string($middleware) && !is_a($middleware, MiddlewareInterface::class, true)) {
                throw new \InvalidArgumentException("Middleware must be a class name or an instance of MiddlewareInterface");
            }
            yield $middleware;
        }
    }
}