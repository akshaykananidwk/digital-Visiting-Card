<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Settings;
use App\Models\Backup;
use App\Services\BackupService;
use Throwable;

final class BackupController extends AdminController
{
    public function index(): Response
    {
        $backups = new Backup();

        return $this->render('admin.backups', [
            'title'        => 'Backups',
            'backups'      => $backups->recent(30),
            'totalSize'    => $backups->totalSize(),
            'retention'    => (int) (Settings::get('backup_retention') ?: 5),
            'diskFree'     => (float) (@disk_free_space(BASE_PATH) ?: 0),
            'zipAvailable' => class_exists(\ZipArchive::class),
        ]);
    }

    public function store(): Response
    {
        $this->requireSuperAdmin();

        $type = $this->request->string('type', 'full');
        $type = in_array($type, ['full', 'files', 'database'], true) ? $type : 'full';
        $includeUploads = $this->request->bool('include_uploads');

        try {
            @set_time_limit(900);
            $backup = (new BackupService())->create($type, 'manual', $this->userId(), $includeUploads);

            if ((string) $backup['status'] === 'completed') {
                $this->success(sprintf(
                    'Backup #%d created and verified (%s).',
                    (int) $backup['id'],
                    human_size((int) $backup['files_size'] + (int) $backup['database_size'])
                ));
            } else {
                $this->error('Backup failed: ' . (string) ($backup['error'] ?? 'unknown error'));
            }
        } catch (Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());
        }

        return $this->redirect('admin/backups');
    }

    public function verify(string $id): Response
    {
        $result = (new BackupService())->verify((int) $id);

        if ($result['verified']) {
            $this->success('Backup #' . (int) $id . ' verified: ' . $result['message']);
        } else {
            $this->error('Backup #' . (int) $id . ' failed verification: ' . $result['message']);
        }

        return $this->redirect('admin/backups');
    }

    public function restore(string $id): Response
    {
        $this->requireSuperAdmin();

        if ($this->request->string('confirm') !== 'RESTORE') {
            $this->error('Type RESTORE to confirm. This overwrites the current files and database.');

            return $this->redirect('admin/backups');
        }

        $files = $this->request->bool('restore_files', true);
        $database = $this->request->bool('restore_database', true);

        if (!$files && !$database) {
            $this->error('Choose at least one of files or database to restore.');

            return $this->redirect('admin/backups');
        }

        try {
            @set_time_limit(900);
            $result = (new BackupService())->restore((int) $id, $files, $database, $this->userId());

            if ($result['success']) {
                $this->success($result['message'] . ' Please sign in again if anything looks unusual.');
            } else {
                $this->error($result['message']);
            }
        } catch (Throwable $e) {
            $this->error('Restore failed: ' . $e->getMessage());
        }

        return $this->redirect('admin/backups');
    }

    public function download(string $id, string $type): Response
    {
        $this->requireSuperAdmin();

        $backup = (new Backup())->find((int) $id);
        if ($backup === null) {
            $this->error('Backup not found.');

            return $this->redirect('admin/backups');
        }

        $relative = match ($type) {
            'files'    => (string) ($backup['files_path'] ?? ''),
            'database' => (string) ($backup['database_path'] ?? ''),
            default    => '',
        };

        if ($relative === '') {
            $this->error('That part of the backup does not exist.');

            return $this->redirect('admin/backups');
        }

        $absolute = (new BackupService())->absolutePath($relative);
        if ($absolute === null || !is_file($absolute)) {
            $this->error('The backup file is missing from storage.');

            return $this->redirect('admin/backups');
        }

        \App\Core\AuditLog::record('admin.backup_downloaded', 'backup', (int) $id, ['type' => $type]);

        return Response::download(
            (string) file_get_contents($absolute),
            basename($absolute),
            $type === 'files' ? 'application/zip' : 'application/sql'
        );
    }

    public function destroy(string $id): Response
    {
        $this->requireSuperAdmin();

        if ((new BackupService())->delete((int) $id, $this->userId())) {
            $this->success('Backup deleted.');
        } else {
            $this->error('Backup not found.');
        }

        return $this->redirect('admin/backups');
    }
}
