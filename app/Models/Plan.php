<?php

declare(strict_types=1);

namespace App\Models;

final class Plan extends Model
{
    protected string $table = 'plans';

    protected bool $softDeletes = true;

    protected array $jsonColumns = ['features'];

    protected array $intColumns = [
        'id', 'duration_days', 'trial_days', 'card_limit', 'product_limit', 'service_limit',
        'gallery_limit', 'video_limit', 'lead_limit', 'storage_limit_mb', 'sort_order',
    ];

    protected array $floatColumns = ['price', 'reseller_price', 'mrp'];

    protected array $fillable = [
        'slug', 'name', 'description', 'price', 'reseller_price', 'mrp', 'currency', 'duration_days',
        'trial_days', 'card_limit', 'product_limit', 'service_limit', 'gallery_limit', 'video_limit',
        'lead_limit', 'storage_limit_mb', 'premium_templates', 'custom_domain', 'remove_branding',
        'analytics', 'qr_download', 'vcard', 'enquiry_form', 'seo_controls', 'api_access',
        'priority_support', 'features', 'is_free', 'is_active', 'is_featured', 'sort_order',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->where(['is_active' => 1], 'sort_order ASC');
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->firstWhere(['slug' => $slug]);
    }

    /** @return array<string,mixed>|null The default free plan assigned at registration. */
    public function freePlan(): ?array
    {
        return $this->firstWhere(['is_free' => 1, 'is_active' => 1]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $conditions = ['slug' => $slug];
        if ($exceptId !== null) {
            $conditions['id'] = ['!=', $exceptId];
        }

        return $this->exists($conditions);
    }
}
