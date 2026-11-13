<?php
require_once __DIR__ . '/../src/helpers.php';

const PER_PAGE = 12;

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);

$where = '';
$params = [];
if ($q !== '') {
    // Escape LIKE wildcards so the search term is matched literally.
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where = "WHERE m.title LIKE ? ESCAPE '\\' OR m.top_text LIKE ? ESCAPE '\\' OR m.bottom_text LIKE ? ESCAPE '\\'";
    $params = [$like, $like, $like];
}

$count = db()->prepare("SELECT COUNT(*) FROM memes m $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($page, $pages);

$stmt = db()->prepare(
    "SELECT m.*, u.username
       FROM memes m
       JOIN users u ON u.id = m.user_id
       $where
   ORDER BY m.created_at DESC, m.id DESC
      LIMIT ? OFFSET ?"
);
$i = 1;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p, PDO::PARAM_STR);
}
$stmt->bindValue($i++, PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue($i, ($page - 1) * PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$memes = $stmt->fetchAll();

render_header($q !== '' ? 'Search' : 'Gallery');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <?php if ($q !== ''): ?>
            Results for “<?= e($q) ?>” <span class="text-body-secondary fs-6">(<?= $total ?>)</span>
        <?php else: ?>
            Latest Memes
        <?php endif; ?>
    </h1>
    <?php if (is_logged_in()): ?>
        <a class="btn btn-warning fw-semibold" href="create.php">+ New Meme</a>
    <?php endif; ?>
</div>

<?php if (!$memes): ?>
    <p class="text-body-secondary">
        <?= $q !== '' ? 'No memes match that search.' : 'No memes yet. Be the first to make one!' ?>
    </p>
<?php endif; ?>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
    <?php foreach ($memes as $m): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <a href="meme.php?id=<?= (int) $m['id'] ?>"><?= render_meme($m, 'card-img-top') ?></a>
                <div class="card-body">
                    <h2 class="h6 card-title mb-1">
                        <a class="text-decoration-none" href="meme.php?id=<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a>
                    </h2>
                    <p class="card-text small text-body-secondary mb-0">
                        by <a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= e($m['username']) ?></a>
                        · <?= e(fmt_date($m['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
    <nav class="mt-4" aria-label="Pages">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="index.php?<?= e(http_build_query(['q' => $q, 'page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php render_footer(); ?>
