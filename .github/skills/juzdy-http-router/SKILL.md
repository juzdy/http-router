---
name: juzdy-http-router
description: "Use when working on the juzdy/http-router package: adding routes or HTTP methods, updating RouterInterface/Router, route middleware pipeline behavior, handler invocation patterns (RequestHandlerInterface, closures, invokable classes), config-based route registration, and mandatory README/API docs alignment."
---

# JUZDY HTTP Router Skill

## Purpose

This skill provides package-specific rules for safe, consistent changes in `juzdy/http-router`.

Use it when implementing or refactoring:
- router public API (`RouterInterface`, `Router`)
- route creation and dispatch flow
- route/group/router middleware behavior
- handler resolution and invocation
- package documentation that must match runtime behavior

## Source Of Truth

Treat these files as authoritative:
- `src/RouterInterface.php`
- `src/Router.php`
- `src/Config/RouteConfigProcessor.php`
- `src/Route/RouteInterface.php`
- `src/Route/Route.php`
- `src/Route/RouteFactory.php`
- `src/Route/HandlerFactory.php`
- `src/Route/MiddlewareFactory.php`
- `etc/config/config.php`
- `README.md`

When behavior and docs differ, update docs to match code unless explicitly asked to change runtime behavior.

## Router API Conventions

### Supported route helper methods

The router exposes these verb helpers:
- `get()`
- `post()`
- `put()`
- `patch()`
- `delete()`
- `head()`
- `options()`

If adding a new helper, update all of:
- `RouterInterface`
- `Router` implementation
- README API sections and examples

### Route registration flow

All helper methods must delegate to `createRoute(<METHOD>, $path, $handler)`.

`createRoute()` responsibilities:
- normalize method to uppercase
- apply router prefix to path
- prevent duplicate route registration per method + path
- create route via `RouteFactory`
- apply router middleware to the route

## Handler Model

Accepted route handler types:
- `RequestHandlerInterface` instance/class
- callable (closure/function/invokable)
- string resolved by container (commonly an invokable class)

Invocation behavior:
- string handlers are resolved from container via `HandlerFactory`
- callable handlers are invoked via `DiInvoker` (method-level DI)
- request handlers are executed through `handle(ServerRequestInterface)`

### Documentation rule

Do not document handler examples using PHP globals (`$_GET`, `$_POST`, etc.).
Use `ServerRequestInterface` and injected dependencies.

## Route Parameters And Request Data

Current package behavior:
- dynamic route params are extracted from path placeholders
- params are merged into request query params
- handlers access them through `$request->getQueryParams()`

Do not claim that router writes to globals.

## Middleware Behavior

Middleware can be applied at:
- router level
- route group level
- route level

When changing middleware logic:
- preserve PSR-15 contracts
- keep middleware type checks strict (`MiddlewareInterface` or container-resolved string)
- ensure returned values are valid `ResponseInterface` (or explicitly normalized if allowed)

## Change Checklist

For any API or behavior change in this package:
1. update interface(s) first
2. update implementation(s)
3. update README examples and API listing
4. run error diagnostics on changed files
5. avoid introducing global state assumptions

## Documentation Synchronization Rule

For every code change in this package, treat README updates as required unless the change is purely internal and non-behavioral.

Always verify and update:
- public API sections (verb helpers, handler types, middleware behavior)
- config-driven route registration (`http-router` structure)
- examples that reference changed behavior or signatures

## Editing Guardrails

- Follow PSR-1/PSR-12 style.
- Prefer minimal patches over broad rewrites.
- Keep public API changes backward-compatible unless requested.
- If examples include container-based handlers, make dependency injection explicit and realistic.

## Quick Validation

After edits, validate:
- no diagnostics in changed PHP files
- method signatures match interface contracts
- README snippets match actual runtime behavior
