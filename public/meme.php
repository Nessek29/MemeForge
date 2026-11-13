<?php
require_once __DIR__ . '/../src/helpers.php';

$meme = find_meme($_GET['id'] ?? null) ?? not_found();
$isOwner = current_user_id() === (int) $meme['user_id'];

render_header($meme['title']);
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <?= render_meme($meme, 'card-img-top meme-large') ?>
            <div class="card-body">
                <h1 class="h4 mb-1"><?= e($meme['title']) ?></h1>
                <p class="text-body-secondary small mb-3">
                    by <a href="user.php?id=<?= (int) $meme['user_id'] ?>"><?= e($meme['username']) ?></a>
                    · <?= e(fmt_date($meme['created_at'])) ?>
                    <?php if ($meme['updated_at'] !== $meme['created_at']): ?>
                        · edited <?= e(fmt_date($meme['updated_at'])) ?>
                    <?php endif; ?>
                </p>

                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="index.php">← Gallery</a>
                    <?php if ($isOwner): ?>
                        <a class="btn btn-sm btn-primary" href="edit.php?id=<?= (int) $meme['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?= (int) $meme['id'] ?>">Delete</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
