<?php

declare(strict_types=1);

// Errors are logged to the terminal running `composer start`, never shown to users.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('APP_NAME', 'MemeForge');
define('DB_PATH', __DIR__ . '/../database/memes.sqlite');
define('UPLOAD_DIR', __DIR__ . '/../public/uploads');

// Image rules shared by file uploads and URL imports.
define('MAX_IMAGE_BYTES', 2 * 1024 * 1024);   // 2 MB
define('MAX_IMAGE_DIMENSION', 4000);           // pixels, either side
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
]);

// Text limits.
define('MAX_TITLE_LEN', 100);
define('MAX_CAPTION_LEN', 120);

// Built-in templates. Users pick a key; the path is never taken from input.
define('MEME_TEMPLATES', [
    'mountains' => ['label' => 'Mountain Sunrise', 'path' => 'assets/templates/mountains.jpg'],
    'ocean'     => ['label' => 'Open Ocean',       'path' => 'assets/templates/ocean.jpg'],
    'night-sky' => ['label' => 'Night Sky',        'path' => 'assets/templates/night-sky.jpg'],
    'desert'    => ['label' => 'Lonely Cactus',    'path' => 'assets/templates/desert.jpg'],
    'forest'    => ['label' => 'Deep Forest',      'path' => 'assets/templates/forest.jpg'],
    'city'      => ['label' => 'City at Dusk',     'path' => 'assets/templates/city.jpg'],
]);
