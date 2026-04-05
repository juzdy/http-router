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
final class PostDetailHandler implements HasAttributes//implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? 'unknown';
        //return "OKKKKKK: " . $id;

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode(['id' => $id, 'title' => "Post {$id}"]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
