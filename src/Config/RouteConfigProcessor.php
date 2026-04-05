<?php

namespace Juzdy\Http\Router\Config;

use Juzdy\Http\Router\RouterInterface;
use Juzdy\Http\Router\Exception\RuntimeException;

/**
 * Processes the 'http-router' configuration tree and registers routes and groups
 * on a RouterInterface instance.
 *
 * Config structure rules:
 *   - Path keys start with '/'  → route node (may contain verbs and/or child paths)
 *   - 'middleware' key          → middleware list for the current scope
 *   - HTTP verb keys            → route definition for that method at the current path (case-insensitive)
 *
 * Verb value forms:
 *   'GET' => Handler::class                                // simple
 *   'POST' => ['handler' => Handler::class, 'middleware' => [M::class]]  // with per-method middleware
 *
 * Shorthand (GET-only path):
 *   '/path' => Handler::class
 *
 * Middleware inheritance:
 *   group middleware → path (node) middleware → method middleware
 */
class RouteConfigProcessor
{
    private const HTTP_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    /**
     * Entry point. Walks the top-level 'http-router' config array.
     *
     * @param RouterInterface $router The router to register routes on.
     * @param array           $config The value of the 'http-router' config key.
     */
    public function process(RouterInterface $router, array $config): void
    {
        foreach ($config as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, '/')) {
                continue;
            }
            $this->processNode($router, $key, $value);
        }
    }

    /**
     * Process a single path node.
     *
     * @param RouterInterface    $router     The router (or group router) to register on.
     * @param string             $path       The path for this node (relative to current router prefix).
     * @param mixed              $nodeConfig Handler class string or array node definition.
     */
    private function processNode(RouterInterface $router, string $path, mixed $nodeConfig): void
    {
        // Shorthand: '/path' => Handler::class  →  GET /path
        if (is_string($nodeConfig)) {
            $router->get($path, $nodeConfig);
            return;
        }

        if (!is_array($nodeConfig)) {
            throw new RuntimeException(
                "Route config for path '{$path}' must be a handler class string or an array."
            );
        }

        $nodeMiddleware = $nodeConfig['middleware'] ?? [];

        $verbKeyMap = [];
        foreach (array_keys($nodeConfig) as $key) {
            if (!is_string($key)) {
                continue;
            }

            $normalized = strtolower($key);
            if (in_array($normalized, self::HTTP_VERBS, true)) {
                $verbKeyMap[$normalized] = $key;
            }
        }

        $childPaths = array_filter(
            array_keys($nodeConfig),
            static fn (string $k) => str_starts_with($k, '/')
        );

        // Register verb routes directly on the current router.
        foreach ($verbKeyMap as $verb => $originalKey) {
            $this->registerVerb($router, $verb, $path, $nodeConfig[$originalKey], $nodeMiddleware);
        }

        // Child paths form a group, inheriting node middleware.
        if (!empty($childPaths)) {
            $router->group(
                $path,
                function (RouterInterface $groupRouter) use ($nodeConfig, $childPaths): void {
                    foreach ($childPaths as $childPath) {
                        $this->processNode($groupRouter, $childPath, $nodeConfig[$childPath]);
                    }
                },
                ...$nodeMiddleware,
            );
        }
    }

    /**
     * Register one HTTP verb on a router path.
     *
     * @param RouterInterface $router         Current router instance.
     * @param string          $verb           Lowercase HTTP method (get, post, …).
     * @param string          $path           Route path.
     * @param mixed           $verbConfig     Handler string or ['handler'=>…, 'middleware'=>[…]].
     * @param array           $nodeMiddleware Middleware inherited from the path node.
     */
    private function registerVerb(
        RouterInterface $router,
        string $verb,
        string $path,
        mixed $verbConfig,
        array $nodeMiddleware,
    ): void {
        [$handler, $verbMiddleware] = $this->parseVerbConfig($verbConfig, $path, $verb);

        $route = $router->{$verb}($path, $handler);

        $middleware = array_merge($nodeMiddleware, $verbMiddleware);
        if (!empty($middleware)) {
            $route->withMiddleware(...$middleware);
        }
    }

    /**
     * Normalize a verb config value to [handler, middleware[]].
     *
     * @return array{0: string, 1: array<string>}
     */
    private function parseVerbConfig(mixed $verbConfig, string $path, string $verb): array
    {
        if (is_string($verbConfig)) {
            return [$verbConfig, []];
        }

        if (is_array($verbConfig)) {
            if (!isset($verbConfig['handler'])) {
                throw new RuntimeException(
                    "Route config for {$verb} '{$path}' must include a 'handler' key when specified as array."
                );
            }
            return [$verbConfig['handler'], $verbConfig['middleware'] ?? []];
        }

        throw new RuntimeException(
            "Route config for {$verb} '{$path}' must be a handler class string or an array with a 'handler' key."
        );
    }
}
