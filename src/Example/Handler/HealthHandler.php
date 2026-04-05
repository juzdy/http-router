<?php

namespace Juzdy\Http\Router\Example\Handler;

use Juzdy\Http\Router\Attribute\WithMiddleware;
use Juzdy\Http\Router\Contract\HasAttributes;
use Juzdy\Http\Router\Example\Middleware\LogMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[WithMiddleware([LogMiddleware::class])]
final class HealthHandler implements RequestHandlerInterface, HasAttributes
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode(['status' => 'ok']));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
