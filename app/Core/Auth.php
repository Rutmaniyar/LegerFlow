<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id || !app()->isInstalled()) {
            return null;
        }

        return app()->db()->fetch(
            'SELECT users.*, roles.name AS role_name, roles.permissions
             FROM users INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = ? AND users.deleted_at IS NULL',
            [$id]
        );
    }

    public static function id(): ?int
    {
        return Session::get('user_id') ? (int) Session::get('user_id') : null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = app()->db()->fetch(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [mb_strtolower(trim($email))]
        );

        if (!$user
            || !password_verify($password, $user['password_hash'])
            || (int) $user['is_active'] !== 1
            || $user['email_verified_at'] === null) {
            return false;
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('_login_at', time());
        Session::put('_last_activity', time());
        app()->db()->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Response::redirect('/login');
        }

        $now = time();
        $idleLimit = (int) config('security.session_idle_minutes', 120) * 60;
        $absoluteLimit = (int) config('security.session_absolute_minutes', 720) * 60;
        $lastActivity = (int) Session::get('_last_activity', $now);
        $loginAt = (int) Session::get('_login_at', $now);

        if (($now - $lastActivity) > $idleLimit || ($now - $loginAt) > $absoluteLimit) {
            self::logout();
            Response::redirect('/login');
        }

        Session::put('_last_activity', $now);
    }

    public static function redirectIfAuthenticated(): void
    {
        if (self::check()) {
            Response::redirect('/dashboard');
        }
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        $permissions = json_decode((string) $user['permissions'], true) ?: [];
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        if (str_ends_with($permission, '.view')) {
            return in_array(str_replace('.view', '.manage', $permission), $permissions, true);
        }

        return false;
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            exit('You do not have permission to access this resource.');
        }
    }
}
