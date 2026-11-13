<?php
require_once __DIR__ . '/../src/helpers.php';

$user = require_login();

// Ownership is enforced in the query: someone else's meme id is a 404.
$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null);
$meme = find_owned_meme($id, $user['id']) ?? not_found();

$form = [
    'title'       => $meme['title'],
    'top_text'    => $meme['top_text'],
    'bottom_text' => $meme['bottom_text'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($form) as $k) {
        $form[$k] = post_string($k);
    }

    if ($form['title'] === '') {
        $errors[] = 'Give your meme a title.';
    } elseif (too_long($form['title'], MAX_TITLE_LEN)) {
        $errors[] = 'Titles can be at most ' . MAX_TITLE_LEN . ' characters.';
    }
    if ($form['top_text'] === '' && $form['bottom_text'] === '') {
        $errors[] = 'Add top text, bottom text, or both.';
    }
    if (too_long($form['top_text'], MAX_CAPTION_LEN) || too_long($form['bottom_text'], MAX_CAPTION_LEN)) {
        $errors[] = 'Captions can be at most ' . MAX_CAPTION_LEN . ' characters each.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "UPDATE memes
                SET title = ?, top_text = ?, bottom_text = ?, updated_at = datetime('now')
              WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$form['title'], $form['top_text'], $form['bottom_text'], $meme['id'], $user['id']]);
        flash('success', 'Meme updated.');
        redirect('meme.php?id=' . (int) $meme['id']);
    }
}

render_header('Edit meme');
?>
<h1 class="h4 mb-3">Edit meme</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $meme['id'] ?>">

            <div class="mb-3">
                <label class="form-label" for="title">Title</label>
                <input id="title" name="title" class="form-control" maxlength="<?= MAX_TITLE_LEN ?>"
                    value="<?= e($form['title']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="top_text">Top text</label>
                <input id="top_text" name="top_text" class="form-control" maxlength="<?= MAX_CAPTION_LEN ?>"
                    value="<?= e($form['top_text']) ?>" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label" for="bottom_text">Bottom text</label>
                <input id="bottom_text" name="bottom_text" class="form-control" maxlength="<?= MAX_CAPTION_LEN ?>"
                    value="<?= e($form['bottom_text']) ?>" autocomplete="off">
            </div>
            <p class="small text-body-secondary">To use a different image, make a new meme.</p>

            <div class="d-flex gap-2">
                <button class="btn btn-primary">Save changes</button>
                <a class="btn btn-outline-secondary" href="meme.php?id=<?= (int) $meme['id'] ?>">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-lg-6">
    <div class="sticky-top preview-sticky">
            <h2 class="h6 text-body-secondary">Preview</h2>
            <figure class="meme shadow-sm">
                <img id="preview-img" src="<?= e($meme['image_path']) ?>" alt="Preview">
                <span id="preview-top" class="meme-text meme-top"><?= e($form['top_text']) ?></span>
                <span id="preview-bottom" class="meme-text meme-bottom"><?= e($form['bottom_text']) ?></span>
            </figure>
        </div>
    </div>
</div>

<?php render_preview_script(); ?>
<?php render_footer(); ?>
