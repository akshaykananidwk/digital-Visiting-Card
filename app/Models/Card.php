<?php

declare(strict_types=1);

namespace App\Models;

final class Card extends Model
{
    protected string $table = 'cards';

    protected bool $softDeletes = true;

    protected array $jsonColumns = ['business_hours', 'theme_overrides', 'settings', 'draft_data'];

    protected array $intColumns = ['id', 'user_id', 'reseller_id', 'template_id', 'category_id', 'views_count', 'leads_count'];

    protected array $floatColumns = ['latitude', 'longitude'];

    protected array $fillable = [
        'uuid', 'user_id', 'reseller_id', 'template_id', 'category_id', 'slug', 'status', 'title',
        'full_name', 'designation', 'business_name', 'business_category', 'tagline', 'about',
        'profile_image', 'cover_image', 'logo_image', 'phone', 'phone_alt', 'whatsapp',
        'whatsapp_message', 'email', 'website', 'address', 'city', 'state', 'pincode', 'country',
        'map_link', 'latitude', 'longitude', 'business_hours', 'upi_id', 'payment_note',
        'seo_title', 'seo_description', 'seo_image', 'seo_keywords', 'theme_overrides', 'settings',
        'draft_data', 'published_at', 'expires_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    /** Slugs that would collide with application routes. */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'assets', 'card', 'cards', 'contact', 'customer', 'dashboard', 'editor',
        'faq', 'features', 'forgot-password', 'install', 'login', 'logout', 'pricing', 'privacy',
        'register', 'reseller', 'reset-password', 'robots', 'sitemap', 'storage', 'support',
        'templates', 'terms', 'uploads', 'webhooks', 'how-it-works', 'reseller-program', 'u', 'p',
        'verify-email', 'billing', 'settings', 'account', 'upgrade', 'checkout', 'invoice', 'qr',
    ];

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->firstWhere(['slug' => strtolower($slug)]);
    }

    /**
     * A published card ready for public display.
     *
     * @return array<string,mixed>|null
     */
    public function findPublished(string $slug): ?array
    {
        return $this->hydrate($this->db->selectOne(
            'SELECT * FROM `' . $this->table() . '`
              WHERE `slug` = :slug AND `deleted_at` IS NULL AND `status` = :status LIMIT 1',
            ['slug' => strtolower($slug), 'status' => 'published']
        ));
    }

    /** @return array<string,mixed>|null Card with its owner + template joined. */
    public function findPublishedWithRelations(string $slug): ?array
    {
        $row = $this->db->selectOne(
            'SELECT c.*,
                    u.`name` AS owner_name, u.`email` AS owner_email, u.`status` AS owner_status,
                    u.`reseller_id` AS owner_reseller_id,
                    t.`code` AS template_code, t.`name` AS template_name, t.`layout` AS template_layout,
                    t.`config` AS template_config, t.`theme_mode` AS template_mode, t.`is_premium` AS template_premium
               FROM `' . $this->table() . '` c
               JOIN `' . $this->db->table('users') . '` u ON u.id = c.user_id
          LEFT JOIN `' . $this->db->table('templates') . '` t ON t.id = c.template_id
              WHERE c.`slug` = :slug AND c.`deleted_at` IS NULL
              LIMIT 1',
            ['slug' => strtolower($slug)]
        );

        if ($row === null) {
            return null;
        }
        $hydrated = (array) $this->hydrate($row);
        $hydrated['template_config'] = json_decode_safe((string) ($row['template_config'] ?? ''));

        return $hydrated;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE `slug` = :slug';
        $bindings = ['slug' => strtolower($slug)];
        if ($exceptId !== null) {
            $sql .= ' AND `id` <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $bindings) > 0;
    }

    /** Produce a unique, route-safe slug derived from $base. */
    public function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $slug = str_slug($base);
        if (strlen($slug) < 3) {
            $slug = 'card-' . $slug;
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            $slug .= '-card';
        }

        $candidate = $slug;
        $suffix = 1;
        while ($this->slugExists($candidate, $exceptId) || in_array($candidate, self::RESERVED_SLUGS, true)) {
            $suffix++;
            $candidate = substr($slug, 0, 90) . '-' . $suffix;
            if ($suffix > 500) {
                $candidate = substr($slug, 0, 80) . '-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $candidate;
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, ?string $status = null): array
    {
        $conditions = ['user_id' => $userId];
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->where($conditions, 'created_at DESC');
    }

    public function countForUser(int $userId, ?string $status = null): int
    {
        $conditions = ['user_id' => $userId];
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->count($conditions);
    }

    /**
     * Ownership-checked fetch: the single place the application loads a card
     * on behalf of a signed-in user. Returns null when the card is not owned
     * by the user (prevents IDOR).
     *
     * @return array<string,mixed>|null
     */
    public function findForOwner(int $cardId, int $userId): ?array
    {
        return $this->firstWhere(['id' => $cardId, 'user_id' => $userId]);
    }

    /**
     * @param array{search?:string,status?:string,user_id?:int,reseller_id?:int,template_id?:int} $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 20, string $orderBy = 'created_at DESC'): array
    {
        $conditions = [];
        if (!empty($filters['search'])) {
            $term = '%' . $this->escapeLike((string) $filters['search']) . '%';
            $conditions['__raw'] = ['`title` LIKE :s OR `slug` LIKE :s OR `business_name` LIKE :s OR `full_name` LIKE :s OR `phone` LIKE :s', ['s' => $term]];
        }
        foreach (['status', 'user_id', 'reseller_id', 'template_id'] as $key) {
            if (!empty($filters[$key])) {
                $conditions[$key] = $filters[$key];
            }
        }

        return $this->paginate($conditions, $page, $perPage, $orderBy);
    }

    /** @return array<int,array<string,mixed>> Cards joined with owner names (admin list). */
    public function searchWithOwners(array $filters, int $page = 1, int $perPage = 20): array
    {
        $result = $this->search($filters, $page, $perPage);
        if ($result['data'] === []) {
            return $result;
        }
        $ids = array_column($result['data'], 'user_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $owners = $this->db->select(
            'SELECT `id`, `name`, `email` FROM `' . $this->db->table('users') . '` WHERE `id` IN (' . $placeholders . ')',
            array_values($ids)
        );
        $map = [];
        foreach ($owners as $owner) {
            $map[(int) $owner['id']] = $owner;
        }
        foreach ($result['data'] as $index => $card) {
            $result['data'][$index]['owner'] = $map[(int) $card['user_id']] ?? null;
        }

        return $result;
    }

    public function publish(int $id): int
    {
        return $this->updateById($id, ['status' => 'published', 'published_at' => now()]);
    }

    public function unpublish(int $id): int
    {
        return $this->updateById($id, ['status' => 'draft']);
    }

    /** @return array<string,int> */
    public function statistics(): array
    {
        $table = $this->table();

        return [
            'total'     => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL"),
            'published' => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'published'"),
            'draft'     => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'draft'"),
            'expired'   => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'expired'"),
            'suspended' => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND `status` = 'suspended'"),
            'today'     => (int) $this->db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `deleted_at` IS NULL AND DATE(`created_at`) = CURDATE()"),
        ];
    }

    /** @return array<int,array{label:string,value:int}> */
    public function growth(int $days = 30): array
    {
        $rows = $this->db->select(
            'SELECT DATE(`created_at`) AS d, COUNT(*) AS c FROM `' . $this->table() . '`
              WHERE `deleted_at` IS NULL AND `created_at` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(`created_at`) ORDER BY d',
            ['days' => $days]
        );

        return User::fillSeries($rows, $days);
    }

    /** Published cards for the XML sitemap. @return array<int,array<string,mixed>> */
    public function sitemapRows(int $limit = 5000): array
    {
        return $this->db->select(
            'SELECT `slug`, `updated_at`, `published_at` FROM `' . $this->table() . '`
              WHERE `deleted_at` IS NULL AND `status` = :status
              ORDER BY `published_at` DESC LIMIT ' . max(1, min(50000, $limit)),
            ['status' => 'published']
        );
    }

    /** Mark published cards whose subscription window has closed. */
    public function expireDue(): int
    {
        return $this->db->execute(
            'UPDATE `' . $this->table() . '` SET `status` = :expired, `updated_at` = :now
              WHERE `deleted_at` IS NULL AND `status` = :published
                AND `expires_at` IS NOT NULL AND `expires_at` < :now2',
            ['expired' => 'expired', 'published' => 'published', 'now' => now(), 'now2' => now()]
        );
    }
}
