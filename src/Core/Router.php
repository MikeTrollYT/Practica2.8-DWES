<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador genérico: no conoce ningún controlador concreto.
 * Se le registran patrones como "/posts/delete/{id}" asociados a un método de un controlador.
 */
class Router
{
    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Convierte el patrón en una expresión regular y guarda la ruta.
     */
    private function add(string $method, string $pattern, callable $handler): void
    {
        // Cada {nombre} se transforma en un grupo con nombre que solo acepta dígitos:
        // "/posts/delete/{id}" -> "#^/posts/delete/(?P<id>\d+)$#"
        // Aceptar solo dígitos garantiza que el id es un número (y /posts/create no se confunde con un id).
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern);

        $this->routes[] = [
            'method'  => $method,
            // ^ y $ obligan a que coincida la URL ENTERA, no solo una parte.
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * Busca la ruta que encaja con método + URL y ejecuta su controlador.
     */
    public function dispatch(string $httpMethod, string $uri): void
    {
        // parse_url con PHP_URL_PATH descarta "?search=..." para quedarnos solo con la ruta.
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // "/posts/" y "/posts" se tratan igual; la raíz se queda como "/".
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $httpMethod) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                // $matches trae claves numéricas y las de los grupos con nombre ("id").
                // Nos quedamos solo con las de nombre y las convertimos a entero.
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = (int) $value;
                    }
                }

                // Las claves de texto se usan como argumentos con nombre:
                // ['id' => 3] equivale a llamar a ->destroy(id: 3).
                ($route['handler'])(...$params);
                return;
            }
        }

        $this->notFound();
    }

    /** Ninguna ruta encaja: página de error 404. */
    private function notFound(): void
    {
        http_response_code(404);
        $code = 404;
        $message = 'La página que buscas no existe.';
        require __DIR__ . '/../Views/error.php';
    }
}