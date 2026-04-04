<?php

namespace Juzdy\Http\Router\Example;

use Juzdy\Config\ConfigInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CallableRequestHandler
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
    ): ResponseInterface
    {
        $response = $responseFactory->createResponse(200);

        $name = $request->getQueryParams()['name'] ?? 'guest';
        $environment = (string) $this->config->get('app.env', 'production');

        $response->getBody()->write(
            sprintf('Hello %s from CallableRequestHandler in %s.', $name, $environment)
        );

        return $response;
    }
}