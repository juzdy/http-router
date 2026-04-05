<?php

namespace Juzdy\Http\Router\Example\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UpdatePostHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? 'unknown';

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode(['id' => $id, 'title' => "Updated post {$id}"]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
