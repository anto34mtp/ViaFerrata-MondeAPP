<?php
require_once __DIR__ . '/_common.php';

// Stats
try {
    $stats = [
        'total_vias'   => (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE is_approved=1")->fetchColumn(),
        'pending_vias' => (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE is_approved=0")->fetchColumn(),
        'closed'       => (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE opening_status IN ('ferme','ferme_definitif')")->fetchColumn(),
        'no_gps'       => (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE is_approved=1 AND (latitude IS NULL OR longitude IS NULL)")->fetchColumn(),
    ];
} catch (\PDOException $e) {
    $stats = ['total_vias'=>0,'pending_vias'=>0,'closed'=>0,'no_gps'=>0];
}

try {
    $recentVias = $pdo->query("SELECT id, name, slug, is_approved, opening_status, created_at FROM vias ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $recentVias = []; }

$pageTitle = 'Dashboard Admin';
$adminCurrentPage = '';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_nav.php';
?>

<!-- Flash -->
<?php if ($flashSuccess): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"><?= escape($flashError) ?></div><?php endif; ?>

<?php if ($autoStatusUpdated > 0): ?>
<div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    <span>Mise à jour automatique : <strong><?= $autoStatusUpdated ?> via<?= $autoStatusUpdated > 1 ? 's' : '' ?></strong> ont changé de statut selon leur période d'ouverture.</span>
</div>
<?php endif; ?>

<h1 class="text-2xl font-bold text-slate-900 mb-6">Tableau de bord</h1>

<!-- Stat cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <?php
    $cards = [
        ['label'=>'Via ferrata',       'val'=>$stats['total_vias'],   'sub'=>'publiées',          'bg'=>'bg-brand-50',   'txt'=>'text-brand-700',   'border'=>'border-brand-200',  'href'=>BASE_URL.'/admin/vias'],
        ['label'=>'En attente',        'val'=>$stats['pending_vias'], 'sub'=>'à approuver',       'bg'=>'bg-amber-50',   'txt'=>'text-amber-700',   'border'=>'border-amber-200',  'href'=>BASE_URL.'/admin/vias?filter=pending'],
        ['label'=>'Commentaires',      'val'=>$navBadges['comments'], 'sub'=>'à modérer',         'bg'=>'bg-blue-50',    'txt'=>'text-blue-700',    'border'=>'border-blue-200',   'href'=>BASE_URL.'/admin/comments'],
        ['label'=>'Photos',            'val'=>$navBadges['photos'],   'sub'=>'à modérer',         'bg'=>'bg-purple-50',  'txt'=>'text-purple-700',  'border'=>'border-purple-200', 'href'=>BASE_URL.'/admin/photos'],
        ['label'=>'Propositions',      'val'=>$navBadges['submissions'],'sub'=>'en attente',      'bg'=>'bg-rose-50',    'txt'=>'text-rose-700',    'border'=>'border-rose-200',   'href'=>BASE_URL.'/admin/submissions'],
    ];
    foreach ($cards as $c): ?>
    <a href="<?= $c['href'] ?>" class="<?= $c['bg'] ?> border <?= $c['border'] ?> rounded-2xl p-4 flex flex-col gap-1 hover:shadow-md transition-shadow">
        <span class="text-3xl font-bold <?= $c['txt'] ?>"><?= $c['val'] ?></span>
        <span class="text-sm font-semibold text-slate-700"><?= $c['label'] ?></span>
        <span class="text-xs text-slate-500"><?= $c['sub'] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Menu navigation rapide -->
<h2 class="text-lg font-bold text-slate-800 mb-4">Navigation rapide</h2>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
    <?php
    $menuItems = [
        ['href'=>BASE_URL.'/admin/vias',        'icon'=>'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'label'=>'Via ferrata',  'badge'=>$navBadges['vias']],
        ['href'=>BASE_URL.'/admin/comments',    'icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'label'=>'Commentaires', 'badge'=>$navBadges['comments']],
        ['href'=>BASE_URL.'/admin/photos',      'icon'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'label'=>'Photos',        'badge'=>$navBadges['photos']],
        ['href'=>BASE_URL.'/admin/submissions', 'icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label'=>'Propositions',  'badge'=>$navBadges['submissions']],
        ['href'=>BASE_URL.'/admin/users',       'icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label'=>'Utilisateurs',  'badge'=>0],
        ['href'=>BASE_URL.'/admin/vias?filter=no_gps', 'icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z', 'label'=>'GPS manquant',  'badge'=>$stats['no_gps']],
    ];
    foreach ($menuItems as $m): ?>
    <a href="<?= $m['href'] ?>" class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col items-center gap-2 hover:shadow-md hover:border-brand-300 transition-all relative group">
        <?php if ($m['badge'] > 0): ?>
        <span class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $m['badge'] ?></span>
        <?php endif; ?>
        <svg class="w-7 h-7 text-slate-500 group-hover:text-brand-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $m['icon'] ?>"/>
        </svg>
        <span class="text-sm font-semibold text-slate-700 text-center leading-tight"><?= $m['label'] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Vias récentes -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-bold text-slate-900">Dernières via ferrata ajoutées</h2>
        <a href="<?= BASE_URL ?>/admin/vias" class="text-xs text-brand-600 hover:underline font-medium">Voir tout →</a>
    </div>
    <div class="divide-y divide-slate-100">
        <?php if (empty($recentVias)): ?>
        <p class="px-5 py-6 text-slate-400 text-sm text-center">Aucune via ferrata en base.</p>
        <?php else: foreach ($recentVias as $v):
            $status = $v['opening_status'] ?? 'ouvert';
        ?>
        <div class="px-5 py-3 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="font-medium text-slate-900 text-sm truncate"><?= escape($v['name']) ?></p>
                <p class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($v['created_at'])) ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if (!$v['is_approved']): ?>
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">En attente</span>
                <?php else: ?>
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Publiée</span>
                <?php endif; ?>
                <?php if ($status !== 'ouvert'): ?>
                <span class="text-xs <?= $status==='ferme_definitif' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' ?> px-2 py-0.5 rounded-full font-semibold">
                    <?= $status==='ferme_definitif' ? 'Fermée déf.' : 'Fermée temp.' ?>
                </span>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/admin/vias?q=<?= urlencode($v['name']) ?>" class="text-xs text-slate-400 hover:text-brand-600">Gérer →</a>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_nav_end.php'; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
