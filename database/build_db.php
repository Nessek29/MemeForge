<?php

/**
 * Rebuild and pre-populate the SQLite database.
 *
 * Usage:
 *   composer reset-db
 *   php database/build_db.php
 *
 * Drops the existing database, recreates the schema, seeds sample users and
 * memes, and clears any user-uploaded images.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';

$dbFile = DB_PATH;

if (file_exists($dbFile)) {
    unlink($dbFile);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

// --- Seed users -----------------------------------------------------------
// username, email, password, created_at offset
$users = [
    ['alice', 'alice@memeforge.local', 'password123',  '-30 days'],
    ['bob',   'bob@example.com',       'hunter2',      '-25 days'],
    ['carol', 'carol@example.com',     'letmein2024',  '-20 days'],
    ['admin', 'admin@memeforge.local', 'Sup3rSecret!', '-35 days'],
];

$insUser = $pdo->prepare(
    "INSERT INTO users (username, email, password_hash, created_at)
     VALUES (?, ?, ?, datetime('now', ?))"
);
foreach ($users as $u) {
    $insUser->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3]]);
}

// --- Seed memes -----------------------------------------------------------
// user_id, title, top, bottom, template key, created_at offset
$memes = [
    [1, 'Patch day',              'Fixed one XSS bug',              'Ready to climb anything',        'mountains', '-12 days'],
    [1, 'Friday deploys',         'Deploying at 4:59 on a Friday',  'What could possibly go wrong',   'city',      '-3 days'],
    [2, 'Prepared statements',    "Attacker sends ' OR 1=1 --",     'Prepared statement: nice string', 'desert',    '-10 days'],
    [2, 'Log diving',             'Searching the logs for the bug', 'Day four',                       'forest',    '-6 days'],
    [3, 'Dependency audit',       'Counting the CVEs',              'In my node_modules',             'night-sky', '-8 days'],
    [3, 'Trust boundaries',       'Deep down',                      'Every input is untrusted',       'ocean',     '-1 days'],
    [4, 'Welcome to MemeForge',   'Welcome to MemeForge',           'Be kind. Make memes.',           'mountains', '-14 days'],
];

$templates = MEME_TEMPLATES;
$insMeme = $pdo->prepare(
    "INSERT INTO memes (user_id, title, top_text, bottom_text, image_path, image_source, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, 'template', datetime('now', ?), datetime('now', ?))"
);
foreach ($memes as $m) {
    $insMeme->execute([$m[0], $m[1], $m[2], $m[3], $templates[$m[4]]['path'], $m[5], $m[5]]);
}

// --- Clear uploads --------------------------------------------------------
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
foreach (glob(UPLOAD_DIR . '/*') ?: [] as $file) {
    if (is_file($file) && basename($file) !== '.gitkeep') {
        unlink($file);
    }
}

echo "Database rebuilt at: {$dbFile}\n";
echo 'Seeded ' . count($users) . ' users and ' . count($memes) . " memes. Uploads cleared.\n";
echo "Sample logins (username / password):\n";
foreach ($users as $u) {
    echo "  - {$u[0]} / {$u[2]}\n";
}
