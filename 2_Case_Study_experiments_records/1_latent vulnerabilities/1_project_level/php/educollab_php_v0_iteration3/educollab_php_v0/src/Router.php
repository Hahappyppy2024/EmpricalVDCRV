<?php

class Router {
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void {
        $method = strtoupper($method);
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function dispatch(string $method, string $path): void {
        foreach ($this->routes as [$m, $pat, $h]) {
            if ($m !== $method) continue;
            $regex = '#^' . $pat . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_int($k)) $params[$k] = $v;
                }
                call_user_func($h, $params);
                return;
            }
        }
        http_response_code(404);
        echo "Not Found";
    }
}

