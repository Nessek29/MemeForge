<?php
require_once __DIR__ . '/../src/helpers.php';

$user = require_login();
$templates = MEME_TEMPLATES;

$form = [
    'title'       => '',
    'top_text'    => '',
    'bottom_text' => '',
    'source'      => 'template',
    'template'    => array_key_first($templates),
    'image_url'   => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (['title', 'top_text', 'bottom_text', 'source', 'template', 'image_url'] as $k) {
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
    if (!in_array($form['source'], ['template', 'upload', 'url'], true)) {
        $errors[] = 'Choose where the image comes from.';
    }

    // Only resolve the image once the text is valid, so a bad title does not
    // leave an orphaned upload behind.
    $imagePath = null;
    if (!$errors) {
        try {
            $imagePath = match ($form['source']) {
                'template' => isset($templates[$form['template']])
                    ? $templates[$form['template']]['path']
                    : throw new ImageException('Choose one of the listed templates.'),
                'upload'   => store_uploaded_image($_FILES['image_file'] ?? null),
                'url'      => import_image_from_url($form['image_url']),
            };
        } catch (ImageException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO memes (user_id, title, top_text, bottom_text, image_path, image_source)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $form['title'], $form['top_text'], $form['bottom_text'], $imagePath, $form['source'],
        ]);
        flash('success', 'Meme published!');
        redirect('meme.php?id=' . (int) db()->lastInsertId());
    }
}

$previewSrc = $templates[$form['template']]['path'] ?? reset($templates)['path'];

render_header('New Meme');
?>
<h1 class="h4 mb-3">Make a meme</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_BYTES ?>">

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

            <fieldset class="mb-3">
                <legend class="form-label fs-6">Image</legend>
                <?php foreach (['template' => 'Template', 'upload' => 'Upload a file', 'url' => 'Import from URL'] as $val => $label): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="source" id="src-<?= $val ?>"
                            value="<?= $val ?>" <?= $form['source'] === $val ? 'checked' : '' ?>>
                        <label class="form-check-label" for="src-<?= $val ?>"><?= $label ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <div id="source-template" class="mb-3">
                <label class="form-label" for="template">Template</label>
                <select id="template" name="template" class="form-select">
                    <?php foreach ($templates as $key => $t): ?>
                        <option value="<?= e($key) ?>" data-src="<?= e($t['path']) ?>"
                            <?= $form['template'] === $key ? 'selected' : '' ?>><?= e($t['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="source-upload" class="mb-3">
                <label class="form-label" for="image_file">Image file</label>
                <input id="image_file" name="image_file" type="file" class="form-control"
                    accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="form-text">JPEG, PNG, GIF or WebP · max 2 MB · max 4000 × 4000 px.</div>
            </div>

            <div id="source-url" class="mb-3">
                <label class="form-label" for="image_url">Image URL</label>
                <input id="image_url" name="image_url" type="url" class="form-control"
                    placeholder="https://example.com/picture.png" value="<?= e($form['image_url']) ?>">
                <div class="form-text">The image is downloaded once and stored with your meme. Public http(s) URLs only.</div>
            </div>

            <div><button class="btn btn-warning fw-semibold">Publish</button></div>
        </form>
    </div>

    <div class="col-lg-6">
        <div class="sticky-top" style="top: 1rem; z-index: 1;">
            <h2 class="h6 text-body-secondary">Preview</h2>
            <figure class="meme shadow-sm">
                <img id="preview-img" src="<?= e($previewSrc) ?>" alt="Preview">
                <span id="preview-top" class="meme-text meme-top"><?= e($form['top_text']) ?></span>
                <span id="preview-bottom" class="meme-text meme-bottom"><?= e($form['bottom_text']) ?></span>
            </figure>
            <p class="small text-body-secondary">Uploaded and imported images appear after you publish.</p>
        </div>
    </div>
</div>

<?php render_preview_script(); ?>
<?php render_footer(); ?>
