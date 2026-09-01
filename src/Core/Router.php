<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array<int,string>, handler:callable|array, roles:array<int,string>}> */
    private array $routes = [];
    private array $groupRoles = [];

    public function group(array $roles, callable $definition): void
    {
        $previous         = $this->groupRoles;
        $this->groupRoles = $roles;
        $definition($this);
        $this->groupRoles = $previous;
    }

    public function get(string $pattern, array|callable $handler, array $roles = []): void
    {
        $this->add('GET', $pattern, $handler, $roles);
    }

    public function post(string $pattern, array|callable $handler, array $roles = []): void
    {
        $this->add('POST', $pattern, $handler, $roles);
    }

    private function add(string $method, string $pattern, array|callable $handler, array $roles): void
    {
        $keys  = [];
        $regex = preg_replace_callback('#\{([a-z_]+)\}#', function (array $m) use (&$keys): string {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
            'roles'   => $roles ?: $this->groupRoles,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            array_shift($matches);
            foreach ($route['keys'] as $i => $key) {
                $request->params[$key] = $matches[$i];
            }

            // 1. cancello autenticazione + ruolo
            if ($route['roles'] !== []) {
                Auth::requireRole($route['roles'], $request);
            }

            // 2. CSRF su ogni scrittura
            if ($request->method === 'POST' && !Csrf::check((string) $request->input('_csrf'))) {
                if ($request->wantsJson()) {
                    Response::json(['error' => 'Sessione scaduta, ricarica la pagina.'], 419);
                }
                Response::abort(419, 'Sessione scaduta. Ricarica la pagina e riprova.');
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                (new $class())->{$method}($request);
                return;
            }
            $handler($request);
            return;
        }

        Response::abort(404, 'La pagina cercata non esiste.');
    }
}
