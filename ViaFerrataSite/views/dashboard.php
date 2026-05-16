<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();
$auth->requireAuth(BASE_URL . '/connexion');

$favoriteModel = new Favorite();
$commentModel  = new Comment();
$logbookModel  = new Logbook();
$userModel     = new User();
$userId        = $auth->getUserId();

// ── POST handlers ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('dash_msg', 'Token de sécurité invalide.'); setFlash('dash_type', 'error');
        redirect(BASE_URL . '/mon-espace');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $err = [];
        if (mb_strlen($username) < 3 || mb_strlen($username) > 20) $err[] = 'Le pseudo doit faire entre 3 et 20 caractères.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))             $err[] = 'Email invalide.';
        if ($err) {
            setFlash('dash_msg', implode(' ', $err)); setFlash('dash_type', 'error');
        } else {
            $ok = $userModel->update($userId, ['username' => $username, 'email' => $email]);
            if ($ok) { $_SESSION['username'] = $username; setFlash('dash_msg', 'Profil mis à jour.'); setFlash('dash_type', 'success'); }
            else      { setFlash('dash_msg', 'Ce pseudo ou email est déjà utilisé.'); setFlash('dash_type', 'error'); }
        }
        redirect(BASE_URL . '/mon-espace?tab=profil');
    }

    if ($action === 'change_password') {
        $cur = $_POST['current_password']  ?? '';
        $new = $_POST['new_password']      ?? '';
        $cnf = $_POST['confirm_password']  ?? '';
        $full = $userModel->getByUsername($auth->getUsername());
        if (!$full || !$userModel->verifyPassword($cur, $full['password_hash'])) {
            setFlash('dash_msg', 'Mot de passe actuel incorrect.'); setFlash('dash_type', 'error');
        } elseif (strlen($new) < 8) {
            setFlash('dash_msg', 'Le nouveau mot de passe doit contenir au moins 8 caractères.'); setFlash('dash_type', 'error');
        } elseif ($new !== $cnf) {
            setFlash('dash_msg', 'Les mots de passe ne correspondent pas.'); setFlash('dash_type', 'error');
        } else {
            $ok = $userModel->changePassword($userId, $new);
            setFlash('dash_msg', $ok ? 'Mot de passe modifié.' : 'Erreur lors du changement.'); setFlash('dash_type', $ok ? 'success' : 'error');
        }
        redirect(BASE_URL . '/mon-espace?tab=profil');
    }
}

// ── Data ──────────────────────────────────────────────────────────────────
$todoVias       = $favoriteModel->getByUser($userId, 'to_do');
$doneVias       = $favoriteModel->getByUser($userId, 'done');
$myComments     = $commentModel->getByUser($userId);
$logbookEntries = $logbookModel->getByUser($userId);
$userInfo       = $userModel->getById($userId);

$logbookByVia = [];
foreach ($logbookEntries as $le) { $logbookByVia[(int)$le['via_id']] = $le; }

$doneCount    = count($doneVias);
$todoCount    = count($todoVias);
$commentCount = count($myComments);
$logbookCount = count($logbookEntries);
$doneThisYear = $logbookModel->countThisYear($userId);

// ── Level ──────────────────────────────────────────────────────────────────
$levelsMap = [
    ['min'=>100, 'name'=>'Légende',    'next'=>null, 'icon'=>'crown',   'color'=>'violet'],
    ['min'=>50,  'name'=>'Expert',     'next'=>100,  'icon'=>'pickaxe', 'color'=>'amber'],
    ['min'=>25,  'name'=>'Grimpeur',   'next'=>50,   'icon'=>'mountain','color'=>'orange'],
    ['min'=>10,  'name'=>'Confirmé',   'next'=>25,   'icon'=>'flag',    'color'=>'brand'],
    ['min'=>3,   'name'=>'Explorateur','next'=>10,   'icon'=>'compass', 'color'=>'blue'],
    ['min'=>1,   'name'=>'Débutant',   'next'=>3,    'icon'=>'boot',    'color'=>'slate'],
    ['min'=>0,   'name'=>'Novice',     'next'=>1,    'icon'=>'star',    'color'=>'slate'],
];
$lvl = end($levelsMap);
foreach ($levelsMap as $l) { if ($doneCount >= $l['min']) { $lvl = $l; break; } }
$lvlProgress = 0;
if ($lvl['next'] !== null) {
    $range = $lvl['next'] - $lvl['min'];
    $lvlProgress = $range > 0 ? min(100, (int)(($doneCount - $lvl['min']) / $range * 100)) : 100;
}

// ── Achievements ───────────────────────────────────────────────────────────
$badges = [
    ['key'=>'first',   'label'=>'Premier Pas',     'desc'=>'1ère via complétée',    'done'=>$doneCount>=1],
    ['key'=>'explo',   'label'=>'Explorateur',      'desc'=>'3 vias complétées',     'done'=>$doneCount>=3],
    ['key'=>'ten',     'label'=>'Confirmé',          'desc'=>'10 vias complétées',    'done'=>$doneCount>=10],
    ['key'=>'climb',   'label'=>'Grimpeur',          'desc'=>'25 vias complétées',    'done'=>$doneCount>=25],
    ['key'=>'expert',  'label'=>'Expert',            'desc'=>'50 vias complétées',    'done'=>$doneCount>=50],
    ['key'=>'legend',  'label'=>'Légende',           'desc'=>'100 vias complétées',   'done'=>$doneCount>=100],
    ['key'=>'journal', 'label'=>'Chroniqueur',       'desc'=>'1ère note de carnet',   'done'=>$logbookCount>=1],
    ['key'=>'critic',  'label'=>'Critique',          'desc'=>'1er commentaire',        'done'=>$commentCount>=1],
    ['key'=>'veteran', 'label'=>'Vétéran',           'desc'=>'10 commentaires',        'done'=>$commentCount>=10],
];

