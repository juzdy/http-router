<?php

namespace Juzdy\Http\Router\Example\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Invokable callable class example.
 * Resolved by the container (constructor DI), then invoked via DiInvoker (method DI).
 */
final class ProfileHandler
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
    ): ResponseInterface {
        $user = ['id' => 1, 'name' => 'Example User'];

        $response = $responseFactory->createResponse(200);
        $response->getBody()->write(json_encode($user));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
