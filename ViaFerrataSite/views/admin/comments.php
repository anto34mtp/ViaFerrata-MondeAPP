<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token CSRF invalide.');
        redirect(BASE_URL . '/admin/comments');
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);

    try {
        switch ($action) {
            case 'approve':
                $pdo->prepare("UPDATE comments SET is_approved=1 WHERE id=?")->execute([$id]);
                setFlash('success', 'Commentaire approuvé.');
                break;
            case 'delete':
                $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
                setFlash('success', 'Commentaire supprimé.');
                break;
            case 'approve_all':
                $n = $pdo->exec("UPDATE comments SET is_approved=1 WHERE is_approved=0");
                setFlash('success', "$n commentaire(s) approuvé(s).");
                break;
        }
    } catch (\PDOException $e) {
        setFlash('error', 'Erreur : ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/comments');
}

// Filtres
$filter = $_GET['filter'] ?? 'pending';
$where  = $filter === 'all' ? '1=1' : 'c.is_approved=' . ($filter === 'approved' ? '1' : '0');

try {
    $comments = $pdo->query("
        SELECT c.id, c.content, c.created_at, c.is_approved, c.visitor_hash,
               v.name AS via_name, v.slug AS via_slug,
               u.username
        FROM comments c
        LEFT JOIN vias v ON c.via_id = v.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE $where
        ORDER BY c.created_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $comments = []; }

$pageTitle = 'Commentaires';
$adminCurrentPage = 'comments';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-slate-900">Commentaires
        <?php if ($navBadges['comments'] > 0): ?>
        <span class="text-base font-normal text-amber-600 ml-2"><?= $navBadges['comments'] ?> en attente</span>
        <?php endif; ?>
    </h1>
    <?php if ($navBadges['comments'] > 0): ?>
    <form method="POST" onsubmit="return confirm('Approuver tous les commentaires en attente ?')">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="approve_all">
        <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">✓ Tout approuver</button>
    </form>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="flex gap-2 mb-5">
    <?php foreach (['pending'=>'En attente','approved'=>'Approuvés','all'=>'Tous'] as $k=>$l): ?>
    <a href="?filter=<?= $k ?>" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors <?= $filter===$k ? 'bg-brand-500 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <?= $l ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="space-y-3">
    <?php if (empty($comments)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">Aucun commentaire.</div>
    <?php else: foreach ($comments as $c): ?>
    <div class="bg-white rounded-2xl border <?= $c['is_approved'] ? 'border-slate-200' : 'border-amber-200' ?> shadow-sm p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="font-semibold text-slate-800 text-sm">
                        <?= escape($c['username'] ?? 'Visiteur #' . substr($c['visitor_hash'] ?? '', 0, 6)) ?>
                    </span>
                    <span class="text-xs text-slate-400">·</span>
                    <a href="<?= BASE_URL ?>/via/<?= escape($c['via_slug'] ?? '') ?>" target="_blank"
                       class="text-xs text-brand-600 hover:underline"><?= escape($c['via_name'] ?? '—') ?></a>
                    <span class="text-xs text-slate-400">·</span>
                    <span class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span>
                    <?php if (!$c['is_approved']): ?>
                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">En attente</span>
                    <?php endif; ?>
                </div>
                <p class="text-slate-700 text-sm leading-relaxed"><?= nl2br(escape($c['content'])) ?></p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <?php if (!$c['is_approved']): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                    <button class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition-colors">✓ Approuver</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Supprimer ce commentaire ?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                    <button class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold rounded-lg transition-colors">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
