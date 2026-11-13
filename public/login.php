<?php
require_once __DIR__ . '/../src/helpers.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = post_string('username');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    // Always run password_verify (against a dummy hash if the user does not
    // exist) so a missing user takes as long as a wrong password.
    $hash  = $row['password_hash'] ?? '$2y$10$uvjEvwYetikscehHPMsHnubTdb8Zazitfm.mxBISzvhSNiqBmMQfW';
    $valid = password_verify($password, $hash);
    if ($row && $valid) {
        log_in((int) $row['id']);
        flash('success', 'Welcome back, ' . $row['username'] . '!');
        redirect('index.php');
    }
    $error = 'Invalid username or password.';   // same message either way
}

render_header('Log in');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <h1 class="h4 mb-3">Log in</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input id="username" name="username" class="form-control" value="<?= e($username) ?>"
                    autocomplete="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input id="password" name="password" type="password" class="form-control"
                    autocomplete="current-password" required>
            </div>
            <button class="btn btn-warning fw-semibold">Log in</button>
            <p class="small mt-3 mb-0">No account? <a href="register.php">Register</a>.</p>
        </form>
    </div>
</div>
<?php render_footer(); ?>
