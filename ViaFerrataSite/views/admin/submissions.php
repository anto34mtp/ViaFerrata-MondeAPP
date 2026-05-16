<?php
require_once __DIR__ . '/_common.php';

function makeSlugAdmin(string $name, PDO $pdo): string {
    $slug = function_exists('iconv') ? (iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name) ?: $name) : $name;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/','-',$slug));
    $slug = trim(substr($slug,0,90),'-');
    $base = $slug; $i = 2;
    while ((int)$pdo->query("SELECT COUNT(*) FROM vias WHERE slug=".$pdo->quote($slug))->fetchColumn() > 0) {
        $slug = $base.'-'.$i++;
    }
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token CSRF invalide.');
        redirect(BASE_URL . '/admin/submissions');
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);
    $token  = $_POST['group_token'] ?? '';

    try {
        switch ($action) {
            case 'publish_single':
                $sub = $pdo->query("SELECT * FROM via_submissions WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
                if ($sub) {
                    $slug = makeSlugAdmin($sub['name'], $pdo);
                    $pdo->prepare("INSERT INTO vias (name,slug,location,latitude,longitude,difficulty,duration_hours,approach_time,return_time,elevation_gain,description,code_pays,is_active,is_approved)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,'FR',1,1)")
                        ->execute([$sub['name'],$slug,$sub['location'],$sub['latitude'],$sub['longitude'],$sub['difficulty'],$sub['duration_hours'],$sub['approach_time'],$sub['return_time'],$sub['elevation_gain'],$sub['description']]);
                    $pdo->prepare("UPDATE via_submissions SET status='approved' WHERE id=?")->execute([$id]);
                    setFlash('success', 'Proposition publiée.');
                }
                break;
            case 'reject_single':
                $pdo->prepare("UPDATE via_submissions SET status='rejected' WHERE id=?")->execute([$id]);
                setFlash('success', 'Proposition rejetée.');
                break;
            case 'reject_group':
                $pdo->prepare("UPDATE via_submissions SET status='rejected' WHERE group_token=?")->execute([$token]);
                setFlash('success', 'Groupe rejeté.');
                break;
        }
    } catch (\PDOException $e) {
        setFlash('error', 'Erreur : ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/submissions');
}

try {
    $subs = $pdo->query("
        SELECT s.*, u.username
        FROM via_submissions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.status='pending'
        ORDER BY s.group_token, s.part_number, s.created_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $subs = []; }

// Groupement
$groups = [];
foreach ($subs as $s) {
    $key = !empty($s['group_token']) ? 'g_'.$s['group_token'] : 'i_'.$s['id'];
    $groups[$key][] = $s;
}

$pageTitle = 'Propositions';
$adminCurrentPage = 'submissions';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<h1 class="text-2xl font-bold text-slate-900 mb-6">Propositions de via ferrata
    <?php if ($navBadges['submissions'] > 0): ?>
    <span class="text-base font-normal text-amber-600 ml-2"><?= $navBadges['submissions'] ?> en attente</span>
    <?php endif; ?>
</h1>

<?php if (empty($groups)): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
    Aucune proposition en attente. 🎉
</div>
<?php else: foreach ($groups as $gkey => $items):
    $isGroup = str_starts_with($gkey, 'g_');
    $gtoken  = $isGroup ? substr($gkey, 2) : '';
    $first   = $items[0];
?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-4 overflow-hidden">
    <!-- Header du groupe -->
    <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
        <div>
            <p class="font-bold text-slate-900">
                <?= $isGroup ? '⛓ Itinéraire multi-parties (' . count($items) . ' parties)' : '🧗 ' . escape($first['name']) ?>
            </p>
            <p class="text-xs text-slate-500 mt-0.5">
                Soumis par <?= escape($first['username'] ?? 'Visiteur') ?> · <?= date('d/m/Y H:i', strtotime($first['created_at'])) ?>
            </p>
        </div>
        <?php if ($isGroup): ?>
        <form method="POST" onsubmit="return confirm('Rejeter tout ce groupe ?')">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="reject_group">
            <input type="hidden" name="group_token" value="<?= escape($gtoken) ?>">
            <button class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-semibold rounded-xl transition-colors">🗑 Rejeter le groupe</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Parties -->
    <?php foreach ($items as $s): ?>
    <div class="px-5 py-4 border-b border-slate-100 last:border-0">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-slate-900"><?= escape($s['name']) ?><?= $isGroup ? ' <span class="text-slate-400 font-normal text-sm">(Partie ' . ($s['part_number'] ?? '?') . ')</span>' : '' ?></p>
                <?php if (!empty($s['location'])): ?><p class="text-sm text-slate-500">📍 <?= escape($s['location']) ?></p><?php endif; ?>
                <?php if (!empty($s['description'])): ?><p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= escape($s['description']) ?></p><?php endif; ?>
                <div class="flex flex-wrap gap-3 mt-2 text-xs text-slate-500">
                    <?php if ($s['difficulty']): ?><span>Diff. <?= (int)$s['difficulty'] ?>/10</span><?php endif; ?>
                    <?php if ($s['duration_hours']): ?><span>⏱ <?= $s['duration_hours'] ?>h</span><?php endif; ?>
                    <?php if ($s['latitude'] && $s['longitude']): ?>
                    <a href="https://www.google.com/maps?q=<?= $s['latitude'] ?>,<?= $s['longitude'] ?>" target="_blank" class="text-brand-600 hover:underline">📍 GPS</a>
                    <?php else: ?><span class="text-red-400">❌ Pas de GPS</span><?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="publish_single">
                    <input type="hidden" name="item_id" value="<?= $s['id'] ?>">
                    <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">✓ Publier</button>
                </form>
                <form method="POST" onsubmit="return confirm('Rejeter cette proposition ?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="reject_single">
                    <input type="hidden" name="item_id" value="<?= $s['id'] ?>">
                    <button class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-semibold rounded-xl transition-colors">🗑 Rejeter</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; endif; ?>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
