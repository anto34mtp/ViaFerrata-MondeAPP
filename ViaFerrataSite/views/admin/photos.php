<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token CSRF invalide.');
        redirect(BASE_URL . '/admin/photos');
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);

    try {
        switch ($action) {
            case 'approve':
                $pdo->prepare("UPDATE user_photos SET is_approved=1 WHERE id=?")->execute([$id]);
                setFlash('success', 'Photo approuvée.');
                break;
            case 'delete':
                $row = $pdo->query("SELECT file_path FROM user_photos WHERE id=$id")->fetch();
                $pdo->prepare("DELETE FROM user_photos WHERE id=?")->execute([$id]);
                if ($row && !empty($row['file_path'])) {
                    $fp = ROOT_PATH . '/uploads/' . $row['file_path'];
                    if (file_exists($fp)) @unlink($fp);
                }
                setFlash('success', 'Photo supprimée.');
                break;
            case 'approve_all':
                $n = $pdo->exec("UPDATE user_photos SET is_approved=1 WHERE is_approved=0");
                setFlash('success', "$n photo(s) approuvée(s).");
                break;
        }
    } catch (\PDOException $e) {
        setFlash('error', 'Erreur : ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/photos');
}

$filter = $_GET['filter'] ?? 'pending';
$where  = $filter === 'all' ? '1=1' : 'p.is_approved=' . ($filter === 'approved' ? '1' : '0');

try {
    $photos = $pdo->query("
        SELECT p.id, p.file_path, p.caption, p.created_at, p.is_approved,
               v.name AS via_name, v.slug AS via_slug,
               u.username
        FROM user_photos p
        LEFT JOIN vias v ON p.via_id = v.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE $where
        ORDER BY p.created_at DESC LIMIT 60
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $photos = []; }

$pageTitle = 'Photos';
$adminCurrentPage = 'photos';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-slate-900">Photos
        <?php if ($navBadges['photos'] > 0): ?>
        <span class="text-base font-normal text-amber-600 ml-2"><?= $navBadges['photos'] ?> en attente</span>
        <?php endif; ?>
    </h1>
    <?php if ($navBadges['photos'] > 0): ?>
    <form method="POST" onsubmit="return confirm('Approuver toutes les photos en attente ?')">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="approve_all">
        <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">✓ Tout approuver</button>
    </form>
    <?php endif; ?>
</div>

<div class="flex gap-2 mb-5">
    <?php foreach (['pending'=>'En attente','approved'=>'Approuvées','all'=>'Toutes'] as $k=>$l): ?>
    <a href="?filter=<?= $k ?>" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors <?= $filter===$k ? 'bg-brand-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <?= $l ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($photos)): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">Aucune photo.</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($photos as $p):
        $src = BASE_URL . '/uploads/' . $p['file_path'];
    ?>
    <div class="bg-white rounded-2xl border <?= $p['is_approved'] ? 'border-slate-200' : 'border-amber-200' ?> shadow-sm overflow-hidden">
        <div class="h-44 bg-slate-100 relative overflow-hidden">
            <img src="<?= escape($src) ?>" alt="Photo" loading="lazy"
                 class="w-full h-full object-cover"
                 onerror="this.parentElement.innerHTML='<div class=\'flex items-center justify-center h-full text-slate-400 text-sm\'>Image introuvable</div>'">
            <?php if (!$p['is_approved']): ?>
            <div class="absolute top-2 left-2 bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">En attente</div>
            <?php endif; ?>
        </div>
        <div class="p-3">
            <p class="text-xs font-semibold text-slate-500 mb-0.5">
                <?= escape($p['username'] ?? 'Visiteur') ?> · <a href="<?= BASE_URL ?>/via/<?= escape($p['via_slug'] ?? '') ?>" target="_blank" class="text-brand-600 hover:underline"><?= escape($p['via_name'] ?? '—') ?></a>
            </p>
            <?php if (!empty($p['caption'])): ?>
            <p class="text-xs text-slate-600 mb-2 line-clamp-2"><?= escape($p['caption']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mb-3"><?= date('d/m/Y', strtotime($p['created_at'])) ?></p>
            <div class="flex gap-2">
                <?php if (!$p['is_approved']): ?>
                <form method="POST" class="flex-1">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="item_id" value="<?= $p['id'] ?>">
                    <button class="w-full px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition-colors">✓ Approuver</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Supprimer cette photo ?')" <?= $p['is_approved'] ? 'class="flex-1"' : '' ?>>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" value="<?= $p['id'] ?>">
                    <button class="w-full px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold rounded-lg transition-colors">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
