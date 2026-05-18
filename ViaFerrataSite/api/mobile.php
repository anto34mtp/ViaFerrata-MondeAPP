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
    $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
    $json  = json_encode(['ok' => true, 'data' => $data], $flags);
    if ($json === false) {
        $json = json_encode(['ok' => true, 'data' => $data], $flags | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    echo $json;
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

// Normalise les noms de colonnes DB vers les champs attendus par l'app mobile
function normalizeVia(array $v): array {
    return [
        'id'                    => (int)($v['id'] ?? 0),
        'slug'                  => $v['slug'] ?? '',
        'name'                  => $v['name'] ?? '',
        'location'              => $v['location'] ?? null,
        'department_name'       => $v['department_name'] ?? null,
        'department_code'       => $v['department_code'] ?? null,
        'country'               => $v['code_pays'] ?? $v['country'] ?? null,
        'difficulty'            => isset($v['difficulty']) && $v['difficulty'] !== null ? (int)$v['difficulty'] : null,
        'duration_min'          => isset($v['duration_hours']) && $v['duration_hours'] !== null ? (int)$v['duration_hours'] : null,
        'length_m'              => isset($v['length_meters']) && $v['length_meters'] !== null ? (int)$v['length_meters'] : null,
        'elevation_m'           => isset($v['elevation_gain']) && $v['elevation_gain'] !== null ? (int)$v['elevation_gain'] : null,
        'altitude_max_m'        => isset($v['altitude_max']) && $v['altitude_max'] !== null ? (int)$v['altitude_max'] : null,
        'gps_lat'               => isset($v['latitude']) && $v['latitude'] !== null ? (float)$v['latitude'] : null,
        'gps_lng'               => isset($v['longitude']) && $v['longitude'] !== null ? (float)$v['longitude'] : null,
        'opening_status'        => $v['opening_status'] ?? null,
        'description'           => $v['description'] ?? null,
        'pricing_info'          => $v['pricing'] ?? null,
        'tourism_office'        => $v['tourism_office_name'] ?? null,
        'avg_rating_general'    => isset($v['avg_general']) && $v['avg_general'] !== null ? round((float)$v['avg_general'], 1) : null,
        'avg_rating_beauty'     => isset($v['avg_beauty']) && $v['avg_beauty'] !== null ? round((float)$v['avg_beauty'], 1) : null,
        'avg_rating_difficulty' => isset($v['avg_difficulty']) && $v['avg_difficulty'] !== null ? round((float)$v['avg_difficulty'], 1) : null,
        'ratings_count'         => isset($v['total_ratings']) && $v['total_ratings'] !== null ? (int)$v['total_ratings'] : null,
        'image_url'             => !empty($v['image_url']) ? normalizeUrl($v['image_url']) : null,
    ];
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
        if (!empty(TURNSTILE_SECRET_KEY) && !verifyCloudflareTurnstile($b['turnstile_token'] ?? null)) {
            merr('Vérification anti-spam échouée. Complétez le captcha.', 422);
        }
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
        try {
            $rows = $viaModel->getTopRated($limit);
            mok(array_map('normalizeVia', $rows));
        } catch (Throwable $e) {
            try {
                $stmt = $db->getConnection()->prepare(
                    "SELECT v.*, d.name as department_name, d.code as department_code
                     FROM vias v
                     LEFT JOIN departments d ON v.department_id = d.code
                     WHERE v.is_active = 1
                     ORDER BY v.created_at DESC LIMIT :limit"
                );
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                mok(array_map('normalizeVia', $stmt->fetchAll()));
            } catch (Throwable $e2) {
                mok([]);
            }
        }
    }

    // Endpoint carte : toutes les vias avec coordonnées GPS
    if ($r1 === 'map' && $method === 'GET') {
        try {
            $rows = $db->fetchAll(
                "SELECT v.id, v.name, v.slug, v.latitude, v.longitude, v.difficulty, v.code_pays as country,
                        v.image_url, d.name as department_name, vrs.avg_overall
                 FROM vias v
                 LEFT JOIN departments d ON v.department_id = d.code
                 LEFT JOIN via_ratings_summary vrs ON v.id = vrs.via_id
                 WHERE v.is_active = 1
                   AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL
                   AND v.latitude != 0 AND v.longitude != 0
                 ORDER BY v.name ASC"
            );
        } catch (Throwable $e) {
            try {
                $rows = $db->fetchAll(
                    "SELECT v.id, v.name, v.slug, v.latitude, v.longitude, v.difficulty, v.code_pays as country,
                            v.image_url, d.name as department_name, NULL as avg_overall
                     FROM vias v
                     LEFT JOIN departments d ON v.department_id = d.code
                     WHERE v.is_active = 1
                       AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL
                       AND v.latitude != 0 AND v.longitude != 0
                     ORDER BY v.name ASC"
                );
            } catch (Throwable $e2) {
                $rows = [];
            }
        }
        $points = [];
        foreach ($rows as $r) {
            $points[] = [
                'id'              => (int)$r['id'],
                'slug'            => $r['slug'],
                'name'            => $r['name'],
                'gps_lat'         => (float)$r['latitude'],
                'gps_lng'         => (float)$r['longitude'],
                'difficulty'      => $r['difficulty'] !== null ? (int)$r['difficulty'] : null,
                'country'         => $r['country'] ?? null,
                'department_name' => $r['department_name'] ?? null,
                'avg_overall'     => $r['avg_overall'] !== null ? round((float)$r['avg_overall'], 1) : null,
                'image_url'       => !empty($r['image_url']) ? normalizeUrl($r['image_url']) : null,
            ];
        }
        mok($points);
    }

    if ($r1 === '' && $method === 'GET') {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $filters = [];
        if (!empty($_GET['search']))          $filters['search']          = $_GET['search'];
        if (!empty($_GET['department_code'])) $filters['department_code'] = $_GET['department_code'];
        if (!empty($_GET['country']))         $filters['country']         = $_GET['country'];
        if (!empty($_GET['difficulty_min']))  $filters['difficulty_min']  = (int)$_GET['difficulty_min'];
        if (!empty($_GET['difficulty_max']))  $filters['difficulty_max']  = (int)$_GET['difficulty_max'];
        if (!empty($_GET['order_by']))        $filters['order_by']        = $_GET['order_by'];

        try {
            $total = $viaModel->count($filters);
        } catch (Throwable $e) {
            $total = 0;
        }
        try {
            $items = $viaModel->search($filters, $limit, $offset);
            mok(['items' => array_map('normalizeVia', $items), 'total' => $total, 'page' => $page, 'limit' => $limit]);
        } catch (Throwable $e) {
            // Fallback: simple query without via_ratings_summary view
            try {
                $simpleConditions = ["v.is_active = 1"];
                $simpleParams = [];
                if (!empty($filters['search'])) {
                    $s = '%' . $filters['search'] . '%';
                    $simpleConditions[] = "(v.name LIKE :s_name OR v.location LIKE :s_loc)";
                    $simpleParams[':s_name'] = $s;
                    $simpleParams[':s_loc']  = $s;
                }
                if (!empty($filters['country'])) {
                    $simpleConditions[] = "v.code_pays = :country";
                    $simpleParams[':country'] = $filters['country'];
                }
                if (isset($filters['difficulty_min'])) { $simpleConditions[] = "v.difficulty >= :dmin"; $simpleParams[':dmin'] = $filters['difficulty_min']; }
                if (isset($filters['difficulty_max'])) { $simpleConditions[] = "v.difficulty <= :dmax"; $simpleParams[':dmax'] = $filters['difficulty_max']; }
                $where = implode(' AND ', $simpleConditions);
                $stmt = $db->getConnection()->prepare(
                    "SELECT v.*, d.name as department_name, d.code as department_code
                     FROM vias v
                     LEFT JOIN departments d ON v.department_id = d.code
                     WHERE $where ORDER BY v.created_at DESC LIMIT :limit OFFSET :offset"
                );
                foreach ($simpleParams as $k => $val) { $stmt->bindValue($k, $val); }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll();
                if ($total === 0) $total = count($items);
                mok(['items' => array_map('normalizeVia', $items), 'total' => $total, 'page' => $page, 'limit' => $limit]);
            } catch (Throwable $e2) {
                mok(['items' => [], 'total' => 0, 'page' => $page, 'limit' => $limit]);
            }
        }
    }

    if ($r1 !== '' && $r2 === '' && $method === 'GET') {
        // GET /vias/{slug}
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via ferrata introuvable', 404);

        $data = normalizeVia($via);

        try {
            $ratingModel  = new Rating();
            $commentModel = new Comment();
            $photoModel   = new Photo();
            $data['ratings']  = $ratingModel->getByVia($via['id']);
            $data['comments'] = $commentModel->getByVia($via['id']);
            $photos = $photoModel->getApprovedByVia($via['id']);
            foreach ($photos as &$p) {
                $fileUrl = !empty($p['file_path']) ? normalizeUrl($p['file_path']) : null;
                $p['url']       = $fileUrl;
                $p['file_path'] = $fileUrl;
            }
            $data['photos'] = $photos;
        } catch (Throwable $e) {
            $data['ratings']  = [];
            $data['comments'] = [];
            $data['photos']   = [];
        }
        mok($data);
    }

    if ($r1 !== '' && $r2 === 'rate' && $method === 'POST') {
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via introuvable', 404);
        $jwt = JWT::fromRequest();
        $userId = $jwt ? (int)$jwt['sub'] : null;
        $b = body();
        if (!$jwt && !empty(TURNSTILE_SECRET_KEY)) {
            if (!verifyCloudflareTurnstile($b['turnstile_token'] ?? null)) {
                merr('Vérification anti-spam échouée.', 422);
            }
        }
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
        // Turnstile required for anonymous users when configured
        if (!$jwt && !empty(TURNSTILE_SECRET_KEY)) {
            if (!verifyCloudflareTurnstile($b['turnstile_token'] ?? null)) {
                merr('Vérification anti-spam échouée. Rechargez la page.', 422);
            }
        }
        $content = trim($b['content'] ?? '');
        $author  = $jwt ? $jwt['username'] : trim($b['author_name'] ?? '');
        if (strlen($content) < 10 || !$author) merr('Contenu insuffisant');
        $hash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($jwt ? $jwt['sub'] : 'anon'));
        $ok = (new Comment())->create($via['id'], $author, $content, $userId, $hash, null, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!$ok) merr('Erreur lors de la publication');
        mok(['published' => true]);
    }

    if ($r1 !== '' && $r2 === 'photos' && $method === 'POST') {
        $via = $viaModel->getBySlug($r1);
        if (!$via) merr('Via introuvable', 404);
        if (!isset($_FILES['photo'])) merr('Fichier photo manquant');
        $jwt = JWT::fromRequest();
        if (!$jwt && !empty(TURNSTILE_SECRET_KEY)) {
            if (!verifyCloudflareTurnstile($_POST['turnstile_token'] ?? null)) {
                merr('Vérification anti-spam échouée.', 422);
            }
        }
        $userId = $jwt ? (int)$jwt['sub'] : null;
        $authorName = $jwt ? ($jwt['username'] ?? 'Utilisateur') : trim($_POST['author_name'] ?? 'Anonyme');
        $visitorHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($jwt ? $jwt['sub'] : 'anon'));
        $result = (new Photo())->upload($via['id'], $_FILES['photo'], $userId, $authorName, $visitorHash, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!is_int($result) || $result <= 0) {
            merr('Erreur lors de l\'upload photo: ' . (is_string($result) ? $result : 'unknown'));
        }
        mok(['photo_id' => $result, 'pending_review' => true], 201);
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

    // Flatten favorites rows → nested {via: {name, slug}} for app
    $recentFavs = $favModel->getByUser($uid, null);
    $recentFavsFormatted = array_map(function($f) {
        return [
            'id'         => (int)($f['via_id'] ?? $f['id'] ?? 0),
            'via_id'     => (int)($f['via_id'] ?? 0),
            'status'     => $f['status'] ?? 'to_do',
            'created_at' => $f['updated_at'] ?? $f['created_at'] ?? '',
            'via' => [
                'id'   => (int)($f['via_id'] ?? 0),
                'name' => $f['name'] ?? ('Via #' . ($f['via_id'] ?? '?')),
                'slug' => $f['slug'] ?? '',
            ],
        ];
    }, array_slice($recentFavs, 0, 5));

    // Flatten logbook rows → nested {via: {name, slug}} for app
    $recentLog = $logModel->getByUser($uid);
    $recentLogFormatted = array_map(function($l) {
        return [
            'id'         => (int)($l['id'] ?? 0),
            'via_id'     => (int)($l['via_id'] ?? 0),
            'done_date'  => $l['done_date'] ?? '',
            'conditions' => $l['conditions'] ?? null,
            'companion'  => $l['companion'] ?? null,
            'notes'      => $l['notes'] ?? null,
            'via' => [
                'id'   => (int)($l['via_id'] ?? 0),
                'name' => $l['via_name'] ?? $l['name'] ?? ('Via #' . ($l['via_id'] ?? '?')),
                'slug' => $l['via_slug'] ?? $l['slug'] ?? '',
            ],
        ];
    }, array_slice($recentLog, 0, 5));

    mok([
        'stats' => [
            'favorites_count'   => $favModel->countByUser($uid),
            'to_do_count'       => $favModel->countByUser($uid, 'to_do'),
            'done_count'        => $favModel->countByUser($uid, 'done'),
            'logbook_count'     => $logModel->countByUser($uid),
            'logbook_this_year' => $logModel->countThisYear($uid),
            'trips_count'       => count($tripModel->getByUser($uid)),
        ],
        'recent_favorites' => $recentFavsFormatted,
        'recent_logbook'   => $recentLogFormatted,
        'trips'            => $tripModel->getByUser($uid),
    ]);
}

