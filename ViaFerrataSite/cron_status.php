<?php
/**
 * Endpoint cron — Mise à jour automatique des statuts d'ouverture
 * Appeler via cron : php /path/to/cron_status.php
 * Ou via URL protégée : https://viaferrata-monde.fr/cron_status.php?token=VOTRE_TOKEN
 *
 * Configurer le token dans config.php :
 *   define('CRON_TOKEN', 'votre_token_secret');
 */
require_once __DIR__ . '/config/config.php';

// Protection par token si appelé via HTTP
if (php_sapi_name() !== 'cli') {
    $token = defined('CRON_TOKEN') ? CRON_TOKEN : null;
    if (!$token || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        die('Accès interdit.');
    }
}

$pdo = Database::getInstance()->getConnection();

// ── Fonctions (dupliquées ici pour fonctionner sans session admin) ────────────

function parseOpeningPeriod(string $period): ?array {
    static $months = [
        'janvier'=>1,'fevrier'=>2,'février'=>2,'mars'=>3,'avril'=>4,
        'mai'=>5,'juin'=>6,'juillet'=>7,'aout'=>8,'août'=>8,
        'septembre'=>9,'octobre'=>10,'novembre'=>11,'decembre'=>12,'décembre'=>12,
    ];
    $p = strtolower(trim($period));
    if (preg_match('/toute.{0,5}ann/u', $p)) return ['start'=>1,'end'=>12];
    $p = preg_replace('/\([^)]*\)/', '', $p);
    $p = str_replace(['mi-','début ','fin '], '', $p);
    if (preg_match('/(\w+)\s+(?:à|a|-|au)\s+(\w+)/u', $p, $m)) {
        $start = $months[$m[1]] ?? null;
        $end   = $months[$m[2]] ?? null;
        if ($start && $end) return ['start'=>$start,'end'=>$end];
    }
    foreach ($months as $name => $num) {
        if (str_contains($p, $name)) return ['start'=>$num,'end'=>$num];
    }
    return null;
}

function isCurrentlyOpen(int $s, int $e): bool {
    $m = (int)date('n');
    return $s <= $e ? ($m >= $s && $m <= $e) : ($m >= $s || $m <= $e);
}

// ── Traitement ────────────────────────────────────────────────────────────────
$vias = $pdo->query("
    SELECT id, name, opening_period, opening_status
    FROM vias
    WHERE opening_status != 'ferme_definitif'
      AND (closure_reason IS NULL OR closure_reason = '')
      AND opening_period IS NOT NULL AND opening_period != ''
      AND is_approved = 1
")->fetchAll(PDO::FETCH_ASSOC);

$upd     = $pdo->prepare("UPDATE vias SET opening_status=? WHERE id=?");
$results = [];

foreach ($vias as $v) {
    $range = parseOpeningPeriod($v['opening_period']);
    if (!$range) continue;
    $newStatus = isCurrentlyOpen($range['start'], $range['end']) ? 'ouvert' : 'ferme';
    if ($v['opening_status'] !== $newStatus) {
        $upd->execute([$newStatus, $v['id']]);
        $results[] = "[{$v['id']}] {$v['name']} : {$v['opening_status']} → $newStatus";
    }
}

$date = date('Y-m-d H:i:s');
$count = count($results);

if (php_sapi_name() === 'cli') {
    echo "[$date] $count via(s) mise(s) à jour\n";
    foreach ($results as $r) echo "  $r\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "[$date] $count via(s) mise(s) à jour\n";
    foreach ($results as $r) echo "  $r\n";
}
