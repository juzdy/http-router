<?php
namespace Juzdy\Http\Router\Request;

use Psr\Http\Message\ServerRequestInterface;

interface SimpleRequestInterface
{
    /**
     * Creates a new SimpleRequest instance.
     * Use for sharing the server request across the router and its middleware.
     *
     * @param ServerRequestInterface $request The server request instance.
     * @return static
     */
    public function withServerRequest(ServerRequestInterface $request): static;

    /**
     * Gets the underlying server request.
     *
     * @return ServerRequestInterface The server request instance.
     */
    public function getRequest(): ServerRequestInterface;

    /**
     * Gets a query parameter from the request.
     *
     * @param string $key The parameter key.
     * @param mixed $default The default value to return if the parameter is not found.
     * 
     * @return mixed The parameter value or the default value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Gets a POST parameter from the request.
     *
     * @param string $key The parameter key.
     * @param mixed $default The default value to return if the parameter is not found.
     * 
     * @return mixed The parameter value or the default value.
     */
    public function post(string $key, mixed $default = null): mixed;

    /**
     * Gets a header value from the request.
     *
     * @param string $key The header key.
     * @param mixed $default The default value to return if the header is not found.
     * 
     * @return mixed The header value or the default value.
     */
    public function header(string $key, mixed $default = null): mixed;

    /**
     * Gets a cookie value from the request.
     *
     * @param string $key The cookie key.
     * @param mixed $default The default value to return if the cookie is not found.
     * 
     * @return mixed The cookie value or the default value.
     */
    public function cookie(string $key, mixed $default = null): mixed;

    /**
     * Gets an uploaded file from the request.
     *
     * @param string $key The file key.
     * @param mixed $default The default value to return if the file is not found.
     * @return mixed The file or the default value.
     */
    public function file(string $key, mixed $default = null): mixed;

    /**
     * Gets a server parameter from the request.
     *
     * @param string $key The server parameter key.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The parameter value or the default value.
     */

    public function server(string $key, mixed $default = null): mixed;

    /**
     * Gets an attribute from the request.
     *
     * @param string $key The attribute key.
     * @param mixed $default The default value to return if the attribute is not found.
     * @return mixed The attribute value or the default value.
     */
    public function attribute(string $key, mixed $default = null): mixed;
}