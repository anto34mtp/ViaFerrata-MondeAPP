<!DOCTYPE html>
<html lang="<?= Lang::get() ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escape($pageTitle) : 'ViaFerrata-Monde.fr — Le portail des via ferrata' ?></title>
    <meta name="description" content="<?= isset($pageDesc) ? escape($pageDesc) : escape(t('footer_tagline')) ?>">
    <link rel="icon" type="image/png" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/images/logo.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#ecfdf5', 100:'#d1fae5', 200:'#a7f3d0', 500:'#10b981', 600:'#059669', 700:'#047857', 900:'#064e3b' }
                    }
                }
            }
        }
    </script>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

    <style>
        body { -webkit-font-smoothing: antialiased; }
        .map-container { height: 320px; }
        @media (min-width: 1024px) {
            .map-container { height: calc(100vh - 64px); position: sticky; top: 64px; }
        }
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 2px; }
        .star-rating input { display:none; }
        .star-rating label { font-size:1.5rem; cursor:pointer; color:#d1d5db; transition:color .15s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color:#f59e0b; }

        /* Security popup */
        #security-popup {
            position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            z-index: 9999; display:flex; align-items:center; justify-content:center; padding:1rem;
        }
        #security-popup.hidden { display:none; }

        /* Language dropdown */
        .lang-dropdown { position:relative; display:inline-block; }
        .lang-menu { display:none; position:absolute; right:0; top:calc(100% + 6px); background:white; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,.12); min-width:140px; z-index:100; overflow:hidden; }
        .lang-dropdown:hover .lang-menu,
        .lang-dropdown:focus-within .lang-menu { display:block; }
        .lang-menu a { display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:.8rem; font-weight:500; color:#475569; text-decoration:none; transition:background .15s; white-space:nowrap; }
        .lang-menu a:hover { background:#f8fafc; color:#10b981; }
        .lang-menu a.active { background:#ecfdf5; color:#047857; font-weight:600; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans flex flex-col min-h-screen">

<!-- Security Warning Popup -->
<div id="security-popup">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 max-w-md w-full shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="bg-amber-500/20 text-amber-400 p-2 rounded-xl">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h2 class="text-xl font-bold text-white"><?= t('popup_title') ?></h2>
        </div>
        <div class="bg-red-900/40 border border-red-700 rounded-lg px-4 py-3 mb-4">
            <p class="text-red-300 font-semibold text-sm"><?= t('popup_warning') ?></p>
        </div>
        <p class="text-slate-300 font-semibold text-sm mb-2"><?= t('popup_intro') ?></p>
        <ul class="space-y-1.5 text-sm text-slate-300 mb-4">
            <li class="flex gap-2"><span class="text-brand-400 mt-0.5">•</span><?= t('popup_check1') ?></li>
            <li class="flex gap-2"><span class="text-brand-400 mt-0.5">•</span><?= t('popup_check2') ?></li>
            <li class="flex gap-2"><span class="text-brand-400 mt-0.5">•</span><?= t('popup_check3') ?></li>
            <li class="flex gap-2"><span class="text-brand-400 mt-0.5">•</span><?= t('popup_check4') ?></li>
        </ul>
        <p class="text-sm text-red-400 font-semibold mb-1"><?= t('popup_disclaimer_title') ?></p>
        <p class="text-xs text-slate-400 mb-5"><?= t('popup_disclaimer') ?></p>
        <div class="flex gap-3">
            <button onclick="refuseWarning()" class="flex-1 py-2.5 bg-red-700 hover:bg-red-600 text-white font-semibold rounded-lg transition-colors text-sm"><?= t('popup_refuse') ?></button>
            <button onclick="acceptWarning()" class="flex-1 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-lg transition-colors text-sm"><?= t('popup_accept') ?></button>
        </div>
    </div>
</div>

<script>
if (sessionStorage.getItem('security_ok')) {
    document.getElementById('security-popup').classList.add('hidden');
}
function acceptWarning() {
    document.getElementById('security-popup').classList.add('hidden');
    sessionStorage.setItem('security_ok', '1');
}
function refuseWarning() {
    window.location.href = 'https://www.google.fr';
}
</script>

<!-- Header / Navbar -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-2 group">
                    <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="ViaFerrata-Monde logo" class="w-9 h-9 rounded-lg object-contain group-hover:opacity-90 transition-opacity">
                    <span class="font-bold text-xl tracking-tight text-slate-800">ViaFerrata-<?= isset($headerCountry) ? escape($headerCountry) : 'Monde' ?></span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <nav class="hidden md:flex space-x-1 items-center">
                <a href="<?= BASE_URL ?>/monde" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">
                    <svg class="inline w-4 h-4 -mt-0.5 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= t('nav_explore') ?>
                </a>
                <a href="<?= BASE_URL ?>/proposer" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">➕ <?= t('nav_suggest') ?></a>
                <a href="<?= BASE_URL ?>/contact" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">✉️ <?= t('nav_contact') ?></a>

                <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/mon-espace" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">👤 <?= t('nav_my_space') ?></a>
                    <a href="<?= BASE_URL ?>/road-trip" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">🗺️ <?= t('nav_trips') ?></a>
                    <?php if ($auth->isModerator()): ?>
                        <a href="<?= BASE_URL ?>/admin" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50">⚙️ <?= t('nav_admin') ?></a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/deconnexion" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"><?= t('nav_logout') ?></a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/connexion" class="text-slate-600 hover:text-brand-600 font-medium transition-colors text-sm px-3 py-2 rounded-lg hover:bg-slate-50"><?= t('nav_login') ?></a>
                    <a href="<?= BASE_URL ?>/inscription" class="bg-brand-500 hover:bg-brand-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors shadow-sm"><?= t('nav_register') ?></a>
                <?php endif; ?>

                <!-- Language Selector -->
                <?php $currentLang = Lang::get(); ?>
                <div class="lang-dropdown ml-2">
                    <button class="flex items-center gap-1.5 border border-slate-200 hover:border-brand-400 text-slate-600 hover:text-brand-600 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors bg-white">
                        <span><?= Lang::getFlag($currentLang) ?></span>
                        <span class="hidden lg:inline"><?= escape(Lang::getNativeName($currentLang)) ?></span>
                        <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="lang-menu">
                        <?php foreach (Lang::getAvailable() as $lang): ?>
                        <a href="?lang=<?= $lang ?>" class="<?= $lang === $currentLang ? 'active' : '' ?>">
                            <span><?= Lang::getFlag($lang) ?></span>
                            <span><?= escape(Lang::getNativeName($lang)) ?></span>
                            <?php if ($lang === $currentLang): ?><span class="ml-auto text-brand-500">✓</span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </nav>

            <!-- Mobile: lang + hamburger -->
            <div class="flex items-center gap-2 md:hidden">
                <!-- Mini language picker (mobile) -->
                <?php $currentLang = Lang::get(); ?>
                <div class="lang-dropdown">
                    <button class="flex items-center gap-1 border border-slate-200 text-slate-600 px-2.5 py-1.5 rounded-lg text-sm font-medium bg-white">
                        <?= Lang::getFlag($currentLang) ?>
                        <svg class="w-3 h-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="lang-menu">
                        <?php foreach (Lang::getAvailable() as $lang): ?>
                        <a href="?lang=<?= $lang ?>" class="<?= $lang === $currentLang ? 'active' : '' ?>">
                            <span><?= Lang::getFlag($lang) ?></span>
                            <span><?= escape(Lang::getNativeName($lang)) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" id="mobile-menu-button" class="text-slate-500 hover:text-slate-900 focus:outline-none p-2 rounded-md hover:bg-slate-100 transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Panel -->
    <div class="md:hidden hidden bg-white border-t border-slate-100 absolute w-full shadow-lg z-50" id="mobile-menu">
        <div class="px-4 pt-2 pb-4 space-y-1">
            <a href="<?= BASE_URL ?>/monde" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50"><?= t('nav_explore_world') ?></a>
            <a href="<?= BASE_URL ?>/proposer" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50">➕ <?= t('nav_suggest') ?></a>
            <a href="<?= BASE_URL ?>/contact" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50">✉️ <?= t('nav_contact') ?></a>
            <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>/mon-espace" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50">👤 <?= t('nav_my_space') ?></a>
                <a href="<?= BASE_URL ?>/road-trip" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50">🗺️ <?= t('nav_trips') ?></a>
                <a href="<?= BASE_URL ?>/deconnexion" class="block px-3 py-2 rounded-md text-base font-medium text-red-600 hover:bg-red-50"><?= t('nav_logout') ?></a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/connexion" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:text-brand-600 hover:bg-slate-50"><?= t('nav_login') ?></a>
                <a href="<?= BASE_URL ?>/inscription" class="block px-3 py-2 rounded-md text-base font-medium text-brand-600 hover:bg-brand-50"><?= t('nav_register') ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
</script>

<main class="flex-grow flex flex-col">
