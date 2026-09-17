<?php

declare(strict_types=1);

namespace App\Models;

final class CardEvent extends Model
{
    protected string $table = 'card_events';

    protected bool $timestamps = false;

    protected array $intColumns = ['id', 'card_id'];

    protected array $fillable = ['card_id', 'event_type', 'label', 'visitor_hash', 'device_type', 'occurred_on', 'created_at'];

    /** Event types accepted by the public tracking endpoint. */
    public const TYPES = [
        'call', 'whatsapp', 'email', 'website', 'directions', 'share',
        'save_contact', 'qr_scan', 'product_click', 'service_click', 'social_click', 'payment', 'engaged', 'enquiry_sent',
    ];

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }
}
