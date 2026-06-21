<?php

declare(strict_types=1);

namespace App\Services;

final class UpdateService
{
    private const REPO = 'Rutmaniyar/Invoice_System';
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
        $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

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

        @set_time_limit(0);

        $tmpZip = sys_get_temp_dir() . '/ledgerflow-update-' . bin2hex(random_bytes(8)) . '.zip';

        try {
            $content = $this->httpGet($downloadUrl, '');
            file_put_contents($tmpZip, $content, LOCK_EX);

            $backupPath = $this->backupCurrentInstallation();
            $this->extractOver($tmpZip);
            (new InstallerService())->runMigrations(app()->db());

            return $backupPath;
        } finally {
            if (is_file($tmpZip)) {
                unlink($tmpZip);
            }
        }
    }

    private function httpGet(string $url, string $extraHeaders): string
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
            throw new \RuntimeException('Could not reach GitHub. Make sure allow_url_fopen is enabled.');
        }

        return $body;
    }

    private function backupCurrentInstallation(): string
    {
        $backupDir = STORAGE_PATH . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupPath = $backupDir . '/pre-update-' . $this->currentVersion() . '-' . date('Ymd-His') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($this->walk(ROOT_PATH) as $relative => $absolute) {
            if (!is_dir($absolute)) {
                $zip->addFile($absolute, $relative);
            }
        }

        $zip->close();

        return $backupPath;
    }

    private function extractOver(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open the downloaded update package.');
        }

        $extractTo = STORAGE_PATH . '/cache/update-' . bin2hex(random_bytes(6));
        mkdir($extractTo, 0755, true);
        $zip->extractTo($extractTo);
        $zip->close();

        $entries = array_values(array_diff(scandir($extractTo) ?: [], ['.', '..']));
        $sourceRoot = (count($entries) === 1 && is_dir($extractTo . '/' . $entries[0]))
            ? $extractTo . '/' . $entries[0]
            : $extractTo;

        foreach ($this->walk($sourceRoot) as $relative => $absolute) {
            $target = ROOT_PATH . '/' . $relative;
            if (is_dir($absolute)) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }

            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($absolute, $target);
        }

        $this->removeDirectory($extractTo);
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
