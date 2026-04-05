<?php

namespace Juzdy\Http\Router\Example\Handler;

use Juzdy\Http\Router\Attribute\WithMiddleware;
use Juzdy\Http\Router\Contract\HasAttributes;
use Juzdy\Http\Router\Example\Middleware\LogMiddleware;
use Juzdy\Http\Router\Request\SimpleRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

#[WithMiddleware([LogMiddleware::class])]
final class PostDetailHandler implements HasAttributes//implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function __invoke(SimpleRequestInterface $request): ResponseInterface
    {
        //\Juzdy\Debug\Debug::dd($request);
        $id = $request->get('id', 'unknown');

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode(['id' => $id, 'title' => "Post {$id}"]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
