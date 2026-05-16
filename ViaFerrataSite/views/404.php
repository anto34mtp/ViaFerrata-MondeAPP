<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../config/config.php';
$pageTitle = '404 — Page introuvable';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4 text-center">
    <p class="text-7xl font-bold text-brand-400 mb-2">404</p>
    <h1 class="text-2xl font-bold text-slate-900 mb-2">Page introuvable</h1>
    <p class="text-slate-500 text-sm mb-6 max-w-sm">La via ferrata ou la page que vous cherchez n'existe pas ou a été déplacée.</p>
    <a href="<?= BASE_URL ?>/" class="px-6 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl text-sm transition-colors">← Retour à l'accueil</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
