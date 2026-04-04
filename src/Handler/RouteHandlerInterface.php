<?php

namespace Juzdy\Http\Router\Route\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RouteHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}