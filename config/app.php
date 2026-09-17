<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'        => Env::get('APP_NAME', 'Digital Visiting Card'),
    'env'         => Env::get('APP_ENV', 'production'),
    'debug'       => (bool) Env::get('APP_DEBUG', false),
    'url'         => rtrim((string) Env::get('APP_URL', ''), '/'),
    'key'         => Env::get('APP_KEY', ''),
    'timezone'    => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale'      => Env::get('APP_LOCALE', 'en'),
    'force_https' => (bool) Env::get('APP_FORCE_HTTPS', false),

    // Session
    'session' => [
        'name'     => Env::get('SESSION_NAME', 'dvc_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
        'path'     => STORAGE_PATH . '/sessions',
        'secure'   => (bool) Env::get('SESSION_SECURE', false),
        'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    ],

    // Upload constraints (hard ceiling; plans may lower these)
    'uploads' => [
        'max_size'   => (int) Env::get('UPLOAD_MAX_SIZE', 5 * 1024 * 1024),
        'image_mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'image_ext'  => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_width'  => 2000,
        'quality'    => 82,
    ],

    // Rate limiting defaults: [max attempts, decay seconds]
    'throttle' => [
        'login'    => [8, 900],
        'register' => [5, 3600],
        'forgot'   => [5, 3600],
        'enquiry'  => [6, 3600],
        'api'      => [120, 60],
        'upload'   => [80, 3600],
        'track'    => [600, 60],
    ],
];
