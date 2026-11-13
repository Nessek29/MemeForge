<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/images.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------------------------------------------------------------------------
 * Output encoding (XSS)
 * ---------------------------------------------------------------------- */

/** Escape a value for an HTML body or quoted attribute context. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* -------------------------------------------------------------------------
 * CSRF
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. Stops the request on a bad token. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(403, 'Your session form token was missing or invalid. Go back, reload the page and try again.');
    }
}

/* -------------------------------------------------------------------------
 * Authentication & authorization (forced browsing / IDOR)
 * ---------------------------------------------------------------------- */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $user = false;

    if (!is_logged_in()) {
        return null;
    }
    if ($user === false) {
        $stmt = db()->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;

        // The account was deleted elsewhere: drop the stale session.
        if ($user === null) {
            log_out();
        }
    }
    return $user;
}

function current_user_id(): ?int
{
    return current_user()['id'] ?? null;
}

/** Every page that needs an account calls this before doing anything else. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

function log_in(int $userId): void
{
    session_regenerate_id(true);           // prevent session fixation
    $_SESSION = ['user_id' => $userId];
    csrf_token();                          // fresh token for the new session
}

function log_out(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/* -------------------------------------------------------------------------
 * Data access with ownership built in (IDOR)
 * ---------------------------------------------------------------------- */

/** Read a meme for public display. Returns null if it does not exist. */
function find_meme(mixed $id): ?array
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT m.*, u.username
           FROM memes m
           JOIN users u ON u.id = m.user_id
          WHERE m.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Read a meme ONLY if it belongs to the given user. The ownership check is
 * part of the query, so a guessed id that belongs to someone else simply
 * returns nothing.
 */
function find_owned_meme(mixed $id, int $userId): ?array
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT m.*, u.username
           FROM memes m
           JOIN users u ON u.id = m.user_id
          WHERE m.id = ? AND m.user_id = ?'
    );
    $stmt->execute([$id, $userId]);
    return $stmt->fetch() ?: null;
}

/* -------------------------------------------------------------------------
 * Input helpers
 * ---------------------------------------------------------------------- */

/** Read a POST field as a trimmed string (arrays and other junk become ''). */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function too_long(string $value, int $max): bool
{
    return mb_strlen($value, 'UTF-8') > $max;
}

/* -------------------------------------------------------------------------
 * Flow helpers
 * ---------------------------------------------------------------------- */

/** Redirect to a path inside this app. Never pass user input as $path. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    $titles = [403 => 'Forbidden', 404 => 'Not found', 405 => 'Method not allowed'];
    render_header($titles[$status] ?? 'Error');
    echo '<div class="alert alert-warning">' . e($message) . '</div>';
    echo '<a class="btn btn-outline-secondary" href="index.php">Back to the gallery</a>';
    render_footer();
    exit;
}

function not_found(): never
{
    abort(404, 'That meme or page could not be found.');
}

function fmt_date(?string $ts): string
{
    return $ts ? date('M j, Y', strtotime($ts)) : '';
}

/* -------------------------------------------------------------------------
 * Views
 * ---------------------------------------------------------------------- */

/** Render a meme: the stored image with the captions layered on top. */
function render_meme(array $meme, string $extraClass = ''): string
{
    return '<figure class="meme ' . e($extraClass) . '">'
        . '<img src="' . e($meme['image_path']) . '" alt="' . e($meme['title']) . '" loading="lazy">'
        . '<span class="meme-text meme-top">' . e($meme['top_text']) . '</span>'
        . '<span class="meme-text meme-bottom">' . e($meme['bottom_text']) . '</span>'
        . '</figure>';
}

function render_header(string $title = APP_NAME): void
{
    $user = current_user();
    $flashes = take_flashes();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
    <style>
        .navbar-brand .brand-mark { color: #ffc93c; }
    </style>
</head>

<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg bg-dark" data-bs-theme="dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark">▲</span> <?= e(APP_NAME) ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <form class="d-flex ms-lg-3 my-2 my-lg-0" role="search" action="index.php" method="get">
                    <input class="form-control form-control-sm" type="search" name="q"
                        placeholder="Search memes…" aria-label="Search memes" value="<?= e($_GET['q'] ?? '') ?>">
                </form>
                <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                    <?php if ($user): ?>
                        <a class="btn btn-sm btn-warning fw-semibold" href="create.php">+ New Meme</a>
                        <a class="btn btn-sm btn-outline-light" href="user.php?id=<?= (int) $user['id'] ?>">My memes</a>
                        <a class="btn btn-sm btn-outline-light" href="profile.php"><?= e($user['username']) ?></a>
                        <form action="logout.php" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-light">Log out</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline-light" href="login.php">Log in</a>
                        <a class="btn btn-sm btn-warning fw-semibold" href="register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="container py-4">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
<?php
}

function render_footer(): void
{
?>
    </main>
    <footer class="container text-center text-body-secondary py-4 small">
        <?= e(APP_NAME) ?> — CST8265 lab application. For educational use only.
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
}

/**
 * Live caption preview for the create/edit forms. Captions are written with
 * textContent, so whatever the user types is never parsed as HTML.
 */
function render_preview_script(): void
{
?>
    <script>
        (function () {
            var top = document.getElementById('top_text');
            var bottom = document.getElementById('bottom_text');
            var previewTop = document.getElementById('preview-top');
            var previewBottom = document.getElementById('preview-bottom');
            var previewImg = document.getElementById('preview-img');
            var template = document.getElementById('template');

            function syncText() {
                previewTop.textContent = top.value;
                previewBottom.textContent = bottom.value;
            }
            top.addEventListener('input', syncText);
            bottom.addEventListener('input', syncText);

            if (template) {
                template.addEventListener('change', function () {
                    var opt = template.options[template.selectedIndex];
                    previewImg.src = opt.getAttribute('data-src');
                });
            }

            // Show only the inputs for the chosen image source.
            var radios = document.querySelectorAll('input[name="source"]');
            function syncSource() {
                radios.forEach(function (r) {
                    var panel = document.getElementById('source-' + r.value);
                    if (panel) { panel.hidden = !r.checked; }
                });
            }
            radios.forEach(function (r) { r.addEventListener('change', syncSource); });
            syncSource();
        })();
    </script>
<?php
}
