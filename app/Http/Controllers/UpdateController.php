<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Services\UpdateService;

final class UpdateController extends Controller
{
    public function check(): never
    {
        $updater = new UpdateService();
        $settings = new SettingsService();

        try {
            $release = $updater->fetchLatestRelease();
            $settings->set('update_latest_version', $release['version']);
            $settings->set('update_release_name', $release['name']);
            $settings->set('update_release_notes', $release['notes']);
            $settings->set('update_release_url', $release['html_url']);
            $settings->set('update_download_url', $release['download_url']);
            $settings->set('update_checked_at', date(DATE_ATOM));

            Session::flash('success', $updater->isNewer($release['version'])
                ? "Update available: v{$release['version']}."
                : 'You are running the latest version.');
        } catch (\Throwable $exception) {
            error_log('LedgerFlow update check failed: ' . $exception->getMessage());
            Session::flash('errors', ["Couldn't check for updates right now. Please try again later."]);
        }

        $this->redirect('/settings');
    }

    public function apply(): never
    {
        $settings = new SettingsService();
        $updater = new UpdateService();
        $downloadUrl = (string) $settings->get('update_download_url', '');
        $version = (string) $settings->get('update_latest_version', '');

        if ($downloadUrl === '') {
            Session::flash('errors', ['Check for updates before applying one.']);
            $this->redirect('/settings');
        }

        if (!$updater->isNewer($version)) {
            Session::flash('errors', ['You are already running the latest version. Run "Check for updates" again if you expect a newer release.']);
            $this->redirect('/settings');
        }

        try {
            $backupPath = $updater->applyUpdate($downloadUrl);
            AuditLogger::log('system.updated', 'application', null, ['version' => $version, 'backup' => basename($backupPath)]);
            Session::flash('success', "Updated to v{$version}. A backup of the previous version was saved to storage/backups/" . basename($backupPath) . '.');
        } catch (\Throwable $exception) {
            AuditLogger::log('system.update_failed', 'application', null, ['error' => $exception->getMessage()]);
            error_log('LedgerFlow update apply failed: ' . $exception->getMessage());
            Session::flash('errors', ['Update failed: ' . $exception->getMessage()]);
        }

        $this->redirect('/settings');
    }
}
