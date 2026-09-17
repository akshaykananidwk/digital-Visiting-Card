<?php

declare(strict_types=1);

namespace App\Models;

final class CardDailyStat extends Model
{
    protected string $table = 'card_daily_stats';

    protected bool $timestamps = false;

    protected array $intColumns = [
        'id', 'card_id', 'views', 'unique_views', 'calls', 'whatsapp', 'emails',
        'websites', 'directions', 'shares', 'saves', 'qr_scans', 'leads',
    ];

    protected array $fillable = [
        'card_id', 'stat_date', 'views', 'unique_views', 'calls', 'whatsapp', 'emails',
        'websites', 'directions', 'shares', 'saves', 'qr_scans', 'leads',
    ];

    /** Column each tracked event increments. */
    private const EVENT_COLUMNS = [
        'view'         => 'views',
        'unique_view'  => 'unique_views',
        'call'         => 'calls',
        'whatsapp'     => 'whatsapp',
        'email'        => 'emails',
        'website'      => 'websites',
        'directions'   => 'directions',
        'share'        => 'shares',
        'save_contact' => 'saves',
        'qr_scan'      => 'qr_scans',
        'lead'         => 'leads',
    ];

    /** Atomic upsert of one counter for today. */
    public function bump(int $cardId, string $event, int $amount = 1, ?string $date = null): void
    {
        $column = self::EVENT_COLUMNS[$event] ?? null;
        if ($column === null) {
            return;
        }
        $date ??= date('Y-m-d');

        $this->db->execute(
            'INSERT INTO `' . $this->table() . '` (`card_id`, `stat_date`, `' . $column . '`)
             VALUES (:card, :date, :amount)
             ON DUPLICATE KEY UPDATE `' . $column . '` = `' . $column . '` + :amount2',
            ['card' => $cardId, 'date' => $date, 'amount' => $amount, 'amount2' => $amount]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function range(int $cardId, string $from, string $to): array
    {
        return $this->db->select(
            'SELECT * FROM `' . $this->table() . '` WHERE `card_id` = :card AND `stat_date` BETWEEN :from AND :to ORDER BY `stat_date`',
            ['card' => $cardId, 'from' => $from, 'to' => $to]
        );
    }

    /** @return array<string,int> */
    public function totals(int $cardId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT COALESCE(SUM(`views`),0) views, COALESCE(SUM(`unique_views`),0) unique_views,
                       COALESCE(SUM(`calls`),0) calls, COALESCE(SUM(`whatsapp`),0) whatsapp,
                       COALESCE(SUM(`emails`),0) emails, COALESCE(SUM(`websites`),0) websites,
                       COALESCE(SUM(`directions`),0) directions, COALESCE(SUM(`shares`),0) shares,
                       COALESCE(SUM(`saves`),0) saves, COALESCE(SUM(`qr_scans`),0) qr_scans,
                       COALESCE(SUM(`leads`),0) leads
                  FROM `' . $this->table() . '` WHERE `card_id` = :card';
        $bindings = ['card' => $cardId];
        if ($from !== null && $to !== null) {
            $sql .= ' AND `stat_date` BETWEEN :from AND :to';
            $bindings['from'] = $from;
            $bindings['to'] = $to;
        }

        $row = $this->db->selectOne($sql, $bindings) ?? [];

        return array_map(static fn ($value): int => (int) $value, $row);
    }

    /**
     * Aggregated totals across every card a user owns.
     *
     * @return array<string,int>
     */
    public function totalsForUser(int $userId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT COALESCE(SUM(s.`views`),0) views, COALESCE(SUM(s.`unique_views`),0) unique_views,
                       COALESCE(SUM(s.`calls`),0) calls, COALESCE(SUM(s.`whatsapp`),0) whatsapp,
                       COALESCE(SUM(s.`emails`),0) emails, COALESCE(SUM(s.`websites`),0) websites,
                       COALESCE(SUM(s.`directions`),0) directions, COALESCE(SUM(s.`shares`),0) shares,
                       COALESCE(SUM(s.`saves`),0) saves, COALESCE(SUM(s.`qr_scans`),0) qr_scans,
                       COALESCE(SUM(s.`leads`),0) leads
                  FROM `' . $this->table() . '` s
                  JOIN `' . $this->db->table('cards') . '` c ON c.id = s.card_id
                 WHERE c.`user_id` = :user AND c.`deleted_at` IS NULL';
        $bindings = ['user' => $userId];
        if ($from !== null && $to !== null) {
            $sql .= ' AND s.`stat_date` BETWEEN :from AND :to';
            $bindings['from'] = $from;
            $bindings['to'] = $to;
        }

        $row = $this->db->selectOne($sql, $bindings) ?? [];

        return array_map(static fn ($value): int => (int) $value, $row);
    }

    /**
     * Daily series for one card (or all of a user's cards) ready for charting.
     *
     * @return array<int,array{label:string,value:int}>
     */
    public function series(?int $cardId, ?int $userId, string $column = 'views', int $days = 30): array
    {
        $column = in_array($column, self::EVENT_COLUMNS, true) ? $column : 'views';

        if ($cardId !== null) {
            $rows = $this->db->select(
                'SELECT `stat_date` AS d, `' . $column . '` AS c FROM `' . $this->table() . '`
                  WHERE `card_id` = :card AND `stat_date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)',
                ['card' => $cardId, 'days' => $days]
            );
        } else {
            $rows = $this->db->select(
                'SELECT s.`stat_date` AS d, SUM(s.`' . $column . '`) AS c FROM `' . $this->table() . '` s
                   JOIN `' . $this->db->table('cards') . '` c ON c.id = s.card_id
                  WHERE c.`user_id` = :user AND c.`deleted_at` IS NULL
                    AND s.`stat_date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  GROUP BY s.`stat_date`',
                ['user' => $userId, 'days' => $days]
            );
        }

        return User::fillSeries($rows, $days);
    }

    /** Platform-wide daily views for the admin dashboard. @return array<int,array{label:string,value:int}> */
    public function platformSeries(int $days = 30): array
    {
        $rows = $this->db->select(
            'SELECT `stat_date` AS d, SUM(`views`) AS c FROM `' . $this->table() . '`
              WHERE `stat_date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY `stat_date`',
            ['days' => $days]
        );

        return User::fillSeries($rows, $days);
    }

    public function platformTotalViews(): int
    {
        return (int) $this->db->scalar('SELECT COALESCE(SUM(`views`),0) FROM `' . $this->table() . '`');
    }
}
