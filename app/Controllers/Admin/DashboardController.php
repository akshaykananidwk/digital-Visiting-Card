<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\AuditLogEntry;
use App\Models\Card;
use App\Models\CardDailyStat;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Services\UpdateService;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $users = new User();
        $cards = new Card();
        $orders = new Order();
        $stats = new CardDailyStat();

        // A stale "running" update usually means PHP timed out mid-update.
        $stale = (new UpdateService())->clearStaleRun();
        if ($stale !== null) {
            $this->error('A previous update did not finish. Review it in Updates → History.');
        }

        return $this->render('admin.dashboard', [
            'title'       => 'Dashboard',
            'userStats'   => $users->statistics(),
            'cardStats'   => $cards->statistics(),
            'revenue'     => $orders->revenueStatistics(),
            'resellers'   => (new Reseller())->statistics(),
            'templates'   => [
                'total'   => (new Template())->count([]),
                'active'  => (new Template())->count(['is_active' => 1]),
                'premium' => (new Template())->count(['is_premium' => 1]),
            ],
            'totalViews'  => $stats->platformTotalViews(),
            'leads'       => (new Lead())->count([]),
            'charts'      => [
                'revenue' => $orders->revenueSeries(30),
                'users'   => $users->growth(30),
                'cards'   => $cards->growth(30),
                'views'   => $stats->platformSeries(30),
            ],
            'expiring'    => (new Subscription())->expiringWithin(14, 10),
            'topTemplates'=> (new Template())->usageReport(8),
            'recentAudit' => (new AuditLogEntry())->where([], 'created_at DESC', 10),
        ]);
    }

    /** JSON chart feed (used for the period switcher). */
    public function chart(string $metric): Response
    {
        $days = $this->request->int('days', 30);
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;

        $data = match ($metric) {
            'revenue' => (new Order())->revenueSeries($days),
            'users'   => (new User())->growth($days),
            'cards'   => (new Card())->growth($days),
            'views'   => (new CardDailyStat())->platformSeries($days),
            default   => [],
        };

        return $this->json(['success' => true, 'metric' => $metric, 'days' => $days, 'data' => $data]);
    }
}
