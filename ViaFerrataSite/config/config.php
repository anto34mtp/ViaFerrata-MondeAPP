<?php
/**
 * Configuration principale - ViaFerrata Monde V2
 */

// =====================================================
// CHEMINS (définis en premier, avant tout le reste)
// =====================================================
define('ROOT_PATH',    dirname(__DIR__));
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('ASSETS_PATH',  ROOT_PATH . '/assets');

// =====================================================
// CHARGEMENT DU FICHIER .env
// =====================================================
(function () {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        // Retirer les guillemets éventuels
        if (preg_match('/^(["\'])(.*)\\1$/', $val, $m)) {
            $val = $m[2];
        }

        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
})();

// Helper : lire une variable .env (avec valeur par défaut)
function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// =====================================================
// CONSTANTES DEPUIS .env
// =====================================================
define('ENVIRONMENT',          env('ENVIRONMENT', 'production'));
define('DB_HOST',              env('DB_HOST',     'localhost'));
define('DB_NAME',              env('DB_NAME',     ''));
define('DB_USER',              env('DB_USER',     ''));
define('DB_PASS',              env('DB_PASS',     ''));
define('BASE_URL',             env('BASE_URL',    ''));
define('SECRET_KEY',           env('SECRET_KEY',  ''));
define('ADMIN_EMAIL',          env('ADMIN_EMAIL', ''));
define('TURNSTILE_SITE_KEY',   env('TURNSTILE_SITE_KEY',   ''));
define('TURNSTILE_SECRET_KEY', env('TURNSTILE_SECRET_KEY', ''));
define('DEEPL_API_KEY',        env('DEEPL_API_KEY', ''));
define('MAIL_HOST',      env('MAIL_HOST',      ''));
define('MAIL_PORT',      (int) env('MAIL_PORT', 465));
define('MAIL_USER',      env('MAIL_USER',      ''));
define('MAIL_PASS',      env('MAIL_PASS',      ''));
define('MAIL_FROM',      env('MAIL_FROM',      ''));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'ViaFerrata-Monde'));

// =====================================================
// CONSTANTES FIXES (non sensibles)
// =====================================================
define('SESSION_LIFETIME', 7200);
define('ITEMS_PER_PAGE',   24);
define('TOP_VIAS_LIMIT',   100);

// =====================================================
// GESTION DES ERREURS
// =====================================================
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/php-errors.log');
}

// =====================================================
// TIMEZONE & SESSION
// =====================================================
date_default_timezone_set('Europe/Paris');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if (ENVIRONMENT === 'production') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// =====================================================
// AUTOLOADER
// =====================================================
spl_autoload_register(function ($className) {
    $file = CLASSES_PATH . '/' . $className . '.class.php';
    if (file_exists($file)) require_once $file;
});

// =====================================================
// LANGUE
// =====================================================
Lang::init();

// =====================================================
// CLOUDFLARE TURNSTILE
// =====================================================
function verifyCloudflareTurnstile(?string $token): bool {
    if (empty($token)) return false;
    $response = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
        ]])
    );
    if ($response === false) return false;
    return !empty(json_decode($response, true)['success']);
}

// =====================================================
// FONCTIONS UTILITAIRES
// =====================================================
function escape(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function safeHtml(string $html): string {
    $allowed = '<h2><h3><h4><p><strong><em><b><i><ul><ol><li><a><br><blockquote><code><pre><hr><span><table><thead><tbody><tr><th><td><img>';
    $clean = strip_tags($html, $allowed);
    // Supprime les attributs événementiels (onclick, onload, etc.)
    $clean = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $clean);
    $clean = preg_replace('/\s+on\w+\s*=\s*\'[^\']*\'/i', '', $clean);
    // Bloque les href javascript:
    $clean = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $clean);
    return $clean;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function getFlash(string $key): ?string {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function setFlash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function formatDate(string $date): string {
    return date('d/m/Y à H:i', strtotime($date));
}

function formatRating(?float $rating): string {
    return $rating !== null ? number_format($rating, 1, ',', '') : 'N/A';
}

function getDifficultyLabel(int $difficulty): string {
    $key = match(true) {
        $difficulty <= 2 => 'diff_F',
        $difficulty <= 3 => 'diff_PD',
        $difficulty <= 4 => 'diff_AD',
        $difficulty <= 6 => 'diff_D',
        $difficulty <= 8 => 'diff_TD',
        default          => 'diff_ED',
    };
    return t($key);
}

// Helper de traduction
function t(string $key, array $params = []): string {
    return Lang::t($key, $params);
}

function truncate(string $text, int $length = 150): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

// =====================================================
// ENVOI D'EMAIL VIA PHPMAILER (SMTP)
// =====================================================
function sendMail(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool {
    require_once ROOT_PATH . '/classes/phpmailer/Exception.php';
    require_once ROOT_PATH . '/classes/phpmailer/SMTP.php';
    require_once ROOT_PATH . '/classes/phpmailer/PHPMailer.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = str_starts_with(MAIL_HOST, 'tls://')
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        if ($replyTo) $mail->addReplyTo($replyTo);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        return $mail->send();
    } catch (\Exception $e) {
        error_log('sendMail error: ' . $mail->ErrorInfo);
        return false;
    }
}
