<?php
require_once __DIR__ . '/_common.php';

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token CSRF invalide.');
        redirect(BASE_URL . '/admin/vias');
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);

    try {
        switch ($action) {
            case 'approve':
                $pdo->prepare("UPDATE vias SET is_approved=1, approved_by=?, approved_at=NOW() WHERE id=?")
                    ->execute([$auth->getUserId(), $id]);
                setFlash('success', 'Via approuvée et publiée.');
                break;

            case 'reject':
                if ($auth->isAdmin()) {
                    $pdo->prepare("DELETE FROM vias WHERE id=?")->execute([$id]);
                    setFlash('success', 'Via supprimée.');
                }
                break;

            case 'approve_all':
                if ($auth->isAdmin()) {
                    $n = $pdo->prepare("UPDATE vias SET is_approved=1, approved_by=?, approved_at=NOW() WHERE is_approved=0");
                    $n->execute([$auth->getUserId()]);
                    setFlash('success', $n->rowCount() . ' via(s) approuvée(s).');
                }
                break;

            case 'update_closure':
                $os  = $_POST['opening_status'] ?? 'ouvert';
                $rsn = trim($_POST['closure_reason'] ?? '');
                $end = $_POST['closure_end_date'] ?? null;
                if ($os === 'ouvert') { $rsn = ''; $end = null; }
                if ($os === 'ferme_definitif') { $end = null; }
                $pdo->prepare("UPDATE vias SET opening_status=?, closure_reason=?, closure_end_date=? WHERE id=?")
                    ->execute([$os, $rsn ?: null, $end ?: null, $id]);
                setFlash('success', 'Statut d\'ouverture mis à jour.');
                break;

            case 'toggle_active':
                if ($auth->isAdmin()) {
                    $cur = (int)$pdo->query("SELECT is_active FROM vias WHERE id=$id")->fetchColumn();
                    $pdo->prepare("UPDATE vias SET is_active=? WHERE id=?")->execute([($cur ? 0 : 1), $id]);
                    setFlash('success', $cur ? 'Via masquée.' : 'Via réactivée.');
                }
                break;
        }
    } catch (\PDOException $e) {
        setFlash('error', 'Erreur : ' . $e->getMessage());
    }

    $qs = http_build_query(array_filter(['filter'=>$_POST['filter']??'','q'=>$_POST['q_back']??'']));
    redirect(BASE_URL . '/admin/vias' . ($qs ? '?'.$qs : ''));
}

// ── Filtres & données ─────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where  = '1=1';
$params = [];
if ($filter === 'pending')  { $where .= ' AND v.is_approved=0'; }
if ($filter === 'approved') { $where .= ' AND v.is_approved=1'; }
if ($filter === 'no_gps')   { $where .= ' AND (v.latitude IS NULL OR v.longitude IS NULL) AND v.is_approved=1'; }
if ($filter === 'closed')   { $where .= " AND v.opening_status IN ('ferme','ferme_definitif')"; }
if ($q !== '') {
    $where .= ' AND (v.name LIKE :q1 OR v.location LIKE :q2 OR v.slug LIKE :q3 OR v.department_id LIKE :q4)';
    $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = "%$q%";
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM vias v WHERE $where");
    $countStmt->execute($params);
    $total     = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT v.id, v.name, v.slug, v.department_id, v.location, v.difficulty,
               v.is_approved, v.is_active, v.opening_status,
               IFNULL(v.closure_reason,'') AS closure_reason,
               IFNULL(v.closure_end_date,'') AS closure_end_date,
               v.latitude, v.longitude, v.created_at,
               d.name as dept_name
        FROM vias v LEFT JOIN departments d ON v.department_id=d.code
        WHERE $where ORDER BY v.id DESC LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $vias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $vias = []; $total = 0; $totalPages = 1;
}

$diffLabels = [1=>'F',2=>'F',3=>'PD',4=>'AD',5=>'D',6=>'D',7=>'TD',8=>'TD',9=>'ED',10=>'ED'];
$diffColors = ['F'=>'bg-green-100 text-green-800','PD'=>'bg-teal-100 text-teal-800','AD'=>'bg-yellow-100 text-yellow-800','D'=>'bg-orange-100 text-orange-800','TD'=>'bg-red-100 text-red-800','ED'=>'bg-purple-100 text-purple-800'];

