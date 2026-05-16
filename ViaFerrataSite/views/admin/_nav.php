<?php
/**
 * Admin — Barre de navigation latérale + topbar
 * Nécessite : $navBadges, $adminCurrentPage (ex: 'vias', 'comments'…)
 */
$adminCurrentPage = $adminCurrentPage ?? '';

$navItems = [
    ''            => ['label'=>'Dashboard',     'icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    'vias'        => ['label'=>'Via ferrata',   'icon'=>'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'badge'=>'vias'],
    'comments'    => ['label'=>'Commentaires',  'icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'badge'=>'comments'],
    'photos'      => ['label'=>'Photos',        'icon'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'badge'=>'photos'],
    'submissions' => ['label'=>'Propositions',  'icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'badge'=>'submissions'],
    'users'       => ['label'=>'Utilisateurs',  'icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
];
?>
<!-- Topbar admin -->
<div class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-50 shadow-lg">
    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/" class="text-slate-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <span class="text-slate-400">|</span>
        <span class="font-bold text-brand-400 tracking-wide text-sm uppercase">Panel Admin</span>
    </div>
    <div class="flex items-center gap-3 text-sm">
        <span class="text-slate-400 hidden sm:block"><?= escape($auth->getUsername() ?? '') ?></span>
        <span class="<?= $auth->isAdmin() ? 'bg-red-600' : 'bg-blue-600' ?> text-white text-xs font-bold px-2 py-0.5 rounded-full uppercase">
            <?= $auth->isAdmin() ? 'Admin' : 'Modo' ?>
        </span>
        <a href="<?= BASE_URL ?>/deconnexion" class="text-slate-400 hover:text-red-400 transition-colors text-xs">Déconnexion</a>
    </div>
</div>

<!-- Layout admin : sidebar + contenu -->
<div class="flex min-h-screen bg-slate-100">

    <!-- Sidebar -->
    <aside class="w-56 bg-white border-r border-slate-200 flex-shrink-0 hidden md:block">
        <nav class="p-3 space-y-1 sticky top-14">
            <?php foreach ($navItems as $key => $item):
                $active = ($adminCurrentPage === $key);
                $url    = BASE_URL . '/admin' . ($key ? '/'.$key : '');
                $badge  = isset($item['badge']) ? ($navBadges[$item['badge']] ?? 0) : 0;
            ?>
            <a href="<?= $url ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                      <?= $active ? 'bg-brand-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/>
                </svg>
                <span class="flex-1"><?= $item['label'] ?></span>
                <?php if ($badge > 0): ?>
                <span class="<?= $active ? 'bg-white/30 text-white' : 'bg-red-100 text-red-700' ?> text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[20px] text-center">
                    <?= $badge ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Nav mobile (horizontal) -->
    <div class="md:hidden w-full fixed bottom-0 left-0 z-40 bg-white border-t border-slate-200 flex justify-around px-2 py-1 shadow-lg">
        <?php foreach ($navItems as $key => $item):
            $active = ($adminCurrentPage === $key);
            $url    = BASE_URL . '/admin' . ($key ? '/'.$key : '');
            $badge  = isset($item['badge']) ? ($navBadges[$item['badge']] ?? 0) : 0;
        ?>
        <a href="<?= $url ?>" class="flex flex-col items-center gap-0.5 px-2 py-1.5 rounded-lg relative <?= $active ? 'text-brand-600' : 'text-slate-500' ?>">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/>
            </svg>
            <span class="text-[10px] font-medium leading-none"><?= explode(' ',$item['label'])[0] ?></span>
            <?php if ($badge > 0): ?>
            <span class="absolute -top-0.5 right-0 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center"><?= min($badge,99) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Zone de contenu (enfant inclura son contenu ici) -->
    <main class="flex-1 p-4 md:p-6 pb-20 md:pb-6 max-w-screen-2xl">
