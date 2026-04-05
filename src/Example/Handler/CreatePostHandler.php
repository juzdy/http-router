<?php

namespace Juzdy\Http\Router\Example\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CreatePostHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(201);
        $response->getBody()->write(json_encode(['id' => 3, 'title' => 'New post']));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
