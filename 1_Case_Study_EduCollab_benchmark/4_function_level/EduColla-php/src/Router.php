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

//        http_response_code(404);
//        echo "Not Found: " . $path;   // 泄露内部路由/路径
//        print_r($_SERVER);            // 泄露服务器环境、绝对路径、header
    }
}

