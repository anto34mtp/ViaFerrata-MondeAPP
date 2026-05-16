<?php require_once __DIR__ . '/../includes/header.php'; ?>

<!-- Hero Section -->
<div class="relative bg-slate-900 overflow-hidden min-h-[500px] md:min-h-[600px] flex items-center">
    <div class="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1522163182402-834f871fd851?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80" alt="Via Ferrata Montagne" class="w-full h-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="max-w-2xl">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 drop-shadow-md">
                <?= t('home_hero_title') ?>
            </h1>
            <p class="text-lg md:text-xl text-slate-200 mb-8 font-light">
                <?= t('home_hero_subtitle') ?>
            </p>

            <div class="flex flex-col sm:flex-row gap-4">
                <a href="<?= BASE_URL ?>/monde" class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-brand-600 hover:bg-brand-500 shadow-lg shadow-brand-500/30 transition-all hover:-translate-y-0.5">
                    <?= t('home_btn_map') ?>
                </a>
                <a href="<?= BASE_URL ?>/proposer" class="inline-flex justify-center items-center px-6 py-3 border border-slate-300 text-base font-medium rounded-lg text-white bg-white/10 hover:bg-white/20 backdrop-blur-sm transition-all">
                    <?= t('nav_suggest') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="py-16 md:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-3xl font-bold text-slate-900 mb-4"><?= t('home_features_title') ?></h2>
            <p class="text-slate-600"><?= t('home_features_sub') ?></p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 bg-brand-100 text-brand-600 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-slate-900 mb-2"><?= t('home_feat1_title') ?></h3>
                <p class="text-slate-600"><?= t('home_feat1_desc') ?></p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-slate-900 mb-2"><?= t('home_feat2_title') ?></h3>
                <p class="text-slate-600"><?= t('home_feat2_desc') ?></p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-slate-900 mb-2"><?= t('home_feat3_title') ?></h3>
                <p class="text-slate-600"><?= t('home_feat3_desc') ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
