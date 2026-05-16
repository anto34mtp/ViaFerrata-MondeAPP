<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// viaferrata_v2/index.php - Front Controller Unifié

require_once __DIR__ . '/config/config.php';

// ── Language switching (handled before routing) ──────────────────────────
if (isset($_GET['lang'])) {
    Lang::set($_GET['lang']);
    // Redirect to same URL without the lang param so it doesn't linger
    $clean = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['lang']);
    $qs = http_build_query($params);
    header('Location: ' . $clean . ($qs ? '?' . $qs : ''));
    exit;
}

$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$url_parts = explode('/', $url);
$segment0 = $url_parts[0] ?? '';
$segment1 = $url_parts[1] ?? '';

$auth = new Auth();

switch ($segment0) {
    case '':
    case 'accueil':
        require __DIR__ . '/views/home.php';
        break;

    case 'france':
        if (!empty($segment1)) {
            $via_slug = $segment1;
            require __DIR__ . '/views/via_detail.php';
        } else {
            require __DIR__ . '/views/country_list.php';
        }
        break;

    case 'monde':
        require __DIR__ . '/views/monde.php';
        break;

    case 'via':
        // /via?pays=france  or  /via/{slug}?pays=fr  (via detail handled by segment1)
        if (!empty($segment1)) {
            $via_slug = $segment1;
            require __DIR__ . '/views/via_detail.php';
        } else {
            require __DIR__ . '/views/via_list.php';
        }
        break;

    case 'pays':
        if (!empty($segment1)) {
            // /pays/{code}/{slug} → via detail pour ce pays
            $segment2 = $url_parts[2] ?? '';
            if (!empty($segment2)) {
                $via_slug  = $segment2;
                // Pour l'instant on affiche la fiche via standard
                require __DIR__ . '/views/via_detail.php';
            } else {
                require __DIR__ . '/views/pays_list.php';
            }
        } else {
            redirect(BASE_URL . '/monde');
        }
        break;

    case 'cgu':
        require __DIR__ . '/views/cgu.php';
        break;

    case 'proposer':
        require __DIR__ . '/views/submit_via.php';
        break;

    case 'contact':
        require __DIR__ . '/views/contact.php';
        break;

    case 'inscription':
        require __DIR__ . '/views/register.php';
        break;

    case 'connexion':
        require __DIR__ . '/views/login.php';
        break;

    case 'deconnexion':
        $auth->logout();
        redirect(BASE_URL . '/');
        break;

    case 'mon-espace':
        $auth->requireAuth(BASE_URL . '/connexion');
        require __DIR__ . '/views/dashboard.php';
        break;

    case 'road-trip':
        if ($segment1 === 'invite' && !empty($url_parts[2])) {
            $invite_token = $url_parts[2];
            require __DIR__ . '/views/road_trip_invite.php';
        } else {
            $auth->requireAuth(BASE_URL . '/connexion');
            $trip_id = (!empty($segment1) && ctype_digit($segment1)) ? (int)$segment1 : 0;
            require __DIR__ . '/views/road_trip.php';
        }
        break;

    case 'mobile-api':
        $mobile_path = implode('/', array_slice($url_parts, 1));
        require __DIR__ . '/api/mobile.php';
        break;

    case 'api':
        header('Content-Type: application/json; charset=utf-8');
        if (!$auth->isLoggedIn()) { echo json_encode(['ok' => false, 'msg' => 'Non connecté']); exit; }
        $userId  = $auth->getUserId();
        $apiAction = ($segment1 ?? '') . '/' . ($url_parts[2] ?? '');

        // ── Road trip API ────────────────────────────────────────────────
        if ($segment1 === 'trip') {
            $tripModel  = new RoadTrip();
            $tripAction = $url_parts[2] ?? '';

            // GET-only endpoint: user search for sharing
            if ($tripAction === 'search-users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $q      = trim($_GET['q'] ?? '');
                $tripId = (int)($_GET['trip_id'] ?? 0);
                if (!$tripModel->owns($tripId, $userId)) { echo json_encode([]); exit; }
                echo json_encode($tripModel->searchUsersToShare($tripId, $userId, $q));
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
            if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'msg'=>'CSRF']); exit; }

            switch ($tripAction) {
                case 'create':
                    $name = trim($_POST['trip_name'] ?? '');
                    if (empty($name)) { echo json_encode(['ok'=>false,'msg'=>'Nom requis']); exit; }
                    $nbDays = (int)($_POST['nb_days'] ?? 3);
                    if ($nbDays < 1) $nbDays = 1;
                    $id = $tripModel->create($userId, $name, trim($_POST['description'] ?? '') ?: null,
                        trim($_POST['start_date'] ?? '') ?: null, trim($_POST['end_date'] ?? '') ?: null, $nbDays);
                    if ($id) { redirect(BASE_URL . '/road-trip/' . $id); } else { redirect(BASE_URL . '/road-trip'); }
                    exit;

                case 'update':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $data = [];
                    if (!empty($_POST['trip_name'])) $data['name']        = trim($_POST['trip_name']);
                    if (isset($_POST['description'])) $data['description']= trim($_POST['description']) ?: null;
                    if (isset($_POST['start_date']))  $data['start_date'] = trim($_POST['start_date']) ?: null;
                    if (isset($_POST['end_date']))    $data['end_date']   = trim($_POST['end_date']) ?: null;
                    if (isset($_POST['nb_days']))     $data['nb_days']    = max(1,(int)$_POST['nb_days']);
                    $ok = $tripModel->update($tripId, $userId, $data);
                    redirect(BASE_URL . '/road-trip/' . $tripId);
                    exit;

                case 'delete':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $tripModel->delete($tripId, $userId);
                    redirect(BASE_URL . '/road-trip');
                    exit;

                case 'add-via':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $viaId  = (int)($_POST['via_id']  ?? 0);
                    $day    = max(1,(int)($_POST['day_number'] ?? 1));
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false]); exit; }
                    $ok = $tripModel->addVia($tripId, $viaId, $day);
                    echo json_encode(['ok' => $ok]);
                    exit;

                case 'remove-via':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $viaId  = (int)($_POST['via_id']  ?? 0);
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false]); exit; }
                    $ok = $tripModel->removeVia($tripId, $viaId);
                    echo json_encode(['ok' => $ok]);
                    exit;

                case 'move-via':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $viaId  = (int)($_POST['via_id']  ?? 0);
                    $day    = max(1,(int)($_POST['day_number'] ?? 1));
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false]); exit; }
                    $ok = $tripModel->moveViaToDay($tripId, $viaId, $day);
                    echo json_encode(['ok' => $ok]);
                    exit;

                case 'reorder':
                    $tripId  = (int)($_POST['trip_id'] ?? 0);
                    $day     = max(1,(int)($_POST['day'] ?? 1));
                    $viaIds  = json_decode($_POST['via_ids'] ?? '[]', true);
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false]); exit; }
                    $ok = $tripModel->reorderDay($tripId, $day, $viaIds);
                    echo json_encode(['ok' => $ok]);
                    exit;

                case 'share':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false,'msg'=>'access']); exit; }
                    $type = $_POST['type'] ?? '';
                    if ($type === 'user') {
                        $targetId = (int)($_POST['user_id'] ?? 0);
                        $ok = $tripModel->shareWithUser($tripId, $userId, $targetId);
                        echo json_encode(['ok' => $ok]);
                    } elseif ($type === 'email') {
                        $email = trim($_POST['email'] ?? '');
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'msg'=>'invalid_email']); exit; }
                        $result = $tripModel->shareByEmail($tripId, $userId, $email);
                        // Send invite email if a new token was created
                        if ($result['ok'] && ($result['type'] ?? '') === 'invite') {
                            $tripData  = $tripModel->getById($tripId);
                            $inviteUrl = BASE_URL . '/road-trip/invite/' . $result['token'];
                            $ownerName = $auth->getUsername();
                            $tripName  = $tripData['name'] ?? 'Road Trip';
                            $nbDays    = (int)($tripData['nb_days'] ?? 1);
                            $startDate = !empty($tripData['start_date']) ? date('d/m/Y', strtotime($tripData['start_date'])) : '';

                            $eOwner    = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
                            $eTrip     = htmlspecialchars($tripName,  ENT_QUOTES, 'UTF-8');
                            $eUrl      = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
                            $eDate     = htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8');
                            $year      = date('Y');
                            $statsDate = $eDate ? "<td width=\"33%\" style=\"text-align:center;padding:14px 8px;\"><div style=\"font-size:22px;margin-bottom:6px;\">📅</div><div style=\"font-size:15px;font-weight:700;color:#0f172a;\">{$eDate}</div><div style=\"font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-top:3px;\">départ</div></td>" : '';

                            $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Invitation Road Trip — ViaFerrata-Monde.fr</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:40px 16px;">
  <tr><td align="center">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;">

    <!-- ── HEADER ── -->
    <tr>
      <td style="background:linear-gradient(150deg,#0f172a 0%,#064e3b 60%,#065f46 100%);border-radius:20px 20px 0 0;padding:44px 40px 36px;text-align:center;">
        <!-- Mountain SVG decoration -->
        <div style="margin-bottom:4px;">
          <svg width="260" height="40" viewBox="0 0 260 40" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:.18;">
            <path d="M0 40 L30 12 L55 26 L85 4 L115 22 L140 8 L168 20 L195 6 L225 18 L260 40Z" fill="white"/>
          </svg>
        </div>
        <div style="font-size:52px;line-height:1;margin-bottom:14px;">🗺️</div>
        <h1 style="margin:0 0 6px;color:#ffffff;font-size:26px;font-weight:800;letter-spacing:-.3px;">Invitation Road Trip</h1>
        <p style="margin:0;color:#6ee7b7;font-size:13px;font-weight:500;letter-spacing:.05em;text-transform:uppercase;">ViaFerrata-Monde.fr</p>
      </td>
    </tr>

    <!-- ── BODY ── -->
    <tr>
      <td style="background:#ffffff;padding:40px 40px 32px;">

        <!-- Intro text -->
        <p style="margin:0 0 6px;color:#64748b;font-size:13px;text-align:center;text-transform:uppercase;letter-spacing:.08em;font-weight:600;">vous avez reçu une invitation</p>
        <h2 style="margin:0 0 30px;color:#0f172a;font-size:22px;font-weight:800;text-align:center;line-height:1.35;">
          <span style="color:#10b981;">{$eOwner}</span> vous invite à découvrir son road trip
        </h2>

        <!-- Trip card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:16px;margin-bottom:30px;overflow:hidden;">
          <tr>
            <!-- Left accent bar -->
            <td width="5" style="background:linear-gradient(180deg,#10b981,#059669);border-radius:16px 0 0 16px;">&nbsp;</td>
            <td style="padding:22px 22px 22px 20px;">
              <!-- Trip name row -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="46" style="vertical-align:top;">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,#10b981,#059669);border-radius:11px;text-align:center;line-height:42px;font-size:20px;">🏔️</div>
                  </td>
                  <td style="padding-left:14px;vertical-align:middle;">
                    <p style="margin:0 0 3px;color:#0f172a;font-size:18px;font-weight:800;line-height:1.2;">{$eTrip}</p>
                    <p style="margin:0;color:#64748b;font-size:13px;">Partagé par <strong style="color:#10b981;">{$eOwner}</strong></p>
                  </td>
                </tr>
              </table>
              <!-- Stats -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="margin-top:18px;border-top:1px solid #e2e8f0;">
                <tr>
                  <td width="50%" style="text-align:center;padding:14px 8px;border-right:1px solid #e2e8f0;">
                    <div style="font-size:22px;margin-bottom:6px;">📆</div>
                    <div style="font-size:22px;font-weight:800;color:#0f172a;">{$nbDays}</div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-top:3px;">jour(s)</div>
                  </td>
                  {$statsDate}
                  <td style="text-align:center;padding:14px 8px;">
                    <div style="font-size:22px;margin-bottom:6px;">🧗</div>
                    <div style="font-size:15px;font-weight:700;color:#0f172a;">Via Ferrata</div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-top:3px;">activité</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- CTA Button -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
          <tr>
            <td align="center">
              <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$eUrl}" style="height:54px;v-text-anchor:middle;width:280px;" arcsize="25%" stroke="f" fillcolor="#10b981"><w:anchorlock/><center style="color:#fff;font-family:sans-serif;font-size:16px;font-weight:700;">🗺️ &nbsp;Voir le road trip</center></v:roundrect><![endif]-->
              <!--[if !mso]><!-->
              <a href="{$eUrl}"
                 style="display:inline-block;background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:800;padding:17px 44px;border-radius:14px;letter-spacing:.01em;box-shadow:0 6px 20px rgba(16,185,129,.38);">
                🗺️ &nbsp;Voir le road trip
              </a>
              <!--<![endif]-->
            </td>
          </tr>
        </table>

        <!-- Note account -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;margin-bottom:4px;">
          <tr>
            <td style="padding:14px 20px;text-align:center;">
              <p style="margin:0;color:#166534;font-size:13px;line-height:1.6;">
                💡 Vous devrez <strong>créer un compte gratuit</strong> ou vous connecter<br>pour accéder au road trip.
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>

    <!-- ── FALLBACK LINK ── -->
    <tr>
      <td style="background:#f8fafc;padding:18px 40px;border-top:1px solid #e2e8f0;">
        <p style="margin:0;color:#94a3b8;font-size:11px;text-align:center;line-height:1.7;">
          Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
          <a href="{$eUrl}" style="color:#10b981;word-break:break-all;">{$eUrl}</a>
        </p>
      </td>
    </tr>

    <!-- ── FOOTER ── -->
    <tr>
      <td style="background:#f1f5f9;border-radius:0 0 20px 20px;padding:22px 40px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0 0 6px;color:#64748b;font-size:12px;">
          <strong>ViaFerrata-Monde.fr</strong> — Le portail des via ferrata de France et d'Europe
        </p>
        <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">
          Vous recevez cet email car <strong>{$eOwner}</strong> vous a partagé ce road trip.<br>
          Si vous ne souhaitez pas le voir, ignorez simplement ce message.<br>
          © {$year} ViaFerrata-Monde.fr
        </p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>