// ── Avatar color (hash-based) ──────────────────────────────────────────────
$palette = ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#f97316','#ec4899','#84cc16','#14b8a6'];
$avatarColor  = $palette[abs(crc32($auth->getUsername())) % count($palette)];
$avatarLetter = mb_strtoupper(mb_substr($auth->getUsername(), 0, 1));

// ── Timeline (group done vias by year) ────────────────────────────────────
$timelineByYear = [];
foreach ($doneVias as $v) {
    $le      = $logbookByVia[(int)$v['via_id']] ?? null;
    $dateStr = ($le && !empty($le['done_date'])) ? $le['done_date'] : $v['updated_at'];
    $year    = (int)date('Y', strtotime($dateStr));
    $timelineByYear[$year][] = ['via' => $v, 'logbook' => $le, 'date' => $dateStr];
}
krsort($timelineByYear);

// ── Active tab ────────────────────────────────────────────────────────────
$tripModel    = new RoadTrip();
$myTrips      = $tripModel->getByUser($userId);
$sharedTrips  = $tripModel->getSharedTrips($userId);
$tripCount    = count($myTrips) + count($sharedTrips);
$activeTab    = in_array($_GET['tab'] ?? '', ['carnet','todo','comments','profil','trips']) ? ($_GET['tab'] ?? 'carnet') : 'carnet';
$flashMsg   = getFlash('dash_msg');
$flashType  = getFlash('dash_type') ?? 'success';
$csrfToken  = $auth->generateCsrfToken();
$pageTitle  = 'Mon Espace';

require_once __DIR__ . '/../includes/header.php';

// ── SVG icon helper ────────────────────────────────────────────────────────
function dashIcon(string $name, string $cls = 'w-5 h-5'): string {
    $icons = [
        'book'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>',
        'flag'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 9m0 6V9"/>',
        'chat'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>',
        'user'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>',
        'plus'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>',
        'pencil'   => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>',
        'trash'    => '<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>',
        'check'    => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>',
        'map-pin'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>',
        'star'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>',
        'lock'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>',
        'eye'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
        'x'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>',
        'clock'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        'users'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>',
        'cloud'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z"/>',
        'warning'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
    ];
    $d = $icons[$name] ?? '<path d="M12 12"/>';
    return "<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"{$cls}\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\" stroke-width=\"1.75\">{$d}</svg>";
}

function conditionEmoji(string $c): string {
    return match($c) {
        'soleil'  => '☀️', 'nuageux' => '⛅', 'pluie' => '🌧️',
        'vent'    => '💨', 'brume'   => '🌫️', 'neige' => '❄️', default => '🌤️'
    };
}
?>

<!-- ═══════════════════════════════════════════════════════════════════════
     FLASH MESSAGE
══════════════════════════════════════════════════════════════════════════ -->
<?php if ($flashMsg): ?>
<div id="flash-bar" class="fixed top-16 inset-x-0 z-40 flex justify-center px-4 pt-3 pointer-events-none">
    <div class="pointer-events-auto flex items-center gap-3 px-5 py-3 rounded-xl shadow-lg text-sm font-medium
        <?= $flashType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
        <?= $flashType === 'success'
            ? '<span class="text-emerald-500">' . dashIcon('check','w-4 h-4') . '</span>'
            : '<span class="text-red-500">'     . dashIcon('warning','w-4 h-4') . '</span>' ?>
        <?= escape($flashMsg) ?>
        <button onclick="this.closest('#flash-bar').remove()" class="ml-2 opacity-50 hover:opacity-100"><?= dashIcon('x','w-3.5 h-3.5') ?></button>
    </div>
</div>
<script>setTimeout(()=>{ const f=document.getElementById('flash-bar'); if(f) f.remove(); }, 5000);</script>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════
     PROFILE HERO
