<?php
require_once __DIR__ . '/../src/helpers.php';

// Account settings for the logged-in user ONLY. There is no id parameter:
// the account being changed always comes from the session.
$user = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');

    if ($action === 'update_email') {
        $email = post_string('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            $errors[] = 'Enter a valid email address.';
        } else {
            $stmt = db()->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->execute([$email, $user['id']]);
            flash('success', 'Email address updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'delete_account') {
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        // Re-authenticate before a destructive, irreversible action.
        if (!is_string($password) || !password_verify($password, $hash)) {
            $errors[] = 'Your password was incorrect. Your account was not deleted.';
        } else {
            $files = db()->prepare("SELECT image_path FROM memes WHERE user_id = ? AND image_source <> 'template'");
            $files->execute([$user['id']]);
            $paths = $files->fetchAll(PDO::FETCH_COLUMN);

            // memes are removed by ON DELETE CASCADE
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            foreach ($paths as $p) {
                delete_stored_image($p);
            }

            log_out();
            flash('info', 'Your account and all of your memes have been deleted.');
            redirect('index.php');
        }
    } else {
        abort(400, 'Unknown action.');
    }
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM memes WHERE user_id = ?');
$countStmt->execute([$user['id']]);
$memeCount = (int) $countStmt->fetchColumn();

render_header('Account settings');
?>
<h1 class="h4 mb-3">Account settings</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5"><?= e($user['username']) ?></h2>
                <p class="mb-1"><strong>Email:</strong> <?= e($user['email']) ?></p>
                <p class="text-body-secondary small mb-2">
                    Joined <?= e(fmt_date($user['created_at'])) ?> · <?= $memeCount ?> meme<?= $memeCount === 1 ? '' : 's' ?>
                </p>
                <a href="user.php?id=<?= (int) $user['id'] ?>">View my public meme page →</a>
            </div>
        </div>

        <form method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_email">
            <h2 class="h6">Update email</h2>
            <label class="form-label" for="email">New email address</label>
            <input id="email" name="email" type="email" class="form-control mb-3" value="<?= e($user['email']) ?>" required>
            <div><button class="btn btn-primary">Save email</button></div>
        </form>
    </div>

    <div class="col-md-6">
        <form method="post" class="card card-body shadow-sm border-danger">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_account">
            <h2 class="h6 text-danger">Delete account</h2>
            <p class="small text-body-secondary">
                This permanently deletes your account and all <?= $memeCount ?> of your memes. It cannot be undone.
            </p>
            <label class="form-label" for="password">Confirm with your password</label>
            <input id="password" name="password" type="password" class="form-control mb-3"
                autocomplete="current-password" required>
            <div><button class="btn btn-danger">Delete my account</button></div>
        </form>
    </div>
</div>
<?php render_footer(); ?>
