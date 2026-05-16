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

$user = new User();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = t('err_csrf');
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        if (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
            $error = t('err_antispam');
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 20) {
            $error = t('err_username_length');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('err_email');
        } elseif (strlen($password) < 8) {
            $error = t('err_pw_length');
        } elseif ($password !== $confirm) {
            $error = t('err_pw_confirm');
        } else {
            $created = $user->create($username, $email, $password);
            if ($created) {
                $auth->login($email, $password);
                redirect($redirectAfter);
            } else {
                $error = t('err_email_taken');
            }
        }
    }
}
$csrfToken = $auth->generateCsrfToken();
$pageTitle = t('nav_register');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[calc(100vh-64px)] flex">

    <!-- Left panel — decorative -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-emerald-900 via-slate-900 to-emerald-950 flex-col justify-between p-12">
        <svg class="absolute bottom-0 left-0 right-0 w-full opacity-15" viewBox="0 0 800 200" fill="white" aria-hidden="true">
            <path d="M0 200 L100 50 L200 110 L300 15 L420 95 L520 35 L620 85 L720 5 L800 55 L800 200Z"/>
        </svg>

        <div class="relative">
            <div class="flex items-center gap-3 mb-10">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="w-10 h-10 rounded-xl">
                <span class="font-bold text-white text-xl">ViaFerrata-Monde</span>
            </div>
            <h2 class="text-4xl font-bold text-white leading-tight mb-4">
                <?= t('register_tagline') ?>
            </h2>
            <p class="text-emerald-300 text-lg leading-relaxed max-w-sm">
                <?= t('register_tagline_sub') ?>
            </p>
        </div>

        <!-- Stats -->
        <div class="relative grid grid-cols-3 gap-4">
            <?php foreach ([['281','Via ferrata'],['⭐', t('register_stat2')],['📔', t('register_stat3')]] as $s): ?>
            <div class="bg-white/10 backdrop-blur border border-white/20 rounded-2xl p-4 text-center">
                <div class="text-2xl font-bold text-white"><?= $s[0] ?></div>
                <div class="text-xs text-emerald-300 mt-1"><?= $s[1] ?></div>
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
                <h1 class="text-2xl font-bold text-slate-900"><?= t('register_title') ?></h1>
                <p class="text-slate-500 text-sm mt-1"><?= t('register_subtitle') ?></p>
            </div>

            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                <?= escape($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" id="reg-form">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="_redirect" value="<?= escape($redirectParam) ?>">

                <div>
                    <label for="reg-username" class="block text-sm font-medium text-slate-700 mb-1.5"><?= t('register_username') ?> <span class="text-slate-400 font-normal"><?= t('register_username_hint') ?></span></label>
                    <input type="text" id="reg-username" name="username" required minlength="3" maxlength="20" autofocus
                           value="<?= escape($_POST['username'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                           placeholder="MonPseudo">
                </div>

                <div>
                    <label for="reg-email" class="block text-sm font-medium text-slate-700 mb-1.5"><?= t('profile_email') ?></label>
                    <input type="email" id="reg-email" name="email" required
                           value="<?= escape($_POST['email'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                           placeholder="votre@email.fr">
                </div>

                <div>
                    <label for="reg-pw" class="block text-sm font-medium text-slate-700 mb-1.5"><?= t('login_password') ?> <span class="text-slate-400 font-normal"><?= t('register_pw_hint') ?></span></label>
                    <div class="relative">
                        <input type="password" id="reg-pw" name="password" required minlength="8"
                               class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm pr-11 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                               placeholder="••••••••"
                               oninput="checkPwStrength(this.value)">
                        <button type="button" onclick="togglePw('reg-pw')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
                                aria-label="Afficher/masquer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-1.5 flex gap-1" id="pw-strength-bars">
                        <div class="h-1 flex-1 rounded-full bg-slate-200 transition-colors" id="ps1"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-200 transition-colors" id="ps2"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-200 transition-colors" id="ps3"></div>
                        <div class="h-1 flex-1 rounded-full bg-slate-200 transition-colors" id="ps4"></div>
                    </div>
                    <p id="pw-strength-label" class="text-[11px] text-slate-400 mt-1"></p>
                </div>

                <div>
                    <label for="reg-cnf" class="block text-sm font-medium text-slate-700 mb-1.5"><?= t('register_confirm_pw') ?></label>
                    <input type="password" id="reg-cnf" name="confirm" required
                           class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-white transition-shadow"
                           placeholder="••••••••">
                </div>

                <!-- Turnstile -->
                <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

                <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors shadow-sm mt-1">
                    <?= t('register_submit') ?>
                </button>

                <p class="text-[11px] text-slate-400 text-center leading-relaxed">
                    <?= t('register_terms_text') ?>
                    <a href="<?= BASE_URL ?>/cgu" class="text-brand-600 hover:underline"><?= t('register_terms_link') ?></a>.
                </p>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-200 text-center">
                <p class="text-sm text-slate-500">
                    <?= t('register_already') ?>
                    <a href="<?= BASE_URL ?>/connexion" class="text-brand-600 hover:text-brand-700 font-semibold ml-1"><?= t('register_login_link') ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
var PW_LABELS = ['', '<?= addslashes(t('pw_weak')) ?>', '<?= addslashes(t('pw_medium')) ?>', '<?= addslashes(t('pw_good')) ?>', '<?= addslashes(t('pw_strong')) ?>'];

function togglePw(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

function checkPwStrength(pw) {
    const bars  = [document.getElementById('ps1'),document.getElementById('ps2'),document.getElementById('ps3'),document.getElementById('ps4')];
    const label = document.getElementById('pw-strength-label');
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) score++;

    const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-brand-500'];
    bars.forEach((b, i) => {
        b.className = 'h-1 flex-1 rounded-full transition-colors ' + (i < score ? colors[score - 1] : 'bg-slate-200');
    });
    label.textContent = score > 0 ? PW_LABELS[score] : '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
