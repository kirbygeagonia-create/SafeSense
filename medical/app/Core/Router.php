<?php

class Router {
    private $routes = [];
    private $basePath;

    public function __construct($basePath = '') {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function add($route, $target, $method = 'GET') {
        $this->routes[] = [
            'route' => $this->basePath . $route,
            'target' => $target,
            'method' => strtoupper($method),
            'params' => []
        ];
    }

    public function get($route, $target) {
        return $this->add($route, $target, 'GET');
    }

    public function post($route, $target) {
        return $this->add($route, $target, 'POST');
    }

    public function put($route, $target) {
        return $this->add($route, $target, 'PUT');
    }

    public function delete($route, $target) {
        return $this->add($route, $target, 'DELETE');
    }

    public function match($requestUri, $requestMethod) {
        $requestMethod = strtoupper($requestMethod);

        foreach ($this->routes as $route) {
            // Check method
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            // Check route pattern (use (.+) for catch-all wildcards)
            $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>.+)', preg_quote($route['route'], '#')) . '$#';
            
            if (preg_match($pattern, $requestUri, $matches)) {
                // Extract parameters
                array_shift($matches); // Remove full match
                $route['params'] = $matches;
                return $route;
            }
        }

        return null; // No match found
    }

    public function getRoutes() {
        return $this->routes;
    }
}