<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Models\AuditLogEntry;
use App\Models\Notification;
use App\Services\AnalyticsService;
use App\Services\HealthCheck;
use App\Services\SubscriptionService;

final class LogController extends AdminController
{
    public function index(): Response
    {
        $files = [];
        foreach (glob(STORAGE_PATH . '/logs/*.log') ?: [] as $path) {
            $files[] = [
                'name'     => basename($path),
                'size'     => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        }
        usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $this->render('admin.logs.index', [
            'title'         => 'Logs',
            'files'         => $files,
            'notifications' => (new Notification())->recent(30),
            'webhooks'      => $this->db()->select(
                'SELECT * FROM `' . $this->db()->table('webhook_events') . '` ORDER BY `id` DESC LIMIT 25'
            ),
        ]);
    }

    public function audit(): Response
    {
        $filters = [
            'search' => $this->request->string('q'),
            'action' => $this->request->string('action'),
        ];

        return $this->render('admin.logs.audit', [
            'title'   => 'Audit trail',
            'result'  => (new AuditLogEntry())->search($filters, $this->page(), 50),
            'filters' => $filters,
            'actions' => (new AuditLogEntry())->distinctActions(),
        ]);
    }

    public function file(string $name): Response
    {
        // Only files inside the log directory, and only .log files.
        $safe = basename($name);
        if (!str_ends_with($safe, '.log') || preg_match('/^[a-z0-9\-_.]+$/i', $safe) !== 1) {
            $this->error('Invalid log file name.');

            return $this->redirect('admin/logs');
        }

        $path = STORAGE_PATH . '/logs/' . $safe;
        $real = realpath($path);
        $root = realpath(STORAGE_PATH . '/logs');

        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            $this->error('Log file not found.');

            return $this->redirect('admin/logs');
        }

        // Show the tail only — log files can be large.
        $lines = $this->tail($real, max(50, min(2000, $this->request->int('lines', 400))));

        return $this->render('admin.logs.file', [
            'title'   => $safe,
            'file'    => $safe,
            'lines'   => $lines,
            'size'    => (int) filesize($real),
        ]);
    }

    public function health(): Response
    {
        $withEndpoints = $this->request->bool('endpoints');

        return $this->render('admin.health', [
            'title'  => 'Health check',
            'report' => (new HealthCheck())->run($withEndpoints),
            'endpoints' => $withEndpoints,
        ]);
    }

    /** Manual maintenance jobs that would otherwise run from cron. */
    public function runTask(string $task): Response
    {
        $message = match ($task) {
            'expire-subscriptions' => (static function (): string {
                $result = (new SubscriptionService())->expireDue();

                return sprintf('%d subscription(s) expired and %d card(s) updated.', $result['expired'], $result['cards']);
            })(),
            'prune-analytics' => (static function (): string {
                $deleted = (new AnalyticsService())->prune(400);

                return $deleted . ' old analytics row(s) removed.';
            })(),
            'purge-rate-limits' => RateLimiter::purge() . ' expired rate-limit row(s) removed.',
            'clear-cache' => (static function (): string {
                $count = 0;
                foreach (glob(STORAGE_PATH . '/cache/*') ?: [] as $file) {
                    if (is_file($file) && basename($file) !== '.gitignore' && @unlink($file)) {
                        $count++;
                    }
                }
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                }

                return $count . ' cache file(s) cleared.';
            })(),
            default => null,
        };

        if ($message === null) {
            $this->error('Unknown maintenance task.');

            return $this->redirect('admin/health');
        }

        AuditLog::record('admin.maintenance_task', 'system', null, ['task' => $task]);
        $this->success($message);

        return $this->back('admin/health');
    }

    /** @return array<int,string> */
    private function tail(string $path, int $lines): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunk = 4096;
        $position = -1;
        $found = 0;
        $size = (int) filesize($path);

        while (-$position < $size && $found < $lines) {
            $seek = max(-$size, $position - $chunk);
            fseek($handle, $seek, SEEK_END);
            $read = (string) fread($handle, min($chunk, (int) abs($seek - $position)));
            $buffer = $read . $buffer;
            $found = substr_count($buffer, "\n");
            $position = $seek;
        }
        fclose($handle);

        $all = explode("\n", trim($buffer));

        return array_slice($all, -$lines);
    }
}