function vDiff(?int $d): string {
    global $diffLabels, $diffColors;
    if (!$d) return '<span class="text-slate-300 text-xs">—</span>';
    $lbl = $diffLabels[$d] ?? '?';
    $cls = $diffColors[$lbl] ?? 'bg-slate-100 text-slate-700';
    return "<span class=\"text-xs font-bold px-1.5 py-0.5 rounded $cls\">$lbl</span>";
}

$pageTitle = 'Gestion Via Ferrata';
$adminCurrentPage = 'vias';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<!-- Flash -->
<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<!-- Header + filtres -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-slate-900">Via ferrata
        <span class="text-base font-normal text-slate-400 ml-2"><?= $total ?> résultat<?= $total>1?'s':'' ?></span>
    </h1>
    <?php if ($auth->isAdmin() && $navBadges['vias'] > 0): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="approve_all">
        <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors"
                onclick="return confirm('Approuver toutes les <?= $navBadges['vias'] ?> vias en attente ?')">
            ✓ Tout approuver (<?= $navBadges['vias'] ?>)
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-slate-500 mb-1">Rechercher</label>
            <input type="text" name="q" value="<?= escape($q) ?>" placeholder="Nom, lieu, slug, dept…"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Statut</label>
            <select name="filter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                <option value="all"      <?= $filter==='all'      ?'selected':'' ?>>Toutes</option>
                <option value="pending"  <?= $filter==='pending'  ?'selected':'' ?>>En attente (<?= $navBadges['vias'] ?>)</option>
                <option value="approved" <?= $filter==='approved' ?'selected':'' ?>>Publiées</option>
                <option value="closed"   <?= $filter==='closed'   ?'selected':'' ?>>Fermées</option>
                <option value="no_gps"   <?= $filter==='no_gps'  ?'selected':'' ?>>Sans GPS</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg transition-colors">Filtrer</button>
        <?php if ($q||$filter!=='all'): ?>
        <a href="<?= BASE_URL ?>/admin/vias" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm rounded-lg transition-colors">✕ Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tableau -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Nom</th>
                    <th class="px-4 py-3">Dept</th>
                    <th class="px-4 py-3">Lieu</th>
                    <th class="px-4 py-3">Diff.</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Ouverture</th>
                    <th class="px-4 py-3">GPS</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (empty($vias)): ?>
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">Aucune via ferrata trouvée.</td></tr>
            <?php else: foreach ($vias as $v):
                $os = $v['opening_status'] ?? 'ouvert';
                $rowBg = $os === 'ferme_definitif' ? 'bg-red-50' : ($os === 'ferme' ? 'bg-amber-50' : (!$v['is_approved'] ? 'bg-amber-50/40' : ''));
            ?>
                <tr class="<?= $rowBg ?> hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3 text-slate-400 text-xs font-mono">#<?= $v['id'] ?></td>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-900 leading-tight max-w-[200px] truncate"><?= escape($v['name']) ?></p>
                        <p class="text-xs text-slate-400 font-mono truncate max-w-[200px]"><?= escape($v['slug']) ?></p>
                    </td>
                    <td class="px-4 py-3 text-slate-600 text-xs"><?= escape($v['department_id'] ?? '') ?></td>
                    <td class="px-4 py-3 text-slate-600 text-xs max-w-[120px] truncate"><?= escape($v['location'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= vDiff((int)($v['difficulty'] ?? 0)) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($v['is_approved']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Publiée</span>
                        <?php else: ?>
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">En attente</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($os === 'ferme_definitif'): ?>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-semibold">🚫 Fermée déf.</span>
                        <?php elseif ($os === 'ferme'): ?>
                        <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">⚠️ Fermée temp.</span>
                        <?php else: ?>
                        <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">Ouverte</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($v['latitude'] && $v['longitude']): ?>
                        <a href="https://www.google.com/maps?q=<?= $v['latitude'] ?>,<?= $v['longitude'] ?>" target="_blank"
                           class="text-xs text-brand-600 hover:underline">📍 Voir</a>
                        <?php else: ?>
                        <span class="text-xs text-red-400 font-medium">❌ Manquant</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <!-- Approuver -->
                            <?php if (!$v['is_approved']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="item_id" value="<?= $v['id'] ?>">
                                <input type="hidden" name="filter" value="<?= escape($filter) ?>">
                                <input type="hidden" name="q_back" value="<?= escape($q) ?>">
                                <button class="text-xs bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded-lg font-semibold transition-colors">✓ Approuver</button>
                            </form>
                            <?php endif; ?>

                            <!-- Gérer fermeture -->
                            <button onclick="openClosure(<?= htmlspecialchars(json_encode(['id'=>$v['id'],'name'=>$v['name'],'status'=>$os,'reason'=>$v['closure_reason'],'end'=>$v['closure_end_date']]),ENT_QUOTES) ?>)"
                                    class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-medium transition-colors">
                                🔒 Fermeture
                            </button>

                            <!-- Voir sur site -->
                            <a href="<?= BASE_URL ?>/via/<?= escape($v['slug']) ?>" target="_blank"
                               class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 px-2 py-1 rounded-lg font-medium transition-colors">↗</a>

                            <!-- Supprimer (admin) -->
                            <?php if ($auth->isAdmin()): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer définitivement cette via ?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="item_id" value="<?= $v['id'] ?>">
                                <input type="hidden" name="filter" value="<?= escape($filter) ?>">
                                <input type="hidden" name="q_back" value="<?= escape($q) ?>">
                                <button class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1 rounded-lg font-medium transition-colors">🗑</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <span class="text-xs text-slate-500">Page <?= $page ?> / <?= $totalPages ?></span>
        <div class="flex gap-1">
            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
            <a href="?<?= http_build_query(['filter'=>$filter,'q'=>$q,'page'=>$p]) ?>"
               class="px-3 py-1 text-sm rounded-lg <?= $p===$page ? 'bg-brand-500 text-white font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' ?> transition-colors">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modale fermeture -->
<div id="closureModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)closeClosure()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-slate-900 text-lg mb-1">Gérer la fermeture</h3>
        <p id="cModalName" class="text-sm text-slate-500 mb-5"></p>
        <form method="POST" id="closureForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_closure">
            <input type="hidden" name="item_id" id="cModalId">
            <input type="hidden" name="filter" value="<?= escape($filter) ?>">
            <input type="hidden" name="q_back" value="<?= escape($q) ?>">

            <div class="space-y-3 mb-5">
                <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                    <input type="radio" name="opening_status" value="ouvert" onchange="toggleClosure(this.value)" class="accent-green-600">
                    <span class="font-semibold text-slate-800">✅ Ouverte</span>
                </label>
                <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                    <input type="radio" name="opening_status" value="ferme" onchange="toggleClosure(this.value)" class="accent-amber-600">
                    <span class="font-semibold text-slate-800">⚠️ Fermée temporairement</span>
                </label>
                <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                    <input type="radio" name="opening_status" value="ferme_definitif" onchange="toggleClosure(this.value)" class="accent-red-600">
                    <span class="font-semibold text-slate-800">🚫 Fermée définitivement</span>
                </label>
            </div>

            <div id="cReasonBlock" class="hidden mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Raison de la fermeture</label>
                <textarea id="cReason" name="closure_reason" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500" placeholder="Travaux, danger, arrêté municipal…"></textarea>
            </div>
            <div id="cEndBlock" class="hidden mb-5">
                <label class="block text-sm font-medium text-slate-700 mb-1">Date de réouverture prévue</label>
                <input type="date" id="cEnd" name="closure_end_date" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2 rounded-xl transition-colors">Enregistrer</button>
                <button type="button" onclick="closeClosure()" class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 rounded-xl transition-colors">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openClosure(data) {
    document.getElementById('cModalName').textContent = data.name;
    document.getElementById('cModalId').value = data.id;
    document.getElementById('cReason').value = data.reason || '';
    document.getElementById('cEnd').value = data.end || '';
    document.querySelectorAll('[name="opening_status"]').forEach(r => r.checked = (r.value === (data.status || 'ouvert')));
    toggleClosure(data.status || 'ouvert');
    document.getElementById('closureModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeClosure() {
    document.getElementById('closureModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function toggleClosure(v) {
    document.getElementById('cReasonBlock').classList.toggle('hidden', v === 'ouvert');
    document.getElementById('cEndBlock').classList.toggle('hidden', v !== 'ferme');
}
</script>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
