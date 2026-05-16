</main>
<footer class="bg-slate-900 text-slate-400 border-t border-slate-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <p class="font-semibold text-white text-sm">ViaFerrata-Monde.fr</p>
                <p class="text-xs mt-0.5"><?= t('footer_tagline') ?></p>
            </div>
            <nav class="flex flex-wrap justify-center gap-4 text-xs">
                <a href="<?= BASE_URL ?>/monde" class="hover:text-white transition-colors"><?= t('nav_explore') ?></a>
                <a href="<?= BASE_URL ?>/proposer" class="hover:text-white transition-colors"><?= t('footer_suggest') ?></a>
                <a href="<?= BASE_URL ?>/contact" class="hover:text-white transition-colors"><?= t('nav_contact') ?></a>
                <a href="<?= BASE_URL ?>/cgu" class="hover:text-white transition-colors">CGU</a>
            </nav>
            <p class="text-xs"><?= t('footer_copyright', ['year' => date('Y')]) ?></p>
        </div>
    </div>
</footer>

<!-- Bannière de consentement aux cookies (RGPD) -->
<div id="cookie-banner" class="hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-900/98 backdrop-blur border-t border-slate-700 shadow-2xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="flex-1 text-sm text-slate-300">
            <p class="font-semibold text-white mb-1"><?= t('cookie_title') ?></p>
            <p class="text-xs leading-relaxed"><?= t('cookie_text') ?>
            <a href="<?= BASE_URL ?>/cgu" class="text-brand-400 hover:text-brand-300 underline ml-1"><?= t('cookie_link') ?></a>.</p>
        </div>
        <div class="flex gap-3 flex-shrink-0">
            <button onclick="declineCookies()" class="px-4 py-2 text-xs font-semibold border border-slate-600 text-slate-400 hover:text-white hover:border-slate-400 rounded-lg transition-colors"><?= t('cookie_decline') ?></button>
            <button onclick="acceptCookies()" class="px-4 py-2 text-xs font-bold bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors shadow"><?= t('cookie_accept') ?></button>
        </div>
    </div>
</div>

<script>
(function() {
    var consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        document.getElementById('cookie-banner').classList.remove('hidden');
    }
})();
function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookie-banner').classList.add('hidden');
}
function declineCookies() {
    localStorage.setItem('cookie_consent', 'declined');
    document.getElementById('cookie-banner').classList.add('hidden');
}
</script>

</body>
</html>
