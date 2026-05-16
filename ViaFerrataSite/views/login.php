<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();
if ($auth->isLoggedIn()) redirect(BASE_URL . '/');

// Validate optional redirect parameter (relative paths only)
$redirectParam = trim($_GET['redirect'] ?? $_POST['_redirect'] ?? '');
$redirectAfter = BASE_URL . '/mon-espace';
if ($redirectParam && preg_match('/^\/[a-zA-Z0-9\-_\/]+$/', $redirectParam)) {
    $redirectAfter = BASE_URL . $redirectParam;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = t('err_csrf');
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($auth->login($email, $password)) {
            redirect($redirectAfter);
        } else {
            $error = t('err_credentials');
        }
    }
}
$csrfToken = $auth->generateCsrfToken();
$pageTitle = t('nav_login');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[calc(100vh-64px)] flex">

    <!-- Left panel — decorative -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 flex-col justify-between p-12">
        <svg class="absolute bottom-0 left-0 right-0 w-full opacity-15" viewBox="0 0 800 200" fill="white" aria-hidden="true">
            <path d="M0 200 L80 60 L160 120 L260 20 L360 100 L460 30 L560 90 L660 10 L760 70 L800 45 L800 200Z"/>
        </svg>

        <div class="relative">
            <div class="flex items-center gap-3 mb-10">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="w-10 h-10 rounded-xl">
                <span class="font-bold text-white text-xl">ViaFerrata-Monde</span>
            </div>
            <h2 class="text-4xl font-bold text-white leading-tight mb-4">
                <?= t('login_tagline') ?>
            </h2>
            <p class="text-emerald-300 text-lg leading-relaxed max-w-sm">
                <?= t('login_tagline_sub') ?>
            </p>
        </div>

        <div class="relative space-y-3">
            <?php
            $features = [
                ['icon'=>'📔', 'key'=>'login_feat1'],
                ['icon'=>'🏔️', 'key'=>'login_feat2'],
                ['icon'=>'🌟', 'key'=>'login_feat3'],
            ];
            foreach ($features as $f):
            ?>
            <div class="flex items-start gap-3 text-sm text-slate-300">
                <span class="text-lg leading-none mt-0.5"><?= $f['icon'] ?></span>
                <span><?= t($f['key']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right panel — form -->
    <div class="flex-1 flex items-center justify-center px-4 sm:px-12 py-12 bg-slate-50">
        <div class="w-full max-w-sm">

            <!-- Logo mobile -->
            <div class="flex items-center gap-2 mb-8 lg:hidden">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="w-8 h-8 rounded-lg">
                <span class="font-bold text-slate-800">ViaFerrata-Monde</span>
            </div>

            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-900"><?= t('login_welcome') ?></h1>
                <p class="text-slate-500 text-sm mt-1"><?= t('login_subtitle') ?></p>
            </div>

            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                <?= escape($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="_redirect" value="<?= escape($redirectParam) ?>">

                <div>
                    <label for="login-email" class="block text-sm font-medium text-slate-700 mb-1.5"><?= t('profile_email') ?></label>
                    <input type="email" id="login-email" name="email" required autofocus
                           value="<?= escape($_POST['email'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                           placeholder="votre@email.fr">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="login-pw" class="text-sm font-medium text-slate-700"><?= t('login_password') ?></label>
                    </div>
                    <div class="relative">
                        <input type="password" id="login-pw" name="password" required
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm pr-11 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePw('login-pw')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
                                aria-label="Afficher/masquer le mot de passe">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors shadow-sm mt-2">
                    <?= t('login_submit') ?>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-200 text-center">
                <p class="text-sm text-slate-500">
                    <?= t('login_no_account') ?>
                    <a href="<?= BASE_URL ?>/inscription" class="text-brand-600 hover:text-brand-700 font-semibold ml-1"><?= t('login_create_account') ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePw(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
