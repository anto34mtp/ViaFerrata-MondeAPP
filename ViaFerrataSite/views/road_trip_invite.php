<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();

$token = trim($invite_token ?? '');
if (!$token) { redirect(BASE_URL . '/'); }

$tripModel = new RoadTrip();
$share     = $tripModel->findShareByToken($token);

if (!$share) {
    // Token invalid or already consumed
    $pageTitle = t('invite_title');
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-md mx-auto px-4 py-16 text-center">
        <div class="text-5xl mb-4">❌</div>
        <h1 class="text-xl font-bold text-slate-900 mb-2"><?= t('invite_title') ?></h1>
        <p class="text-slate-500 text-sm"><?= t('invite_invalid') ?></p>
        <a href="<?= BASE_URL ?>/" class="mt-6 inline-block text-brand-600 hover:underline text-sm"><?= t('breadcrumb_home') ?></a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// If user is logged in, consume invite and redirect to trip
if ($auth->isLoggedIn()) {
    $userId = $auth->getUserId();
    $tripModel->consumeInvite($token, $userId);
    setFlash('success', t('invite_accepted'));
    redirect(BASE_URL . '/road-trip/' . (int)$share['trip_id']);
}

// Not logged in — show invite info + login/register CTAs
$redirectPath = '/road-trip/invite/' . urlencode($token);
$loginUrl     = BASE_URL . '/connexion?redirect=' . urlencode($redirectPath);
$registerUrl  = BASE_URL . '/inscription?redirect=' . urlencode($redirectPath);

$pageTitle = t('invite_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-md mx-auto px-4 sm:px-6 py-16 text-center">

    <!-- Icon -->
    <div class="w-20 h-20 mx-auto rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white text-4xl shadow-xl mb-6">
        🗺️
    </div>

    <!-- Invite message -->
    <h1 class="text-2xl font-bold text-slate-900 mb-2">
        <span class="text-brand-600"><?= escape($share['owner_name']) ?></span>
        <?= t('invite_msg') ?>
    </h1>

    <!-- Trip card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-8 text-left">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-xl flex-shrink-0">🗺️</div>
            <div>
                <h2 class="font-bold text-slate-900 text-base"><?= escape($share['trip_name']) ?></h2>
                <p class="text-sm text-slate-400"><?= (int)$share['nb_days'] ?> <?= t('trip_days') ?> · <?= t('trip_by') ?> <?= escape($share['owner_name']) ?></p>
            </div>
        </div>
    </div>

    <!-- CTAs -->
    <div class="space-y-3">
        <a href="<?= escape($loginUrl) ?>"
           class="block w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors shadow-sm">
            <?= t('invite_login_cta') ?>
        </a>
        <a href="<?= escape($registerUrl) ?>"
           class="block w-full border border-slate-300 text-slate-700 hover:bg-slate-50 font-semibold py-3 rounded-xl text-sm transition-colors">
            <?= t('invite_register_cta') ?>
        </a>
    </div>

    <p class="mt-6 text-xs text-slate-400">
        <?= t('invite_title') ?> — ViaFerrata-Monde.fr
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
