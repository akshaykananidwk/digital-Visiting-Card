<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Settings;
use App\Middleware\Maintenance;
use App\Models\Backup;
use App\Models\UpdateLog;
use App\Services\GitHubClient;
use App\Services\UpdateService;
use Throwable;

/** Admin → Updates: GitHub configuration, check, install, rollback, history. */
final class UpdateController extends AdminController
{
    public function index(): Response
    {
        $service = new UpdateService();
        $service->clearStaleRun();

        $github = $service->github();

        return $this->render('admin.updates.index', [
            'title'          => 'Updates',
            'version'        => APP_VERSION,
            'releaseDate'    => APP_RELEASE_DATE,
            'currentCommit'  => $service->currentCommit(),
            'repo'           => $github->repository(),
            'branch'         => $github->branch(),
            'hasToken'       => $github->hasToken(),
            'configured'     => $github->isConfigured(),
            'lastCheck'      => (string) (Settings::get('github_last_check') ?? ''),
            'lastUpdate'     => (string) (Settings::get('github_last_update') ?? ''),
            'autoBackup'     => (bool) Settings::get('auto_backup_before_update', true),
            'retention'      => (int) (Settings::get('backup_retention') ?: 5),
            'protectedPaths' => (string) (Settings::get('protected_paths') ?? ''),
            'defaultProtected' => UpdateService::DEFAULT_PROTECTED,
            'history'        => (new UpdateLog())->recent(10),
            'backups'        => (new Backup())->recent(5),
            'maintenance'    => Maintenance::isActive(),
            'zipAvailable'   => class_exists(\ZipArchive::class),
            'curlAvailable'  => function_exists('curl_init'),
        ]);
    }

    public function saveSettings(): Response
    {
        $this->requireSuperAdmin();

        $repo = trim($this->request->string('github_repo'));
        $branch = trim($this->request->string('github_branch')) ?: 'main';
        $token = $this->request->string('github_token');

        if ($repo !== '' && preg_match('#^[\w.\-]+/[\w.\-]+$#', $repo) !== 1 && !str_contains($repo, 'github.com')) {
            $this->error('Enter the repository as owner/repository (for example akshaykananidwk/digital-Visiting-Card).');

            return $this->redirect('admin/updates');
        }

        Settings::set('github_repo', $repo, 'updates');
        Settings::set('github_branch', $branch, 'updates');

        // Blank means "keep the stored token"; "-" clears it.
        if ($token === '-') {
            Settings::set('github_token', '', 'updates');
        } elseif ($token !== '') {
            Settings::set('github_token', $token, 'updates');
        }

        Settings::setMany([
            'auto_backup_before_update' => $this->request->bool('auto_backup_before_update'),
            'backup_retention'          => max(1, min(50, $this->request->int('backup_retention', 5))),
            'protected_paths'           => $this->request->string('protected_paths'),
        ], 'updates');

        Settings::flush();
        Settings::load(true);

        AuditLog::record('admin.update_settings_saved', 'settings', null, ['repo' => $repo, 'branch' => $branch]);

        // Immediately verify the credentials so problems surface now, not
        // in the middle of an update.
        $test = (new GitHubClient())->testConnection();
        if ($test['success']) {
            $this->success('Update settings saved. ' . $test['message']);
        } else {
            $this->error('Settings saved, but GitHub could not be reached: ' . $test['message']);
        }

        return $this->redirect('admin/updates');
    }

    public function check(): Response
    {
        $result = (new UpdateService())->check();

        AuditLog::record('admin.update_checked', 'system', null, [
            'available' => $result['available'],
            'commit'    => $result['commit']['short'] ?? null,
        ]);

        if ($this->request->wantsJson()) {
            return $this->json(['success' => $result['error'] === null] + $result);
        }

        if ($result['error'] !== null) {
            $this->error($result['error']);
        } elseif ($result['available']) {
            $this->info($result['message']);
        } else {
            $this->success($result['message']);
        }

        return $this->render('admin.updates.check', [
            'title'  => 'Check for update',
            'result' => $result,
        ]);
    }

    public function run(): Response
    {
        $this->requireSuperAdmin();

        if ($this->request->string('confirm') !== 'UPDATE') {
            $this->error('Type UPDATE to confirm that you want to install the update now.');

            return $this->redirect('admin/updates');
        }

        $service = new UpdateService();
        $running = (new UpdateLog())->runningUpdate();
        if ($running !== null && strtotime((string) $running['started_at']) > time() - 1800) {
            $this->error('An update is already running (started ' . (string) $running['started_at'] . ').');

            return $this->redirect('admin/updates');
        }

        try {
            $result = $service->run($this->userId());
        } catch (Throwable $e) {
            $this->error('The update crashed: ' . $e->getMessage() . ' — the site was left in maintenance mode for safety.');

            return $this->redirect('admin/updates/history');
        }

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        return $this->redirect('admin/updates/history/' . (int) $result['log_id']);
    }

    public function rollback(): Response
    {
        $this->requireSuperAdmin();

        $logId = $this->request->int('log_id');
        $log = (new UpdateLog())->find($logId);

        if ($log === null) {
            $this->error('Update record not found.');

            return $this->redirect('admin/updates/history');
        }
        if ($this->request->string('confirm') !== 'ROLLBACK') {
            $this->error('Type ROLLBACK to confirm.');

            return $this->redirect('admin/updates/history/' . $logId);
        }

        $result = (new UpdateService())->rollback($logId, $log['backup_id'] ?? null, $this->userId());

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        return $this->redirect('admin/updates/history/' . $logId);
    }

    public function history(): Response
    {
        return $this->render('admin.updates.history', [
            'title'   => 'Update history',
            'result'  => (new UpdateLog())->paginate([], $this->page(), 20, 'started_at DESC'),
        ]);
    }

    public function show(string $id): Response
    {
        $log = (new UpdateLog())->find((int) $id);
        if ($log === null) {
            $this->error('Update record not found.');

            return $this->redirect('admin/updates/history');
        }

        return $this->render('admin.updates.show', [
            'title'  => 'Update #' . (int) $log['id'],
            'log'    => $log,
            'backup' => !empty($log['backup_id']) ? (new Backup())->find((int) $log['backup_id']) : null,
        ]);
    }

    public function toggleMaintenance(): Response
    {
        $enabled = $this->request->bool('enabled');

        if ($enabled) {
            Maintenance::enable($this->request->string('message') ?: 'We are performing scheduled maintenance. Please check back shortly.');
            $this->info('Maintenance mode is ON. Visitors see the maintenance page; administrators keep full access.');
        } else {
            Maintenance::disable();
            $this->success('Maintenance mode is OFF — the site is live again.');
        }

        Settings::set('maintenance_mode', $enabled, 'general', 'boolean');
        AuditLog::record('admin.maintenance_toggled', 'system', null, ['enabled' => $enabled]);

        return $this->back('admin/updates');
    }
}
