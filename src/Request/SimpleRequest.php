<?php
namespace Juzdy\Http\Router\Request;

use Juzdy\Container\Attribute\Shared;
use Juzdy\Container\Contract\Lifecycle\SharedInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A simple request wrapper that provides convenient access to request data.
 * This class is designed to be shared across the router and its middleware, allowing for consistent access to request data.
 */
class SimpleRequest implements SimpleRequestInterface, SharedInterface
{
    private ?ServerRequestInterface $request = null;

    /**
     * {@inheritDoc}
     */
    public function withServerRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->request->getQueryParams()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request->getParsedBody()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->request->getHeaderLine($key) ?: $default;
    }

    /**
     * {@inheritDoc}
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->request->getCookieParams()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function file(string $key, mixed $default = null): mixed
    {
        return $this->request->getUploadedFiles()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->request->getServerParams()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->request->getAttribute($key, $default);
    }


}