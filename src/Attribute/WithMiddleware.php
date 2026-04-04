<?php
/**
 ▄▄▄
  █ J █ u z d y
   ▀▀▀
 */
namespace Juzdy\Http\Router\Attribute;

use Attribute;
use Juzdy\Container\Attribute\AttributeApplicableInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class WithMiddleware implements AttributeApplicableInterface
{
    /**
     * @param string|array $middleware The middleware or middlewares to apply to the route
     * @param int $priority The priority of the middleware (lower numbers run first)
     */
    public function __construct(
        public string|array $middleware,
        public int $priority = 0
    ) {}

    public function apply(object $instance): void
    {
        if (method_exists($instance, 'withMiddleware')) {
            $instance->withMiddleware($this->priority, ...(is_array($this->middleware) ? $this->middleware : [$this->middleware]));
        }
    }
}