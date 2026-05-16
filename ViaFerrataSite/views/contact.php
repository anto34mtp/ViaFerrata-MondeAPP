<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();
$pageTitle = t('nav_contact');
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = t('err_csrf');
    } else {
        $name    = trim($_POST['from_name'] ?? '');
        $email   = trim($_POST['from_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
            $error = t('err_antispam');
        } elseif (empty($name) || empty($email) || empty($message)) {
            $error = t('err_required_fields');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('err_email');
        } else {
            $body = nl2br(htmlspecialchars("Message de: $name ($email)\n\nSujet: $subject\n\n$message", ENT_QUOTES, 'UTF-8'));
            $sent = sendMail(ADMIN_EMAIL, "[ViaFerrata] Contact: $subject", $body, $email);
            if ($sent) $success = t('contact_success');
            else $error = t('err_send');
        }
    }
}
$csrfToken = $auth->generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">✉️ <?= t('nav_contact') ?></h1>
    <p class="text-slate-500 text-sm mb-6"><?= t('contact_subtitle') ?></p>

    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm"><?= escape($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm"><?= escape($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('contact_name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="from_name" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none" value="<?= escape($_POST['from_name'] ?? ($auth->isLoggedIn() ? $auth->getUsername() : '')) ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('contact_email_label') ?> <span class="text-red-500">*</span></label>
            <input type="email" name="from_email" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none" value="<?= escape($_POST['from_email'] ?? ($auth->isLoggedIn() ? $auth->getUserEmail() : '')) ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('contact_subject') ?></label>
            <input type="text" name="subject" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none" value="<?= escape($_POST['subject'] ?? '') ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('contact_message_label') ?> <span class="text-red-500">*</span></label>
            <textarea name="message" required rows="5" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none resize-none"></textarea>
        </div>
        <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <button type="submit" class="w-full py-3 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-xl text-sm transition-colors"><?= t('contact_submit') ?></button>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
