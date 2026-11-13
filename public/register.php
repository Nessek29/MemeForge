<?php
require_once __DIR__ . '/../src/helpers.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = post_string('username');
    $email    = post_string('email');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Usernames are 3–20 characters: letters, numbers and underscores only.';
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Passwords must be 8–72 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $exists = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($exists->fetchColumn()) {
            $errors[] = 'That username is taken.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        log_in((int) db()->lastInsertId());
        flash('success', 'Welcome to ' . APP_NAME . ', ' . $username . '!');
        redirect('index.php');
    }
}

render_header('Register');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <h1 class="h4 mb-3">Create an account</h1>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input id="username" name="username" class="form-control" value="<?= e($username) ?>"
                    pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= e($email) ?>"
                    autocomplete="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-control"
                    minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm">Confirm password</label>
                <input id="confirm" name="confirm" type="password" class="form-control"
                    minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="btn btn-warning fw-semibold">Register</button>
            <p class="small mt-3 mb-0">Already have an account? <a href="login.php">Log in</a>.</p>
        </form>
    </div>
</div>
<?php render_footer(); ?>
