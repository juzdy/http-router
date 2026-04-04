<?php
namespace Juzdy\Http\Router\Route\Handler;

use Juzdy\Container\DiInvoker;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class RouteHandler implements RouteHandlerInterface
{
    private $handler;
    public function __construct(
        private DiInvoker $invoker,
    ) 
    {}

    public function withHandler(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->invoker)($this->handler);
    }
}