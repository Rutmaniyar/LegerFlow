<?php

declare(strict_types=1);

namespace App\Services;

final class UpdateService
{
    private const REPO = 'Rutmaniyar/LegerFlow';
    private const API_URL = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
    private const TRUSTED_PREFIXES = [
        'https://github.com/' . self::REPO . '/',
        'https://api.github.com/repos/' . self::REPO . '/',
    ];

    public function currentVersion(): string
    {
        $file = ROOT_PATH . '/VERSION';
        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    public function fetchLatestRelease(): array
    {
        $response = $this->httpGet(self::API_URL, "Accept: application/vnd.github+json\r\n");

        try {
            $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            error_log('LedgerFlow update check: unexpected response - ' . $exception->getMessage());
            throw new \RuntimeException('The update server returned an unexpected response. Try again later.');
        }

        $downloadUrl = null;
        foreach ($data['assets'] ?? [] as $asset) {
            if (str_ends_with((string) $asset['name'], '.zip')) {
                $downloadUrl = (string) $asset['browser_download_url'];
                break;
            }
        }
        $downloadUrl ??= (string) ($data['zipball_url'] ?? '');

        return [
            'version' => ltrim((string) ($data['tag_name'] ?? ''), 'v'),
            'name' => (string) ($data['name'] ?? $data['tag_name'] ?? ''),
            'notes' => (string) ($data['body'] ?? ''),
            'html_url' => (string) ($data['html_url'] ?? ''),
            'download_url' => $downloadUrl,
        ];
    }

    public function isNewer(string $latestVersion): bool
    {
        return $latestVersion !== '' && version_compare($latestVersion, $this->currentVersion(), '>');
    }

    public function applyUpdate(string $downloadUrl): string
    {
        $trusted = false;
        foreach (self::TRUSTED_PREFIXES as $prefix) {
            if (str_starts_with($downloadUrl, $prefix)) {
                $trusted = true;
                break;
            }
        }
        if (!$trusted) {
            throw new \RuntimeException('Refusing to install an update from an untrusted source.');
        }

        if (!extension_loaded('zip')) {
            throw new \RuntimeException('The PHP zip extension is required to apply updates.');
        }

        if (!is_writable(ROOT_PATH)) {
            throw new \RuntimeException('The application directory is not writable by the web server. Fix file/folder permissions and try again.');
        }

        @set_time_limit(0);

        $tmpZip = sys_get_temp_dir() . '/ledgerflow-update-' . bin2hex(random_bytes(8)) . '.zip';

        try {
            $content = $this->httpGet($downloadUrl, '');
            if (strlen($content) < 1024) {
                throw new \RuntimeException('The downloaded update package looks incomplete or corrupted. Try again later.');
            }
            file_put_contents($tmpZip, $content, LOCK_EX);

            $backupPath = $this->backupCurrentInstallation();

            try {
                $this->extractOver($tmpZip);
                (new InstallerService())->runMigrations(app()->db());
            } catch (\Throwable $exception) {
                $this->restoreFromBackup($backupPath);
                throw new \RuntimeException(
                    'Update failed and the previous version was restored: ' . $exception->getMessage(),
                    previous: $exception
                );
            }

            return $backupPath;
        } finally {
            if (is_file($tmpZip)) {
                unlink($tmpZip);
            }
        }
    }

    private function restoreFromBackup(string $backupPath): void
    {
        if (!is_file($backupPath)) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($backupPath) === true) {
            $zip->extractTo(ROOT_PATH);
            $zip->close();
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function httpGet(string $url, string $extraHeaders): string
    {
        $body = extension_loaded('curl')
            ? $this->httpGetViaCurlExtension($url, $extraHeaders)
            : $this->httpGetWithStreams($url, $extraHeaders);

        if ($body === null) {
            throw new \RuntimeException('Could not reach the update server. Check your internet connection and try again later.');
        }

        return $body;
    }

    /** Uses PHP's bundled libcurl HTTP client extension (ext-curl) - not a shell call. */
    private function httpGetViaCurlExtension(string $url, string $extraHeaders): ?string
    {
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'LedgerFlow-Updater',
            CURLOPT_HTTPHEADER => array_filter(explode("\r\n", trim($extraHeaders))),
        ];
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $status >= 400) {
            error_log("LedgerFlow update check: HTTP request to {$url} failed (status {$status}): {$error}");
            return null;
        }

        return $body;
    }