══════════════════════════════════════════════════════════════════════════ -->
<div class="relative bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 overflow-hidden">

    <!-- Mountain silhouette decoration -->
    <svg class="absolute bottom-0 left-0 right-0 w-full opacity-10" viewBox="0 0 1200 160" fill="white" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M0 160 L120 60 L200 110 L320 20 L420 90 L540 10 L640 80 L720 30 L820 100 L920 40 L1040 85 L1120 25 L1200 70 L1200 160Z"/>
    </svg>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-0">
        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-5 pb-8">

            <!-- Avatar -->
            <div class="relative flex-shrink-0">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white font-bold text-3xl shadow-xl ring-4 ring-white/10"
                     style="background:<?= $avatarColor ?>">
                    <?= escape($avatarLetter) ?>
                </div>
                <?php if ($auth->isModerator()): ?>
                <span class="absolute -bottom-1.5 -right-1.5 bg-amber-400 text-slate-900 text-[10px] font-bold px-1.5 py-0.5 rounded-md leading-none">MODO</span>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight"><?= escape($auth->getUsername()) ?></h1>
                <p class="text-emerald-300 text-sm mt-0.5"><?= escape($userInfo['email'] ?? '') ?></p>
                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                        <?= dashIcon('calendar','w-3.5 h-3.5') ?>
                        Membre depuis <?= date('M Y', strtotime($userInfo['created_at'] ?? 'now')) ?>
                    </span>
                    <span class="inline-flex items-center gap-1 bg-white/10 backdrop-blur text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                        <?= escape($lvl['name']) ?>
                    </span>
                </div>
            </div>

            <!-- Level widget (desktop right) -->
            <div class="hidden lg:block bg-white/10 backdrop-blur border border-white/20 rounded-2xl p-4 min-w-[200px]">
                <div class="flex justify-between text-xs text-slate-300 mb-1.5">
                    <span class="font-semibold text-white"><?= escape($lvl['name']) ?></span>
                    <?php if ($lvl['next'] !== null): ?>
                    <span><?= $doneCount ?>/<?= $lvl['next'] ?> vias</span>
                    <?php else: ?>
                    <span>Niveau max !</span>
                    <?php endif; ?>
                </div>
                <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-300 rounded-full transition-all duration-700"
                         style="width:<?= $lvlProgress ?>%"></div>
                </div>
                <?php if ($lvl['next'] !== null): ?>
                <p class="text-[11px] text-slate-400 mt-1.5">
                    <?= $lvl['next'] - $doneCount ?> vias pour <?= escape($levelsMap[array_search($lvl, $levelsMap) - 1]['name'] ?? '') ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-white/10 rounded-t-2xl overflow-hidden border border-b-0 border-white/10">
            <?php
            $stats = [
                ['label'=>'Vias faites',       'val'=>$doneCount,    'icon'=>'check'],
                ['label'=>'Liste à faire',      'val'=>$todoCount,    'icon'=>'flag'],
                ['label'=>'Notes carnet',       'val'=>$logbookCount, 'icon'=>'book'],
                ['label'=>'Commentaires',       'val'=>$commentCount, 'icon'=>'chat'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="bg-white/5 hover:bg-white/10 transition-colors px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5 text-emerald-400 mb-1">
                    <?= dashIcon($s['icon'], 'w-4 h-4') ?>
                </div>
                <div class="text-xl font-bold text-white"><?= $s['val'] ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5"><?= $s['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     STICKY TAB BAR
══════════════════════════════════════════════════════════════════════════ -->
<div class="bg-white border-b border-slate-200 sticky top-16 z-30 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex" aria-label="Navigation espace personnel">
            <?php
            $tabs = [
                ['id'=>'carnet',   'label'=>t('dash_logbook'),  'icon'=>'book',     'count'=>$doneCount],
                ['id'=>'todo',     'label'=>t('dash_bucket'),   'icon'=>'flag',     'count'=>$todoCount],
                ['id'=>'trips',    'label'=>t('dash_trips'),    'icon'=>'map-pin',  'count'=>$tripCount],
                ['id'=>'comments', 'label'=>t('dash_comments'), 'icon'=>'chat',     'count'=>$commentCount],
                ['id'=>'profil',   'label'=>t('dash_profile'),  'icon'=>'user',     'count'=>null],
            ];
            foreach ($tabs as $t):
                $active = $activeTab === $t['id'];
            ?>
            <button onclick="switchTab('<?= $t['id'] ?>')"
                    id="tab-<?= $t['id'] ?>"
                    aria-selected="<?= $active ? 'true' : 'false' ?>"
                    class="tab-btn group flex items-center gap-2 px-3 sm:px-5 py-4 text-sm font-medium border-b-2 transition-all
                        <?= $active
                            ? 'border-brand-500 text-brand-600'
                            : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' ?>">
                <span class="<?= $active ? 'text-brand-500' : 'text-slate-400 group-hover:text-slate-500' ?>">
                    <?= dashIcon($t['icon'], 'w-4 h-4') ?>
                </span>
                <span class="hidden sm:inline"><?= $t['label'] ?></span>
                <?php if ($t['count'] !== null && $t['count'] > 0): ?>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold
                    <?= $active ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200' ?>">
                    <?= $t['count'] ?>
                </span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     PANELS
══════════════════════════════════════════════════════════════════════════ -->
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

<!-- ──────────────────────────────────────────────────────────────────────
     PANEL: CARNET DE BORD
──────────────────────────────────────────────────────────────────────── -->
<div id="panel-carnet" class="<?= $activeTab !== 'carnet' ? 'hidden' : '' ?>">
    <div class="grid lg:grid-cols-[280px_1fr] gap-6">

        <!-- LEFT: Progression + Badges -->
        <div class="space-y-4">

            <!-- Level card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="font-semibold text-slate-800 mb-4 text-sm uppercase tracking-wide">Progression</h3>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow">
                        <?= dashIcon('flag','w-6 h-6 text-white') ?>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900"><?= escape($lvl['name']) ?></div>
                        <div class="text-xs text-slate-500"><?= $doneCount ?> via<?= $doneCount !== 1 ? 's' : '' ?> complétée<?= $doneCount !== 1 ? 's' : '' ?></div>
                    </div>
                </div>
                <?php if ($lvl['next'] !== null): ?>
                <div>
                    <div class="flex justify-between text-xs text-slate-500 mb-1.5">
                        <span>Vers <?= escape($levelsMap[max(0, array_search($lvl, $levelsMap) - 1)]['name'] ?? '') ?></span>
                        <span><?= $lvlProgress ?>%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 rounded-full transition-all duration-700"
                             style="width:<?= $lvlProgress ?>%"></div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2"><?= $lvl['next'] - $doneCount ?> vias restantes</p>
                </div>
                <?php else: ?>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center text-sm text-amber-700 font-medium">
                    🏆 Niveau maximum atteint !
                </div>
                <?php endif; ?>

                <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-center">
                    <div>
                        <div class="text-xl font-bold text-slate-900"><?= $doneThisYear ?></div>
                        <div class="text-[11px] text-slate-400">Cette année</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold text-slate-900"><?= $logbookCount ?></div>
                        <div class="text-[11px] text-slate-400">Notes carnet</div>
                    </div>
                </div>
            </div>

            <!-- Achievements -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="font-semibold text-slate-800 mb-4 text-sm uppercase tracking-wide">Badges</h3>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($badges as $b): ?>
                    <div class="group relative flex flex-col items-center gap-1 p-2 rounded-xl <?= $b['done'] ? 'bg-emerald-50 border border-emerald-100' : 'bg-slate-50 border border-slate-100 opacity-50 grayscale' ?> transition-all cursor-default"
                         title="<?= escape($b['label']) ?> — <?= escape($b['desc']) ?>">
                        <span class="text-xl leading-none"><?= match($b['key']) {
                            'first'   => '🥾',
                            'explo'   => '🧭',
                            'ten'     => '🏔️',
                            'climb'   => '🧗',
                            'expert'  => '⛏️',
                            'legend'  => '👑',
                            'journal' => '📔',
                            'critic'  => '💬',
                            'veteran' => '🌟',
                            default   => '🏅'
                        } ?></span>
                        <span class="text-[9px] font-medium text-center leading-tight <?= $b['done'] ? 'text-emerald-700' : 'text-slate-400' ?>">
                            <?= escape($b['label']) ?>
                        </span>
                        <?php if ($b['done']): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-emerald-400 rounded-full"></span>
                        <?php endif; ?>
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-slate-900 text-white text-[10px] rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                            <?= escape($b['desc']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Timeline -->
        <div>
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-bold text-slate-900">Mes sorties</h2>
                <?php if ($doneCount > 0): ?>
                <button onclick="openLogbookModal(null, null)" class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl shadow-sm transition-colors">
                    <?= dashIcon('plus','w-4 h-4') ?> Ajouter une sortie
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($doneVias)): ?>
            <!-- Empty state -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
                <svg class="mx-auto w-20 h-20 text-slate-200 mb-4" viewBox="0 0 100 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 70 L35 20 L50 45 L65 10 L90 70Z" fill="currentColor" opacity=".4"/>
                    <path d="M0 70 L25 35 L40 55 L55 25 L70 50 L85 30 L100 70Z" fill="currentColor"/>
                </svg>
                <h3 class="font-bold text-slate-700 text-lg">Aucune via complétée</h3>
                <p class="text-slate-400 text-sm mt-2 mb-5">Commencez par explorer les via ferrata et marquer vos premières sorties.</p>
                <a href="<?= BASE_URL ?>/france" class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                    <?= dashIcon('map-pin','w-4 h-4') ?> Explorer les vias
                </a>
            </div>

            <?php else: ?>
            <!-- Timeline -->
            <div class="space-y-8">
                <?php foreach ($timelineByYear as $year => $entries): ?>
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="h-px flex-1 bg-slate-200"></div>
                        <span class="text-sm font-bold text-slate-500 tracking-wider"><?= (int)$year ?></span>
                        <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= count($entries) ?> sortie<?= count($entries) > 1 ? 's' : '' ?></span>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($entries as $entry):
                            $v  = $entry['via'];
                            $le = $entry['logbook'];
                            $imgUrl = !empty($v['image_url']) ? escape($v['image_url']) : BASE_URL.'/assets/images/default.png';
                            $dateLabel = $le && !empty($le['done_date'])
                                ? date('d/m/Y', strtotime($le['done_date']))
                                : date('d/m/Y', strtotime($entry['date']));
                        ?>
                        <div class="group bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden flex">

                            <!-- Image -->
                            <a href="<?= BASE_URL ?>/france/<?= escape($v['slug']) ?>" class="flex-shrink-0 w-28 sm:w-36 overflow-hidden">
                                <img src="<?= $imgUrl ?>" alt="<?= escape($v['name']) ?>"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                     onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                            </a>

                            <!-- Content -->
                            <div class="flex-1 p-4 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <a href="<?= BASE_URL ?>/france/<?= escape($v['slug']) ?>"
                                           class="font-semibold text-slate-900 hover:text-brand-600 transition-colors line-clamp-1 leading-snug">
                                            <?= escape($v['name']) ?>
                                        </a>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-400">
                                            <span class="flex items-center gap-1">
                                                <?= dashIcon('calendar','w-3 h-3') ?><?= $dateLabel ?>
                                            </span>
                                            <?php if (!empty($v['location'])): ?>
                                            <span class="flex items-center gap-1">
                                                <?= dashIcon('map-pin','w-3 h-3') ?><?= escape($v['location']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($le && !empty($le['conditions'])): ?>
                                            <span><?= conditionEmoji($le['conditions']) ?> <?= escape(ucfirst($le['conditions'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex-shrink-0 flex gap-1.5">
                                        <?php
                                        $leData = $le ? htmlspecialchars(json_encode([
                                            'id'         => $le['id'],
                                            'done_date'  => $le['done_date']  ?? '',
                                            'conditions' => $le['conditions'] ?? '',
                                            'companion'  => $le['companion']  ?? '',
                                            'notes'      => $le['notes']      ?? '',
                                        ]), ENT_QUOTES, 'UTF-8') : 'null';
                                        ?>
                                        <button onclick="openLogbookModal(<?= (int)$v['via_id'] ?>, <?= htmlspecialchars(json_encode($v['name']), ENT_QUOTES) ?>, <?= $leData ?>)"
                                                class="p-1.5 text-slate-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                                title="<?= $le ? 'Modifier' : 'Ajouter des notes' ?>">
                                            <?= dashIcon('pencil','w-4 h-4') ?>
                                        </button>
                                        <?php if ($le): ?>
                                        <button onclick="deleteLogbookEntry(<?= (int)$le['id'] ?>, this)"
                                                class="p-1.5 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Supprimer cette note">
                                            <?= dashIcon('trash','w-4 h-4') ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Notes preview -->
                                <?php if ($le && !empty($le['notes'])): ?>
                                <p class="mt-2 text-sm text-slate-600 line-clamp-2 bg-slate-50 rounded-lg px-3 py-2 border-l-2 border-brand-300">
                                    <?= nl2br(escape($le['notes'])) ?>
                                </p>
                                <?php endif; ?>

                                <!-- Companion -->
                                <?php if ($le && !empty($le['companion'])): ?>
                                <p class="mt-1.5 text-xs text-slate-400 flex items-center gap-1">
                                    <?= dashIcon('users','w-3 h-3') ?> Avec <?= escape($le['companion']) ?>
                                </p>
                                <?php endif; ?>

                                <!-- No logbook entry CTA -->
                                <?php if (!$le): ?>
                                <button onclick="openLogbookModal(<?= (int)$v['via_id'] ?>, <?= htmlspecialchars(json_encode($v['name']), ENT_QUOTES) ?>, null)"
                                        class="mt-2 text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1">
                                    <?= dashIcon('plus','w-3 h-3') ?> Ajouter une note de sortie
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ──────────────────────────────────────────────────────────────────────
     PANEL: BUCKET LIST (À FAIRE)
──────────────────────────────────────────────────────────────────────── -->
<div id="panel-todo" class="<?= $activeTab !== 'todo' ? 'hidden' : '' ?>">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-slate-900">Ma Bucket List <span class="text-slate-400 font-normal text-base">(<?= $todoCount ?>)</span></h2>
        <a href="<?= BASE_URL ?>/france" class="inline-flex items-center gap-1.5 text-brand-600 hover:text-brand-700 text-sm font-medium">
            <?= dashIcon('plus','w-4 h-4') ?> Ajouter une via
        </a>
    </div>

    <?php if (empty($todoVias)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-5xl mb-4">🏔️</div>
        <h3 class="font-bold text-slate-700 text-lg">Liste vide</h3>
        <p class="text-slate-400 text-sm mt-2 mb-5">Explorez les via ferrata et ajoutez-les à votre liste "À faire".</p>
        <a href="<?= BASE_URL ?>/france" class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
            <?= dashIcon('map-pin','w-4 h-4') ?> Explorer les vias
        </a>
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" id="todo-grid">
        <?php foreach ($todoVias as $v):
            $imgUrl = !empty($v['image_url']) ? escape($v['image_url']) : BASE_URL.'/assets/images/default.png';
        ?>
        <div class="via-card group bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition-all" data-id="<?= (int)$v['via_id'] ?>">
            <a href="<?= BASE_URL ?>/france/<?= escape($v['slug']) ?>" class="block h-40 overflow-hidden bg-slate-100">
                <img src="<?= $imgUrl ?>" alt="<?= escape($v['name']) ?>"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
            </a>
            <div class="p-4">
                <a href="<?= BASE_URL ?>/france/<?= escape($v['slug']) ?>"
                   class="font-semibold text-slate-900 hover:text-brand-600 transition-colors line-clamp-2 text-sm leading-snug block mb-1">
                    <?= escape($v['name']) ?>
                </a>
                <?php if (!empty($v['location'])): ?>
                <p class="text-xs text-slate-400 flex items-center gap-1 mb-3">
                    <?= dashIcon('map-pin','w-3 h-3') ?><?= escape($v['location']) ?>
                </p>
                <?php endif; ?>
                <div class="flex gap-2">
                    <button onclick="markAsDone(<?= (int)$v['via_id'] ?>, <?= htmlspecialchars(json_encode($v['name']), ENT_QUOTES) ?>, this)"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold py-2 px-3 rounded-lg transition-colors">
                        <?= dashIcon('check','w-3.5 h-3.5') ?> C'est fait !
                    </button>
                    <button onclick="removeFavorite(<?= (int)$v['via_id'] ?>, this)"
                            class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors border border-slate-200"
                            title="Retirer de la liste">
                        <?= dashIcon('trash','w-4 h-4') ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ──────────────────────────────────────────────────────────────────────
     PANEL: ROAD TRIPS
──────────────────────────────────────────────────────────────────────── -->
<div id="panel-trips" class="<?= $activeTab !== 'trips' ? 'hidden' : '' ?>">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-slate-900"><?= t('trips_title') ?> <span class="text-slate-400 font-normal text-base">(<?= $tripCount ?>)</span></h2>
        <a href="<?= BASE_URL ?>/road-trip"
           class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl shadow-sm transition-colors">
            <?= dashIcon('plus','w-4 h-4') ?> <?= t('trips_create') ?>
        </a>
    </div>

    <?php if (empty($myTrips)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-5xl mb-4">🗺️</div>
        <h3 class="font-bold text-slate-700 text-lg"><?= t('trips_empty') ?></h3>
        <p class="text-slate-400 text-sm mt-2 mb-5"><?= t('trips_empty_msg') ?></p>
        <a href="<?= BASE_URL ?>/road-trip"
           class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
            <?= dashIcon('map-pin','w-4 h-4') ?> <?= t('trips_create') ?>
        </a>
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($myTrips as $trip): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-brand-400 to-brand-600"></div>
            <div class="p-5">
                <h3 class="font-bold text-slate-900 mb-1 line-clamp-1"><?= escape($trip['name']) ?></h3>
                <div class="flex flex-wrap gap-2 text-xs text-slate-500 mb-4">
                    <span>📆 <?= (int)$trip['nb_days'] ?> <?= t('trip_days') ?></span>
                    <span>📍 <?= (int)$trip['via_count'] ?> <?= t('trip_vias') ?></span>
                    <?php if (!empty($trip['start_date'])): ?>
                    <span>📅 <?= date('d/m/Y', strtotime($trip['start_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>/road-trip/<?= (int)$trip['id'] ?>"
                   class="block w-full text-center bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2 rounded-xl text-sm transition-colors">
                    <?= t('trip_planner') ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sharedTrips)): ?>
    <!-- Shared trips section -->
    <div class="mt-8">
        <h3 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
            <?= dashIcon('users','w-4 h-4 text-brand-500') ?>
            <?= t('trip_shared_with_me') ?>
            <span class="text-slate-400 font-normal text-sm">(<?= count($sharedTrips) ?>)</span>
        </h3>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($sharedTrips as $trip): ?>
            <div class="bg-white rounded-2xl border border-brand-100 shadow-sm hover:shadow-md transition-all overflow-hidden">
                <div class="h-1.5 bg-gradient-to-r from-blue-400 to-brand-400"></div>
                <div class="p-5">
                    <h3 class="font-bold text-slate-900 mb-0.5 line-clamp-1"><?= escape($trip['name']) ?></h3>
                    <p class="text-xs text-slate-400 mb-2"><?= t('trip_by') ?> <?= escape($trip['owner_name']) ?></p>
                    <div class="flex flex-wrap gap-2 text-xs text-slate-500 mb-4">
                        <span>📆 <?= (int)$trip['nb_days'] ?> <?= t('trip_days') ?></span>
                        <span>📍 <?= (int)$trip['via_count'] ?> <?= t('trip_vias') ?></span>
                        <?php if (!empty($trip['start_date'])): ?>
                        <span>📅 <?= date('d/m/Y', strtotime($trip['start_date'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/road-trip/<?= (int)$trip['id'] ?>"
                       class="block w-full text-center border border-brand-300 text-brand-600 hover:bg-brand-50 font-semibold py-2 rounded-xl text-sm transition-colors">
                        <?= t('trip_planner') ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ──────────────────────────────────────────────────────────────────────
     PANEL: COMMENTAIRES
──────────────────────────────────────────────────────────────────────── -->
<div id="panel-comments" class="<?= $activeTab !== 'comments' ? 'hidden' : '' ?>">
    <h2 class="text-lg font-bold text-slate-900 mb-5">Mes commentaires <span class="text-slate-400 font-normal text-base">(<?= $commentCount ?>)</span></h2>

    <?php if (empty($myComments)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-5xl mb-4">💬</div>
        <h3 class="font-bold text-slate-700 text-lg">Aucun commentaire</h3>
        <p class="text-slate-400 text-sm mt-2">Visitez une via ferrata et partagez votre expérience.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($myComments as $c): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0">
                    <a href="<?= BASE_URL ?>/france/<?= escape($c['via_slug']) ?>"
                       class="font-semibold text-brand-600 hover:text-brand-700 hover:underline text-sm line-clamp-1">
                        <?= escape($c['via_name'] ?? $c['via_slug']) ?>
                    </a>
                    <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-1">
                        <?= dashIcon('clock','w-3 h-3') ?><?= formatDate($c['created_at']) ?>
                    </p>
                </div>
                <?php if (!$c['is_approved']): ?>
                <span class="flex-shrink-0 inline-flex items-center gap-1 text-xs bg-amber-50 border border-amber-200 text-amber-700 px-2.5 py-1 rounded-full">
                    <?= dashIcon('clock','w-3 h-3') ?> En attente
                </span>
                <?php else: ?>
                <span class="flex-shrink-0 inline-flex items-center gap-1 text-xs bg-emerald-50 border border-emerald-200 text-emerald-700 px-2.5 py-1 rounded-full">
                    <?= dashIcon('check','w-3 h-3') ?> Publié
                </span>
                <?php endif; ?>
            </div>
            <p class="text-sm text-slate-700 leading-relaxed bg-slate-50 rounded-xl px-4 py-3"><?= nl2br(escape($c['content'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ──────────────────────────────────────────────────────────────────────
     PANEL: MON PROFIL
──────────────────────────────────────────────────────────────────────── -->
<div id="panel-profil" class="<?= $activeTab !== 'profil' ? 'hidden' : '' ?>">
    <h2 class="text-lg font-bold text-slate-900 mb-5">Mon Profil</h2>
    <div class="grid lg:grid-cols-2 gap-6">

        <!-- Profile info -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                <?= dashIcon('user','w-4 h-4 text-brand-500') ?> Informations personnelles
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label for="f-username" class="block text-sm font-medium text-slate-700 mb-1.5">Pseudo <span class="text-slate-400 font-normal">(3–20 caractères)</span></label>
                    <input type="text" id="f-username" name="username" required minlength="3" maxlength="20"
                           value="<?= escape($userInfo['username'] ?? $auth->getUsername()) ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                </div>
                <div>
                    <label for="f-email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <input type="email" id="f-email" name="email" required
                           value="<?= escape($userInfo['email'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                </div>
                <div class="pt-1">
                    <div class="flex items-center gap-2 text-xs text-slate-400 bg-slate-50 rounded-xl px-4 py-3 mb-4">
                        <?= dashIcon('clock','w-3.5 h-3.5') ?>
                        Dernière connexion : <?= !empty($userInfo['last_login']) ? formatDate($userInfo['last_login']) : 'N/A' ?>
                    </div>
                    <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>

        <!-- Password + danger -->
        <div class="space-y-4">
            <!-- Password change -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                    <?= dashIcon('lock','w-4 h-4 text-brand-500') ?> Changer le mot de passe
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label for="f-cur-pw" class="block text-sm font-medium text-slate-700 mb-1.5">Mot de passe actuel</label>
                        <div class="relative">
                            <input type="password" id="f-cur-pw" name="current_password" required
                                   class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm pr-10 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                            <button type="button" onclick="togglePw('f-cur-pw',this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <?= dashIcon('eye','w-4 h-4') ?>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="f-new-pw" class="block text-sm font-medium text-slate-700 mb-1.5">Nouveau mot de passe <span class="text-slate-400 font-normal">(min. 8 car.)</span></label>
                        <input type="password" id="f-new-pw" name="new_password" required minlength="8"
                               class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                    </div>
                    <div>
                        <label for="f-cnf-pw" class="block text-sm font-medium text-slate-700 mb-1.5">Confirmer</label>
                        <input type="password" id="f-cnf-pw" name="confirm_password" required
                               class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                    </div>
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                        Modifier le mot de passe
                    </button>
                </form>
            </div>

            <!-- Account info card -->
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-700 mb-3 text-sm">Informations du compte</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-slate-400">Rôle</dt>
                        <dd class="font-medium text-slate-800 capitalize"><?= escape($userInfo['role'] ?? 'membre') ?></dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-slate-400">Membre depuis</dt>
                        <dd class="font-medium text-slate-800"><?= date('d/m/Y', strtotime($userInfo['created_at'] ?? 'now')) ?></dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-slate-400">Statut</dt>
                        <dd class="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Actif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

</div><!-- /max-w container -->

<!-- ═══════════════════════════════════════════════════════════════════════
     LOGBOOK MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div id="logbook-modal" class="fixed inset-0 hidden z-50" role="dialog" aria-modal="true" aria-labelledby="lbm-title">
    <!-- Scrim -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeLogbookModal()"></div>
    <!-- Card -->
    <div class="relative flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-modal">
            <!-- Header -->
            <div class="bg-gradient-to-r from-slate-800 to-emerald-900 px-6 py-4 flex items-center justify-between">
                <div>
                    <h2 id="lbm-title" class="font-bold text-white text-lg">Ma sortie</h2>
                    <p id="lbm-via-name" class="text-emerald-300 text-sm mt-0.5 line-clamp-1"></p>
                </div>
                <button onclick="closeLogbookModal()" class="text-white/70 hover:text-white p-1 rounded-lg transition-colors" aria-label="Fermer">
                    <?= dashIcon('x','w-5 h-5') ?>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-4">
                <input type="hidden" id="lbm-via-id">
                <input type="hidden" id="lbm-entry-id">

                <!-- Date -->
                <div>
                    <label for="lbm-date" class="block text-sm font-medium text-slate-700 mb-1.5">
                        <?= dashIcon('calendar','w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>Date de sortie
                    </label>
                    <input type="date" id="lbm-date"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow"
                           max="<?= date('Y-m-d') ?>">
                </div>

                <!-- Conditions -->
                <div>
                    <label for="lbm-conditions" class="block text-sm font-medium text-slate-700 mb-1.5">
                        <?= dashIcon('cloud','w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>Conditions météo
                    </label>
                    <select id="lbm-conditions"
                            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow">
                        <option value="">-- Sélectionner --</option>
                        <option value="soleil">☀️ Beau soleil</option>
                        <option value="nuageux">⛅ Nuageux</option>
                        <option value="vent">💨 Venteux</option>
                        <option value="brume">🌫️ Brume / Brouillard</option>
                        <option value="pluie">🌧️ Pluie</option>
                        <option value="neige">❄️ Neige / Froid</option>
                    </select>
                </div>

                <!-- Companions -->
                <div>
                    <label for="lbm-companion" class="block text-sm font-medium text-slate-700 mb-1.5">
                        <?= dashIcon('users','w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>Accompagnateurs
                    </label>
                    <input type="text" id="lbm-companion" placeholder="Noms des personnes présentes..."
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-shadow">
                </div>

                <!-- Notes -->
                <div>
                    <label for="lbm-notes" class="block text-sm font-medium text-slate-700 mb-1.5">
                        <?= dashIcon('pencil','w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>Notes personnelles
                    </label>
                    <textarea id="lbm-notes" rows="4" placeholder="Impressions, difficulté ressentie, conseils pour la prochaine fois..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none resize-none transition-shadow"></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeLogbookModal()"
                        class="flex-1 border border-slate-300 text-slate-700 font-semibold py-2.5 rounded-xl text-sm hover:bg-slate-50 transition-colors">
                    Annuler
                </button>
                <button onclick="submitLogbookEntry()" id="lbm-submit"
                        class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm disabled:opacity-60">
                    <?= dashIcon('check','w-4 h-4 inline -mt-0.5 mr-1') ?>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inline select-via modal (when clicking "Add" from empty state) -->
<div id="via-select-modal" class="fixed inset-0 hidden z-50">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('via-select-modal').classList.add('hidden')"></div>
    <div class="relative flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
            <div class="text-4xl mb-3">🏔️</div>
            <h3 class="font-bold text-slate-900 mb-2">Sélectionner une via</h3>
            <p class="text-sm text-slate-500 mb-5">Marquez une via comme "faite" depuis sa fiche, puis revenez ici pour ajouter vos notes.</p>
            <div class="flex gap-3">
                <button onclick="document.getElementById('via-select-modal').classList.add('hidden')"
                        class="flex-1 border border-slate-300 text-slate-700 font-semibold py-2.5 rounded-xl text-sm hover:bg-slate-50 transition-colors">
                    Annuler
                </button>
                <a href="<?= BASE_URL ?>/france"
                   class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors text-center">
                    Explorer
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes modal-in {
    from { opacity:0; transform:scale(.95) translateY(8px); }
    to   { opacity:1; transform:scale(1)  translateY(0);    }
}
.animate-modal { animation: modal-in 200ms cubic-bezier(.16,1,.3,1) both; }
@media(prefers-reduced-motion:reduce){ .animate-modal{ animation:none; } }
</style>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
const API  = <?= json_encode(BASE_URL . '/api') ?>;

// ── Tabs ──────────────────────────────────────────────────────────────────
const TAB_IDS = ['carnet','todo','trips','comments','profil'];
function switchTab(id) {
    TAB_IDS.forEach(t => {
        const panel = document.getElementById('panel-' + t);
        const btn   = document.getElementById('tab-'   + t);
        if (!panel || !btn) return;
        const active = (t === id);
        panel.classList.toggle('hidden', !active);
        btn.classList.toggle('border-brand-500',  active);
        btn.classList.toggle('text-brand-600',     active);
        btn.classList.toggle('border-transparent', !active);
        btn.classList.toggle('text-slate-500',     !active);
        btn.setAttribute('aria-selected', active);
        btn.querySelectorAll('svg').forEach(svg => {
            svg.closest('span')?.classList.toggle('text-brand-500', active);
            svg.closest('span')?.classList.toggle('text-slate-400', !active);
        });
    });
    history.replaceState(null, '', '?tab=' + id);
}

// ── Logbook modal ─────────────────────────────────────────────────────────
function openLogbookModal(viaId, viaName, existing) {
    if (!viaId && !existing) {
        document.getElementById('via-select-modal').classList.remove('hidden');
        return;
    }
    document.getElementById('lbm-via-id').value   = viaId   || '';
    document.getElementById('lbm-entry-id').value = existing ? (existing.id || '') : '';
    document.getElementById('lbm-via-name').textContent = viaName || '';
    document.getElementById('lbm-title').textContent    = existing ? 'Modifier ma sortie' : 'Ma sortie';
    document.getElementById('lbm-date').value       = existing ? (existing.done_date  || '') : '';
    document.getElementById('lbm-conditions').value = existing ? (existing.conditions || '') : '';
    document.getElementById('lbm-companion').value  = existing ? (existing.companion  || '') : '';
    document.getElementById('lbm-notes').value      = existing ? (existing.notes      || '') : '';
    document.getElementById('logbook-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('lbm-date').focus(), 100);
}
function closeLogbookModal() {
    document.getElementById('logbook-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLogbookModal(); });

async function submitLogbookEntry() {
    const btn = document.getElementById('lbm-submit');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('via_id',     document.getElementById('lbm-via-id').value);
    fd.append('done_date',  document.getElementById('lbm-date').value);
    fd.append('conditions', document.getElementById('lbm-conditions').value);
    fd.append('companion',  document.getElementById('lbm-companion').value);
    fd.append('notes',      document.getElementById('lbm-notes').value);
    try {
        const r = await fetch(API + '/logbook/save', { method:'POST', body:fd });
        const d = await r.json();
        if (d.ok) { window.location.href = '?tab=carnet'; }
        else { alert(d.msg || 'Erreur, veuillez réessayer.'); btn.disabled = false; }
    } catch { alert('Erreur réseau.'); btn.disabled = false; }
}

async function deleteLogbookEntry(entryId, btn) {
    if (!confirm('Supprimer cette note de carnet ?')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('entry_id',   entryId);
    try {
        const r = await fetch(API + '/logbook/delete', { method:'POST', body:fd });
        const d = await r.json();
        if (d.ok) { window.location.reload(); }
        else { alert('Erreur suppression.'); }
    } catch { alert('Erreur réseau.'); }
}

// ── Bucket list actions ───────────────────────────────────────────────────
async function markAsDone(viaId, viaName, btn) {
    // Mark as done first
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('via_id',     viaId);
    await fetch(API + '/favorite/done', { method:'POST', body:fd });
    // Open logbook modal to add details
    openLogbookModal(viaId, viaName, null);
}

async function removeFavorite(viaId, btn) {
    if (!confirm('Retirer cette via de votre liste ?')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('via_id',     viaId);
    const r = await fetch(API + '/favorite/remove', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
        const card = document.querySelector('.via-card[data-id="' + viaId + '"]');
        if (card) { card.style.transition = 'opacity .3s,transform .3s'; card.style.opacity='0'; card.style.transform='scale(.95)'; setTimeout(()=>card.remove(), 300); }
    }
}

// ── Password toggle ───────────────────────────────────────────────────────
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
