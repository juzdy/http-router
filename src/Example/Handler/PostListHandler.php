<?php

namespace Juzdy\Http\Router\Example\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PostListHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $posts = [
            ['id' => 1, 'title' => 'First post'],
            ['id' => 2, 'title' => 'Second post'],
        ];

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode($posts));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
