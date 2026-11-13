<?php
require_once __DIR__ . '/../src/helpers.php';

$user = require_login();

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null);
$meme = find_owned_meme($id, $user['id']) ?? not_found();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stmt = db()->prepare('DELETE FROM memes WHERE id = ? AND user_id = ?');
    $stmt->execute([$meme['id'], $user['id']]);

    if ($meme['image_source'] !== 'template') {
        delete_stored_image($meme['image_path']);
    }

    flash('success', 'Meme deleted.');
    redirect('user.php?id=' . (int) $user['id']);
}

// GET: show a confirmation page. Deleting only ever happens on POST.
render_header('Delete meme');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-danger">
            <div class="card-body">
                <h1 class="h4">Delete this meme?</h1>
                <p class="text-body-secondary">“<?= e($meme['title']) ?>” will be removed permanently.</p>
                <?= render_meme($meme, 'mb-3') ?>
                <form method="post" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $meme['id'] ?>">
                    <button class="btn btn-danger">Yes, delete it</button>
                    <a class="btn btn-outline-secondary" href="meme.php?id=<?= (int) $meme['id'] ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