    private function httpGetWithStreams(string $url, string $extraHeaders): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: LedgerFlow-Updater\r\n" . $extraHeaders,
                'timeout' => 180,
                'follow_location' => 1,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $error = error_get_last()['message'] ?? 'unknown error';
            error_log("LedgerFlow update check: HTTP request to {$url} failed: {$error}");
            return null;
        }

        return $body;
    }

    private function backupCurrentInstallation(): string
    {
        $backupDir = STORAGE_PATH . '/backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Could not create the backup directory. Check that storage/backups is writable.');
        }

        $backupPath = $backupDir . '/pre-update-' . $this->currentVersion() . '-' . date('Ymd-His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create a backup of the current installation. Refusing to update without one - check that storage/backups is writable.');
        }

        foreach ($this->walk(ROOT_PATH) as $relative => $absolute) {
            if (!is_dir($absolute)) {
                $zip->addFile($absolute, $relative);
            }
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Could not finalize the backup archive. Refusing to update without a valid backup.');
        }

        if (!is_file($backupPath) || filesize($backupPath) === 0) {
            throw new \RuntimeException('The backup archive was not written correctly. Refusing to update without a valid backup.');
        }

        return $backupPath;
    }

    private function extractOver(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open the downloaded update package.');
        }

        $extractTo = STORAGE_PATH . '/cache/update-' . bin2hex(random_bytes(6));
        if (!mkdir($extractTo, 0755, true) && !is_dir($extractTo)) {
            $zip->close();
            throw new \RuntimeException('Could not create a temporary directory to unpack the update.');
        }

        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            $this->removeDirectory($extractTo);
            throw new \RuntimeException('Could not unpack the downloaded update package.');
        }
        $zip->close();

        $entries = array_values(array_diff(scandir($extractTo) ?: [], ['.', '..']));
        $sourceRoot = (count($entries) === 1 && is_dir($extractTo . '/' . $entries[0]))
            ? $extractTo . '/' . $entries[0]
            : $extractTo;

        $filesWritten = 0;

        try {
            foreach ($this->walk($sourceRoot) as $relative => $absolute) {
                $target = ROOT_PATH . '/' . $relative;
                if (is_dir($absolute)) {
                    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                        throw new \RuntimeException("Could not create the '{$relative}' directory. Check file permissions for the web server user.");
                    }
                    continue;
                }

                $targetDir = dirname($target);
                if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    throw new \RuntimeException("Could not create the directory for '{$relative}'. Check file permissions for the web server user.");
                }

                if (!copy($absolute, $target)) {
                    throw new \RuntimeException("Could not write '{$relative}'. Check that the web server user owns and can write to this file/directory.");
                }

                $filesWritten++;
            }
        } finally {
            $this->removeDirectory($extractTo);
        }

        if ($filesWritten === 0) {
            throw new \RuntimeException('The update package did not contain any files to install.');
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** @return \Generator<string, string> relative path => absolute path, skipping excluded data/tooling directories */
    private function walk(string $root): \Generator
    {
        $excluded = [
            'storage/cache', 'storage/logs', 'storage/sessions', 'storage/uploads', 'storage/backups',
            'public/uploads', 'vendor', 'node_modules', '.git', '.claude', '.codex',
            'test-results', '.playwright-mcp', 'graphify-out',
        ];
        $excludedFiles = ['config/config.php', 'storage/installed.lock', '.env'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
            if ($relative === '' || in_array($relative, $excludedFiles, true)) {
                continue;
            }

            $skip = false;
            foreach ($excluded as $excludedPath) {
                if ($relative === $excludedPath || str_starts_with($relative, $excludedPath . '/')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            yield $relative => $item->getPathname();
        }
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