</body>
</html>
HTML;
                            $subject = "{$eOwner} vous invite à voir son road trip : {$eTrip}";
                            sendMail($email, $subject, $htmlBody);
                        }
                        echo json_encode($result);
                    } else {
                        echo json_encode(['ok'=>false,'msg'=>'invalid_type']);
                    }
                    exit;

                case 'unshare':
                    $tripId  = (int)($_POST['trip_id'] ?? 0);
                    $shareId = (int)($_POST['share_id'] ?? 0);
                    if (!$tripModel->owns($tripId, $userId)) { echo json_encode(['ok'=>false]); exit; }
                    $ok = $tripModel->removeShare($tripId, $userId, $shareId);
                    echo json_encode(['ok' => $ok]);
                    exit;

                default:
                    echo json_encode(['ok'=>false,'msg'=>'Action inconnue']); exit;
            }
        }

        switch ($apiAction) {
            case 'logbook/save':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
                $viaId = (int)($_POST['via_id'] ?? 0);
                if (!$viaId) { echo json_encode(['ok'=>false,'msg'=>'ID via manquant']); exit; }
                $logbook = new Logbook();
                $fav     = new Favorite();
                $fav->addOrUpdate($userId, $viaId, 'done');
                $ok = $logbook->save(
                    $userId, $viaId,
                    trim($_POST['done_date']  ?? ''),
                    trim($_POST['conditions'] ?? ''),
                    trim($_POST['companion']  ?? ''),
                    trim($_POST['notes']      ?? '')
                );
                echo json_encode(['ok' => $ok]);
                exit;

            case 'logbook/delete':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
                $entryId = (int)($_POST['entry_id'] ?? 0);
                $logbook = new Logbook();
                $ok = $logbook->delete($userId, $entryId);
                echo json_encode(['ok' => $ok]);
                exit;

            case 'favorite/remove':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
                $viaId = (int)($_POST['via_id'] ?? 0);
                $fav   = new Favorite();
                $ok    = $fav->remove($userId, $viaId);
                echo json_encode(['ok' => $ok]);
                exit;

            case 'favorite/done':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
                $viaId = (int)($_POST['via_id'] ?? 0);
                $fav   = new Favorite();
                $ok    = $fav->addOrUpdate($userId, $viaId, 'done');
                echo json_encode(['ok' => $ok]);
                exit;

            default:
                http_response_code(404);
                echo json_encode(['ok'=>false,'msg'=>'Endpoint inconnu']);
                exit;
        }

    case 'admin':
        $auth->requireAuth(BASE_URL . '/connexion');
        $adminPages = ['vias','comments','photos','submissions','users'];
        $adminSub   = in_array($segment1, $adminPages) ? $segment1 : '';
        require __DIR__ . '/views/admin/' . ($adminSub ?: 'index') . '.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/views/404.php';
        break;
}
