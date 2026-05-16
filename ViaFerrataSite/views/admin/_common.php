<?php
/**
 * Admin — Initialisation commune : auth, PDO, CSRF, badges nav, colonnes DB
 * Inclure en tout premier dans chaque page admin.
 */
require_once __DIR__ . '/../../config/config.php';

$auth = new Auth();
$auth->requireAuth(BASE_URL . '/connexion');
if (!$auth->isModerator()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:10vh 0">
          <h1 style="color:#dc2626">403 — Accès interdit</h1>
          <p>Réservé aux modérateurs et administrateurs.</p>
          <a href="' . BASE_URL . '/">Retour au site</a></body></html>';
    exit;
}

$pdo       = Database::getInstance()->getConnection();
$csrfToken = $auth->generateCsrfToken();

// Ajouter les colonnes manquantes (silencieux si déjà présentes)
foreach ([
    "ALTER TABLE vias ADD COLUMN closure_reason   VARCHAR(500) NULL",
    "ALTER TABLE vias ADD COLUMN closure_end_date DATE         NULL",
    "ALTER TABLE vias ADD COLUMN approved_by      INT          NULL",
    "ALTER TABLE vias ADD COLUMN approved_at      DATETIME     NULL",
    "ALTER TABLE vias ADD COLUMN is_active        TINYINT(1)   NOT NULL DEFAULT 1",
] as $ddl) {
    try { $pdo->exec($ddl); } catch (\PDOException $e) {}
}

// ── Mise à jour automatique des statuts selon la période d'ouverture ──────────

/**
 * Parse "Juin à octobre", "Mi-juin à mi-octobre", "Toute l'année", etc.
 * Retourne ['start' => int, 'end' => int] (numéros de mois) ou null.
 */
function parseOpeningPeriod(string $period): ?array {
    static $months = [
        'janvier'=>1,'fevrier'=>2,'février'=>2,'mars'=>3,'avril'=>4,
        'mai'=>5,'juin'=>6,'juillet'=>7,'aout'=>8,'août'=>8,
        'septembre'=>9,'octobre'=>10,'novembre'=>11,'decembre'=>12,'décembre'=>12,
    ];

    $p = strtolower(trim($period));

    // Toute l'année
    if (preg_match('/toute.{0,5}ann/u', $p)) {
        return ['start' => 1, 'end' => 12];
    }

    // Supprimer "mi-", parenthèses, "selon météo", etc.
    $p = preg_replace('/\([^)]*\)/', '', $p);
    $p = str_replace(['mi-', 'début ', 'fin '], '', $p);
    $p = trim($p);

    // Format "X à Y" ou "X - Y" ou "X au Y"
    if (preg_match('/(\w+)\s+(?:à|a|-|au)\s+(\w+)/u', $p, $m)) {
        $start = $months[$m[1]] ?? null;
        $end   = $months[$m[2]] ?? null;
        if ($start && $end) {
            return ['start' => $start, 'end' => $end];
        }
    }

    // Mois unique ("Juillet")
    foreach ($months as $name => $num) {
        if (str_contains($p, $name)) {
            return ['start' => $num, 'end' => $num];
        }
    }

    return null;
}

/**
 * Détermine si on est actuellement dans la plage start→end.
 * Gère les plages qui traversent le changement d'année (ex. Nov → Mars).
 */
function isCurrentlyOpen(int $start, int $end): bool {
    $m = (int)date('n');
    return $start <= $end
        ? ($m >= $start && $m <= $end)
        : ($m >= $start || $m <= $end);
}

/**
 * Met à jour opening_status de toutes les vias éligibles.
 * N'écrase PAS les vias fermées définitivement ni celles avec une raison manuelle.
 * Retourne le nombre de vias modifiées.
 */
function autoUpdateOpeningStatuses(PDO $pdo): int {
    try {
        $stmt = $pdo->query("
            SELECT id, opening_period, opening_status
            FROM vias
            WHERE opening_status != 'ferme_definitif'
              AND (closure_reason IS NULL OR closure_reason = '')
              AND opening_period IS NOT NULL
              AND opening_period != ''
              AND is_approved = 1
        ");
        $vias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return 0;
    }

    $updated = 0;
    $upd = $pdo->prepare("UPDATE vias SET opening_status=? WHERE id=?");

    foreach ($vias as $v) {
        $range = parseOpeningPeriod($v['opening_period']);
        if (!$range) continue;

        $newStatus = isCurrentlyOpen($range['start'], $range['end']) ? 'ouvert' : 'ferme';
        if ($v['opening_status'] !== $newStatus) {
            $upd->execute([$newStatus, $v['id']]);
            $updated++;
        }
    }

    return $updated;
}

// Exécuter la mise à jour automatique (rapide — une seule requête SQL batch)
$autoStatusUpdated = autoUpdateOpeningStatuses($pdo);

// ── Compteurs pour badges de navigation ──────────────────────────────────────
try {
    $navBadges = [
        'vias'        => (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE is_approved=0")->fetchColumn(),
        'comments'    => (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved=0")->fetchColumn(),
        'photos'      => (int)$pdo->query("SELECT COUNT(*) FROM user_photos WHERE is_approved=0")->fetchColumn(),
        'submissions' => (int)$pdo->query("SELECT COUNT(*) FROM via_submissions WHERE status='pending'")->fetchColumn(),
    ];
} catch (\PDOException $e) {
    $navBadges = ['vias'=>0,'comments'=>0,'photos'=>0,'submissions'=>0];
}

// Flash messages
$flashSuccess = getFlash('success') ?? '';
$flashError   = getFlash('error')   ?? '';
