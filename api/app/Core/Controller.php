<?php

namespace App\Core;

class Controller
{
    public function db($db = 0)
    {
        return DB::getInstance($db);
    }

    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $this->setCorsHeaders();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($data);
        exit;
    }

    protected function success($data = null, $message = 'Success')
    {
        $this->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function error($message = 'Error', $status = 400, $data = null)
    {
        $this->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function getBody()
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }

    protected function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    protected function isPost()
    {
        return $this->method() === 'POST';
    }

    protected function isGet()
    {
        return $this->method() === 'GET';
    }

    protected function handleCors()
    {
        if ($this->method() === 'OPTIONS') {
            $this->setCorsHeaders();
            http_response_code(200);
            exit;
        }
    }

    protected function setCorsHeaders()
    {
        $allowedOrigin = $this->resolveAllowedOrigin();

        if ($allowedOrigin) {
            header("Access-Control-Allow-Origin: {$allowedOrigin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, User-Agent');
        }
    }

    private function resolveAllowedOrigin(): ?string
    {
        if (!isset($_SERVER['HTTP_ORIGIN'])) {
            return null;
        }

        $origin = $_SERVER['HTTP_ORIGIN'];
        $allowedOrigins = defined('Env::ALLOWED_ORIGINS') ? \Env::ALLOWED_ORIGINS : [];
        $parsedOrigin = parse_url($origin);
        $originHost = strtolower($parsedOrigin['host'] ?? '');
        $originPort = isset($parsedOrigin['port']) ? ':' . $parsedOrigin['port'] : '';
        $originBase = ($parsedOrigin['scheme'] ?? 'http') . '://' . $originHost . $originPort;

        foreach ($allowedOrigins as $allowed) {
            $parsedAllowed = parse_url($allowed);
            $allowedHost = strtolower($parsedAllowed['host'] ?? '');
            $allowedPort = isset($parsedAllowed['port']) ? ':' . $parsedAllowed['port'] : '';
            $allowedBase = ($parsedAllowed['scheme'] ?? 'http') . '://' . $allowedHost . $allowedPort;

            if ($originBase === $allowedBase) {
                return $origin;
            }
        }

        if (\Env::isDev() && in_array($originHost, ['localhost', '127.0.0.1'], true)) {
            return $origin;
        }

        return null;
    }

    protected function query($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    protected function validate($data, $required = [])
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing required fields: ' . implode(', ', $missing), 400);
        }

        return true;
    }

    protected function requireAdmin(): array
    {
        if (!\App\Helpers\Auth::check()) {
            $this->error('Unauthorized', 401);
        }

        return \App\Helpers\Auth::user();
    }
}
