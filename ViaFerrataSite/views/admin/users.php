<?php
require_once __DIR__ . '/_common.php';
if (!$auth->isAdmin()) {
    setFlash('error', 'Réservé aux administrateurs.');
    redirect(BASE_URL . '/admin');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token CSRF invalide.');
        redirect(BASE_URL . '/admin/users');
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['item_id'] ?? 0);

    if ($id === $auth->getUserId()) {
        setFlash('error', 'Vous ne pouvez pas modifier votre propre compte.');
        redirect(BASE_URL . '/admin/users');
    }

    try {
        switch ($action) {
            case 'set_role':
                $role = $_POST['role'] ?? 'member';
                if (!in_array($role, ['member','modo','admin'])) break;
                $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
                setFlash('success', 'Rôle mis à jour.');
                break;
            case 'toggle_active':
                $cur = (int)$pdo->query("SELECT is_active FROM users WHERE id=$id")->fetchColumn();
                $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([($cur?0:1), $id]);
                setFlash('success', $cur ? 'Utilisateur désactivé.' : 'Utilisateur réactivé.');
                break;
            case 'delete':
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                setFlash('success', 'Utilisateur supprimé.');
                break;
        }
    } catch (\PDOException $e) {
        setFlash('error', 'Erreur : ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/users');
}

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = '1=1';
$params = [];
if ($q) { $where .= ' AND (username LIKE :q1 OR email LIKE :q2)'; $params['q1'] = $params['q2'] = "%$q%"; }
if ($filter === 'admin') $where .= " AND role='admin'";
if ($filter === 'modo')  $where .= " AND role='modo'";
if ($filter === 'inactive') $where .= ' AND is_active=0';

try {
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active, created_at FROM users WHERE $where ORDER BY id DESC LIMIT 200");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $users = []; }

$roleColors = ['admin'=>'bg-red-100 text-red-700','modo'=>'bg-blue-100 text-blue-700','member'=>'bg-slate-100 text-slate-600'];

$pageTitle = 'Utilisateurs';
$adminCurrentPage = 'users';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<h1 class="text-2xl font-bold text-slate-900 mb-5">Utilisateurs
    <span class="text-base font-normal text-slate-400 ml-2"><?= count($users) ?> résultat<?= count($users)>1?'s':'' ?></span>
</h1>

<!-- Filtres -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-slate-500 mb-1">Rechercher</label>
            <input type="text" name="q" value="<?= escape($q) ?>" placeholder="Nom, email…"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Rôle</label>
            <select name="filter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none">
                <option value="all"      <?= $filter==='all'     ?'selected':'' ?>>Tous</option>
                <option value="admin"    <?= $filter==='admin'   ?'selected':'' ?>>Admins</option>
                <option value="modo"     <?= $filter==='modo'    ?'selected':'' ?>>Modérateurs</option>
                <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Désactivés</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg transition-colors">Filtrer</button>
        <?php if ($q||$filter!=='all'): ?>
        <a href="<?= BASE_URL ?>/admin/users" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm rounded-lg transition-colors">✕ Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Utilisateur</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Rôle</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Inscrit le</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (empty($users)): ?>
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun utilisateur trouvé.</td></tr>
            <?php else: foreach ($users as $u):
                $isSelf = (int)$u['id'] === $auth->getUserId();
            ?>
                <tr class="hover:bg-slate-50 transition-colors <?= !$u['is_active'] ? 'opacity-60' : '' ?>">
                    <td class="px-4 py-3 text-slate-400 text-xs font-mono">#<?= $u['id'] ?></td>
                    <td class="px-4 py-3 font-semibold text-slate-900">
                        <?= escape($u['username']) ?>
                        <?php if ($isSelf): ?><span class="text-xs text-brand-600 font-normal ml-1">(vous)</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= escape($u['email']) ?></td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $roleColors[$u['role']] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= escape($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($u['is_active']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Actif</span>
                        <?php else: ?>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-semibold">Désactivé</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-500 text-xs"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td class="px-4 py-3">
                        <?php if (!$isSelf): ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Changer le rôle -->
                            <form method="POST" class="flex items-center gap-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="item_id" value="<?= $u['id'] ?>">
                                <select name="role" class="text-xs border border-slate-300 rounded-lg px-2 py-1 outline-none">
                                    <option value="member" <?= $u['role']==='member'?'selected':'' ?>>member</option>
                                    <option value="modo"   <?= $u['role']==='modo'  ?'selected':'' ?>>modo</option>
                                    <option value="admin"  <?= $u['role']==='admin' ?'selected':'' ?>>admin</option>
                                </select>
                                <button class="text-xs bg-brand-500 hover:bg-brand-600 text-white px-2 py-1 rounded-lg transition-colors">OK</button>
                            </form>

                            <!-- Activer/désactiver -->
                            <form method="POST" onsubmit="return confirm('<?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?> cet utilisateur ?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="item_id" value="<?= $u['id'] ?>">
                                <button class="text-xs <?= $u['is_active'] ? 'bg-orange-50 hover:bg-orange-100 text-orange-600' : 'bg-green-50 hover:bg-green-100 text-green-600' ?> px-2 py-1 rounded-lg transition-colors font-medium">
                                    <?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?>
                                </button>
                            </form>

                            <!-- Supprimer -->
                            <form method="POST" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= $u['id'] ?>">
                                <button class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1 rounded-lg transition-colors">🗑</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-slate-400 italic">Votre compte</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
