<?php
/**
 * Mobile REST API — ViaFerrata-Monde
 * Base: /mobile-api/{resource}/{id?}/{sub?}
 *
 * Auth: Bearer JWT in Authorization header
 * Response: always JSON { ok, data?, msg? }
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Catch any uncaught exception/error and return JSON instead of HTML error page
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erreur serveur', 'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null]);
    exit;
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function mok(mixed $data = null, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}
function merr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}
function requireAuth(): array {
    $jwt = JWT::fromRequest();
    if (!$jwt) merr('Non authentifié', 401);
    return $jwt;
}
function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST;
}
function normalizeUrl(string $url): string {
    if (empty($url)) return $url;
    $url = str_replace(
        ['https://viaferrata.delgehier.com', 'http://viaferrata.delgehier.com'],
        'https://viaferrata-monde.fr',
        $url
    );
    if (!str_starts_with($url, 'http')) {
        $url = 'https://viaferrata-monde.fr/' . ltrim($url, '/');
    }
    return $url;
}

// ── Routing ───────────────────────────────────────────────────────────────────
// URL format: /mobile-api/{r0}/{r1}/{r2}
$base   = $mobile_path ?? '';
$parts  = array_values(array_filter(explode('/', trim($base, '/'))));
$r0     = $parts[0] ?? '';   // resource  (auth, vias, favorites …)
$r1     = $parts[1] ?? '';   // id / sub-resource
$r2     = $parts[2] ?? '';   // sub-id
$method = $_SERVER['REQUEST_METHOD'];

// ═══════════════════════════════════════════════════════════════════
// AUTH
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'auth') {
    $userModel = new User();

    if ($r1 === 'login' && $method === 'POST') {
        $b = body();
        $login = trim($b['login'] ?? '');
        $pass  = trim($b['password'] ?? '');
        if (!$login || !$pass) merr('Login et mot de passe requis');

        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? $userModel->getByEmail($login)
            : $userModel->getByUsername($login);

        if (!$user || !$user['is_active'] || !$userModel->verifyPassword($pass, $user['password_hash']))
            merr('Identifiants incorrects', 401);

        $userModel->updateLastLogin($user['id']);
        $token = JWT::generate((int)$user['id'], $user['username'], $user['email'], $user['role'] ?? 'member');
        mok(['token' => $token, 'user' => [
            'id'       => (int)$user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'] ?? 'member',
        ]]);
    }

    if ($r1 === 'register' && $method === 'POST') {
        $b = body();
        $username = trim($b['username'] ?? '');
        $email    = trim($b['email'] ?? '');
        $pass     = trim($b['password'] ?? '');
        if (!$username || !$email || !$pass) merr('Tous les champs sont requis');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) merr('Email invalide');
        if (strlen($pass) < 8) merr('Mot de passe trop court (8 car. min)');
        if ($userModel->getByUsername($username)) merr('Nom d\'utilisateur déjà pris');
        if ($userModel->getByEmail($email)) merr('Email déjà utilisé');

        $userId = $userModel->create($username, $email, $pass);
        if (!$userId) merr('Erreur lors de la création du compte', 500);

        $token = JWT::generate($userId, $username, $email, 'member');
        mok(['token' => $token, 'user' => [
            'id' => $userId, 'username' => $username, 'email' => $email, 'role' => 'member',
        ]], 201);
    }

    if ($r1 === 'me' && $method === 'GET') {
        $jwt = requireAuth();
        mok(['id' => $jwt['sub'], 'username' => $jwt['username'], 'email' => $jwt['email'], 'role' => $jwt['role']]);
    }

    merr('Endpoint inconnu', 404);
}

// ═══════════════════════════════════════════════════════════════════
// VIAS
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'vias') {
    $viaModel = new ViaFerrata();
    $db = Database::getInstance();

    if ($r1 === 'top-rated' && $method === 'GET') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        mok($viaModel->getTopRated($limit));
    }

    // Endpoint carte : toutes les vias avec coordonnées GPS
    if ($r1 === 'map' && $method === 'GET') {
        $rows = $db->fetchAll(
            "SELECT v.id, v.name, v.slug, v.latitude, v.longitude, v.difficulty, v.image_url,
                    d.name as department_name, vrs.avg_overall
             FROM vias v
             LEFT JOIN departments d ON v.department_id = d.code
             LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
             WHERE v.is_active = 1 AND v.is_approved = 1
               AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL
               AND v.latitude != 0 AND v.longitude != 0
             ORDER BY v.name ASC"
        );
        foreach ($rows as &$r) {
            $r['latitude']  = (float)$r['latitude'];
            $r['longitude'] = (float)$r['longitude'];
            if (!empty($r['image_url'])) $r['image_url'] = normalizeUrl($r['image_url']);
        }
        mok($rows);
    }

    if ($r1 === '' && $method === 'GET') {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $filters = [];
        if (!empty($_GET['search']))          $filters['search']         = $_GET['search'];
        if (!empty($_GET['department_code'])) $filters['department_code']= $_GET['department_code'];
        if (!empty($_GET['country']))         $filters['country']        = $_GET['country'];
        if (isset($_GET['difficulty_min']))   $filters['difficulty_min'] = (int)$_GET['difficulty_min'];
        if (isset($_GET['difficulty_max']))   $filters['difficulty_max'] = (int)$_GET['difficulty_max'];
        if (!empty($_GET['order_by']))        $filters['order_by']       = $_GET['order_by'];

        $total = $viaModel->count($filters);
        $items = $viaModel->search($filters, $limit, $offset);
        foreach ($items as &$item) {
            if (!empty($item['image_url'])) $item['image_url'] = normalizeUrl($item['image_url']);
        }
        mok(['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    if ($r1 !== '' && $r2 === '' && $method === 'GET') {
        // GET /vias/{slug}
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via ferrata introuvable', 404);

        $ratingModel  = new Rating();
        $commentModel = new Comment();
        $photoModel   = new Photo();

        $via['ratings']  = $ratingModel->getByVia($via['id']);
        $via['comments'] = $commentModel->getByVia($via['id']);
        $photos = $photoModel->getApprovedByVia($via['id']);
        foreach ($photos as &$p) {
            if (!empty($p['file_path'])) $p['file_path'] = normalizeUrl($p['file_path']);
        }
        $via['photos']   = $photos;
        if (!empty($via['image_url'])) $via['image_url'] = normalizeUrl($via['image_url']);
        if (!empty($via['latitude']))  $via['latitude']  = (float)$via['latitude'];
        if (!empty($via['longitude'])) $via['longitude'] = (float)$via['longitude'];
        mok($via);
    }

    if ($r1 !== '' && $r2 === 'rate' && $method === 'POST') {
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via introuvable', 404);
        $jwt = JWT::fromRequest();
        $userId = $jwt ? (int)$jwt['sub'] : null;
        $b = body();
        $rG = (float)($b['rating_general'] ?? 0);
        $rB = (float)($b['rating_beauty'] ?? 0);
        $rD = (float)($b['rating_difficulty'] ?? 0);
        if ($rG < 1 || $rG > 10 || $rB < 1 || $rB > 10 || $rD < 1 || $rD > 10) merr('Notes entre 1 et 10');
        $hash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($jwt ? $jwt['sub'] : 'anon'));
        $ok = (new Rating())->create($via['id'], $rG, $rB, $rD, $userId, $hash, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!$ok) merr('Vous avez déjà noté cette via');
        mok(['rated' => true]);
    }

    if ($r1 !== '' && $r2 === 'comment' && $method === 'POST') {
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via introuvable', 404);
        $jwt = JWT::fromRequest();
        $userId = $jwt ? (int)$jwt['sub'] : null;
        $b = body();
        $content = trim($b['content'] ?? '');
        $author  = $jwt ? $jwt['username'] : trim($b['author_name'] ?? '');
        if (strlen($content) < 10 || !$author) merr('Contenu insuffisant');
        $hash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($jwt ? $jwt['sub'] : 'anon'));
        $ok = (new Comment())->create($via['id'], $author, $content, $userId, $hash, null, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!$ok) merr('Erreur lors de la publication');
        mok(['published' => true]);
    }

    merr('Endpoint inconnu', 404);
}

// ═══════════════════════════════════════════════════════════════════
// FAVORITES  (auth required)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'favorites') {
    $jwt  = requireAuth();
    $uid  = (int)$jwt['sub'];
    $favModel = new Favorite();

    if ($method === 'GET') {
        $status = $_GET['status'] ?? null;
        mok($favModel->getByUser($uid, $status ?: null));
    }

    if ($method === 'POST') {
        $b      = body();
        $viaId  = (int)($b['via_id'] ?? 0);
        $status = in_array($b['status'] ?? '', ['to_do','done']) ? $b['status'] : 'to_do';
        if (!$viaId) merr('via_id requis');
        $ok = $favModel->addOrUpdate($uid, $viaId, $status);
        mok(['saved' => $ok]);
    }

    if ($method === 'DELETE' && $r1 !== '') {
        $ok = $favModel->remove($uid, (int)$r1);
        mok(['removed' => $ok]);
    }

    merr('Méthode non supportée', 405);
}

// ═══════════════════════════════════════════════════════════════════
// LOGBOOK  (auth required)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'logbook') {
    $jwt      = requireAuth();
    $uid      = (int)$jwt['sub'];
    $logModel = new Logbook();

    if ($method === 'GET') {
        mok($logModel->getByUser($uid));
    }

    if ($method === 'POST') {
        $b = body();
        $viaId = (int)($b['via_id'] ?? 0);
        if (!$viaId) merr('via_id requis');
        $ok = $logModel->save($uid, $viaId,
            trim($b['done_date'] ?? ''),
            trim($b['conditions'] ?? ''),
            trim($b['companion'] ?? ''),
            trim($b['notes'] ?? ''),
        );
        mok(['saved' => $ok]);
    }

    if ($method === 'DELETE' && $r1 !== '') {
        $entry = $logModel->getById((int)$r1, $uid);
        if (!$entry) merr('Entrée introuvable', 404);
        $ok = $logModel->delete($uid, (int)$r1);
        mok(['deleted' => $ok]);
    }

    merr('Méthode non supportée', 405);
}

// ═══════════════════════════════════════════════════════════════════
// ROAD TRIPS  (auth required)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'trips') {
    $jwt       = requireAuth();
    $uid       = (int)$jwt['sub'];
    $tripModel = new RoadTrip();

    // GET /trips
    if ($r1 === '' && $method === 'GET') {
        $my     = $tripModel->getByUser($uid);
        $shared = $tripModel->getSharedTrips($uid);
        mok(['my_trips' => $my, 'shared_trips' => $shared]);
    }

    // POST /trips
    if ($r1 === '' && $method === 'POST') {
        $b = body();
        $name = trim($b['name'] ?? '');
        if (!$name) merr('Nom requis');
        $id = $tripModel->create($uid, $name,
            trim($b['description'] ?? '') ?: null,
            trim($b['start_date'] ?? '') ?: null,
            trim($b['end_date'] ?? '') ?: null,
            max(1, (int)($b['nb_days'] ?? 3))
        );
        if (!$id) merr('Erreur lors de la création', 500);
        mok(['id' => $id], 201);
    }

    // GET /trips/{id}
    if ($r1 !== '' && $r2 === '' && $method === 'GET') {
        $tid  = (int)$r1;
        if (!$tripModel->canView($tid, $uid)) merr('Accès refusé', 403);
        $trip = $tripModel->getById($tid);
        if (!$trip) merr('Trip introuvable', 404);
        $trip['vias_by_day'] = $tripModel->getViasByDay($tid);
        $trip['is_owner']    = $tripModel->owns($tid, $uid);
        mok($trip);
    }

    // PATCH /trips/{id}
    if ($r1 !== '' && $r2 === '' && ($method === 'PATCH' || $method === 'PUT')) {
        $tid = (int)$r1;
        $b   = body();
        $ok  = $tripModel->update($tid, $uid, $b);
        mok(['updated' => $ok]);
    }

    // DELETE /trips/{id}
    if ($r1 !== '' && $r2 === '' && $method === 'DELETE') {
        $ok = $tripModel->delete((int)$r1, $uid);
        mok(['deleted' => $ok]);
    }

    // POST /trips/{id}/vias
    if ($r1 !== '' && $r2 === 'vias' && $method === 'POST') {
        $tid = (int)$r1;
        if (!$tripModel->owns($tid, $uid)) merr('Accès refusé', 403);
        $b   = body();
        $ok  = $tripModel->addVia($tid, (int)($b['via_id'] ?? 0), max(1, (int)($b['day_number'] ?? 1)), $b['notes'] ?? null);
        mok(['added' => $ok]);
    }

    // DELETE /trips/{id}/vias/{viaId}
    if ($r1 !== '' && $r2 === 'vias' && isset($parts[3]) && $method === 'DELETE') {
        $tid   = (int)$r1;
        $viaId = (int)$parts[3];
        if (!$tripModel->owns($tid, $uid)) merr('Accès refusé', 403);
        $ok = $tripModel->removeVia($tid, $viaId);
        mok(['removed' => $ok]);
    }

    merr('Endpoint inconnu', 404);
}

// ═══════════════════════════════════════════════════════════════════
// DASHBOARD  (auth required)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'dashboard') {
    $jwt = requireAuth();
    $uid = (int)$jwt['sub'];

    $favModel  = new Favorite();
    $logModel  = new Logbook();
    $tripModel = new RoadTrip();
    $viaModel  = new ViaFerrata();

    mok([
        'stats' => [
            'favorites_count'  => $favModel->countByUser($uid),
            'to_do_count'      => $favModel->countByUser($uid, 'to_do'),
            'done_count'       => $favModel->countByUser($uid, 'done'),
            'logbook_count'    => $logModel->countByUser($uid),
            'logbook_this_year'=> $logModel->countThisYear($uid),
            'trips_count'      => count($tripModel->getByUser($uid)),
        ],
        'recent_favorites' => $favModel->getByUser($uid, null),
        'recent_logbook'   => $logModel->getByUser($uid),
        'trips'            => $tripModel->getByUser($uid),
    ]);
}

// ═══════════════════════════════════════════════════════════════════
// COUNTRIES / STATS  (public)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'stats') {
    $db = Database::getInstance();
    $total = (int)$db->fetchOne("SELECT COUNT(*) AS c FROM vias WHERE is_active=1 AND is_approved=1")['c'];
    $countries = (int)$db->fetchOne("SELECT COUNT(DISTINCT country) AS c FROM vias WHERE is_active=1 AND is_approved=1 AND country IS NOT NULL")['c'];
    mok(['total_vias' => $total, 'countries' => $countries]);
}

merr('Endpoint inconnu', 404);
