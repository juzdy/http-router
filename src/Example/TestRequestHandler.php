<?php
namespace Juzdy\Http\Router\Example;

use Psr\Http\Message\ResponseFactoryInterface;

class TestRequestHandler implements \Psr\Http\Server\RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write("Hello from TestRequestHandler!");
        return $response;
    }
}