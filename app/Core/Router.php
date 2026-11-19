<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $currentGroupMiddlewares = [];
    private string $currentGroupPrefix = '';

    public function get(string $path, string|array|callable $handler, array $middlewares = []): self
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
        return $this;
    }

    public function post(string $path, string|array|callable $handler, array $middlewares = []): self
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
        return $this;
    }

    public function put(string $path, string|array|callable $handler, array $middlewares = []): self
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
        return $this;
    }

    public function delete(string $path, string|array|callable $handler, array $middlewares = []): self
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddlewares = $this->currentGroupMiddlewares;

        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middlewares = (array) $attributes['middleware'];
            $this->currentGroupMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);
        }

        $callback($this);

        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddlewares = $previousMiddlewares;
    }

    private function addRoute(string $method, string $path, string|array|callable $handler, array $middlewares = []): void
    {
        $fullPath = rtrim($this->currentGroupPrefix, '/') . '/' . ltrim($path, '/');
        // Request::parseUri removes trailing slashes; group index routes must
        // use the same canonical path or /api/admin/staff can never match.
        $fullPath = rtrim($fullPath, '/') ?: '/';

        $allMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);

        // Convert {param} to regex named group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];
    }

    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->method();
        // Support method spoofing for HTML forms
        if ($method === 'POST' && $request->input('_method')) {
            $method = strtoupper((string) $request->input('_method'));
        }

        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                // Execute Middlewares
                foreach ($route['middlewares'] as $middlewareDefinition) {
                    $middleware = is_object($middlewareDefinition)
                        ? $middlewareDefinition
                        : (is_string($middlewareDefinition) && class_exists($middlewareDefinition)
                            ? new $middlewareDefinition()
                            : null);

                    if ($middleware && method_exists($middleware, 'handle')) {
                        $handled = $middleware->handle($request, $response);
                        if ($handled === false) {
                            return; // Middleware stopped request
                        }
                    }
                }

                // Execute Handler
                $handler = $route['handler'];

                if (is_callable($handler)) {
                    call_user_func_array($handler, array_merge([$request, $response], array_values($params)));
                    return;
                }

                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class($request, $response);
                    call_user_func_array([$controller, $action], array_values($params));
                    return;
                }

                if (is_string($handler) && str_contains($handler, '@')) {
                    [$class, $action] = explode('@', $handler);
                    $fullClass = "App\\Controllers\\" . $class;
                    if (class_exists($fullClass)) {
                        $controller = new $fullClass($request, $response);
                        call_user_func_array([$controller, $action], array_values($params));
                        return;
                    }
                }
            }
        }

        // API errors must be JSON regardless of the client Accept/X-Requested-With
        // headers. Never attempt to render an HTML 404 for an API route: a missing
        // HTML layout can otherwise turn a valid 404 into a misleading HTTP 500.
        if ($uri === '/api' || str_starts_with($uri, '/api/')) {
            $response->header('Cache-Control', 'no-store');
            $response->json(['success' => false, 'message' => '404 ไม่พบเส้นทาง API'], 404);
            return;
        }

        // Keep the existing HTML 404 experience for non-API visitors, but fail
        // safely if the error template itself cannot be rendered.
        $response->setStatusCode(404);
        if ($request->isAjax()) {
            $response->json(['success' => false, 'message' => '404 ไม่พบหน้าที่ต้องการ'], 404);
            return;
        }
        try {
            $html = View::render('public.404', ['title' => '404 - ไม่พบหน้าเว็บ'], 'layouts.public');
        } catch (\Throwable $e) {
            $html = '<!doctype html><html lang="th"><meta charset="utf-8"><title>404 Not Found</title><body><h1>404 Not Found</h1></body></html>';
        }
        $response->setContent($html)->send();
    }
}
