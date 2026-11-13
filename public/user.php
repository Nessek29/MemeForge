<?php
require_once __DIR__ . '/../src/helpers.php';

// Public gallery for one user. Only public fields are selected: no email.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$stmt = db()->prepare('SELECT id, username, created_at FROM users WHERE id = ?');
$stmt->execute([$id]);
$owner = $stmt->fetch() ?: not_found();

$ms = db()->prepare(
    'SELECT m.*, u.username
       FROM memes m
       JOIN users u ON u.id = m.user_id
      WHERE m.user_id = ?
   ORDER BY m.created_at DESC, m.id DESC'
);
$ms->execute([$owner['id']]);
$memes = $ms->fetchAll();

$isMe = current_user_id() === (int) $owner['id'];

render_header($owner['username'] . "'s memes");
?>
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h1 class="h3 mb-0"><?= e($owner['username']) ?></h1>
        <p class="text-body-secondary small mb-0">
            Joined <?= e(fmt_date($owner['created_at'])) ?> · <?= count($memes) ?> meme<?= count($memes) === 1 ? '' : 's' ?>
        </p>
    </div>
    <?php if ($isMe): ?>
        <a class="btn btn-sm btn-outline-secondary" href="profile.php">Account settings</a>
    <?php endif; ?>
</div>

<?php if (!$memes): ?>
    <p class="text-body-secondary">No memes yet.</p>
<?php endif; ?>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
    <?php foreach ($memes as $m): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <a href="meme.php?id=<?= (int) $m['id'] ?>"><?= render_meme($m, 'card-img-top') ?></a>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <a class="text-decoration-none small fw-semibold" href="meme.php?id=<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a>
                    <?php if ($isMe): ?>
                        <span class="d-flex gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $m['id'] ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?= (int) $m['id'] ?>">Delete</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php render_footer(); ?>
