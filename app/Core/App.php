<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    private static ?self $instance = null;

    private Router $router;
    private ?Database $database = null;

    public function __construct(private array $config)
    {
        self::$instance = $this;
        $this->router = new Router();
    }

    public static function instance(): self
    {
        if (!self::$instance) {
            throw new \RuntimeException('Application has not been bootstrapped.');
        }

        return self::$instance;
    }

    public function bootstrap(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', ($this->config['app']['debug'] ?? false) ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

        $this->sendSecurityHeaders();
        Session::start($this->config);
    }

    private function sendSecurityHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        // 'unsafe-inline' is required because views use inline event handlers/styles;
        // this still blocks framing, third-party script/object injection, and base-uri tampering.
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
            . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public function config(): array
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function db(): Database
    {
        if (!$this->database) {
            $this->database = new Database($this->config['database']);
        }

        return $this->database;
    }

    public function isInstalled(): bool
    {
        return (bool) ($this->config['installed'] ?? false) && is_file(STORAGE_PATH . '/installed.lock');
    }
}