// ═══════════════════════════════════════════════════════════════════
// SUBMIT  (public — via proposal)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'submit' && $method === 'POST') {
    $b = body();
    if (!empty(TURNSTILE_SECRET_KEY) && !verifyCloudflareTurnstile($b['turnstile_token'] ?? null)) {
        merr('Vérification anti-spam échouée. Complétez le captcha.', 422);
    }
    $name     = trim($b['name'] ?? '');
    $location = trim($b['location'] ?? '');
    if (!$name || !$location) merr('Nom et localisation requis');

    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO vias (name, location, latitude, longitude, difficulty, duration_hours,
                           approach_time, return_time, elevation_gain, description,
                           submitted_by, is_active, is_approved, created_at, updated_at)
         VALUES (:name,:location,:lat,:lng,:diff,:dur,:app,:ret,:elev,:desc,:email,0,0,NOW(),NOW())",
        [
            ':name'     => $name,
            ':location' => $location,
            ':lat'      => isset($b['latitude'])      ? (float)$b['latitude']      : null,
            ':lng'      => isset($b['longitude'])     ? (float)$b['longitude']     : null,
            ':diff'     => isset($b['difficulty'])    ? (int)$b['difficulty']      : null,
            ':dur'      => isset($b['duration_hours'])? (float)$b['duration_hours']: null,
            ':app'      => isset($b['approach_time']) ? (int)$b['approach_time']   : null,
            ':ret'      => isset($b['return_time'])   ? (int)$b['return_time']     : null,
            ':elev'     => isset($b['elevation_gain'])? (int)$b['elevation_gain']  : null,
            ':desc'     => trim($b['description'] ?? '') ?: null,
            ':email'    => trim($b['author_email'] ?? '') ?: null,
        ]
    );
    mok(['submitted' => true], 201);
}

// ═══════════════════════════════════════════════════════════════════
// COUNTRIES / STATS  (public)
// ═══════════════════════════════════════════════════════════════════
if ($r0 === 'stats') {
    $db = Database::getInstance();
    $total     = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM vias WHERE is_active=1")['c'] ?? 0);
    $countries = $db->fetchAll("SELECT code_pays as country, COUNT(*) AS count FROM vias WHERE is_active=1 AND code_pays IS NOT NULL AND code_pays != '' GROUP BY code_pays ORDER BY count DESC") ?: [];
    mok(['total_vias' => $total, 'countries' => count($countries)]);
}

// ── API root ─────────────────────────────────────────────────────────────────
if ($r0 === '') {
    mok([
        'api'      => 'ViaFerrata-Monde Mobile API',
        'version'  => '1.1',
        'base_url' => '/mobile-api',
    ]);
}

merr('Endpoint inconnu', 404);
