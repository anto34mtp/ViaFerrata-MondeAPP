<?php
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$viaModel = new ViaFerrata();
$ratingModel = new Rating();
$commentModel = new Comment();
$favoriteModel = new Favorite();
$photoModel = new Photo();

$slug = $via_slug ?? '';
if (empty($slug)) redirect(BASE_URL . '/france');

$via = $viaModel->getBySlug($slug);
if (!$via) {
    http_response_code(404);
    require __DIR__ . '/404.php'; exit;
}

// Translate via content if not French
$lang = Lang::get();
if ($lang !== 'fr') {
    $via = Translator::getViaTranslation($via, $lang);
}

$pageTitle = $via['name'];
$pageDesc = truncate(strip_tags($via['description'] ?? 'Fiche détaillée de la via ferrata ' . $via['name']), 150);

$ratings  = $ratingModel->getByVia($via['id']);
$comments = $commentModel->getByVia($via['id']);
$photos   = $photoModel->getApprovedByVia($via['id']);

$visitorHash = Rating::generateVisitorHash();
$hasVoted     = $ratingModel->hasVoted($via['id'], $visitorHash);
$hasCommented = $commentModel->hasCommented($via['id'], $visitorHash);

$favoriteStatus = null;
if ($auth->isLoggedIn()) {
    $favoriteStatus = $favoriteModel->getStatus($auth->getUserId(), $via['id']);
}

$error = ''; $success = getFlash('success') ?? '';

// --- POST Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        switch ($_POST['action']) {

            case 'rate':
                if ($hasVoted) {
                    $error = 'Vous avez déjà noté cette via ferrata.';
                } else {
                    $rG = floatval($_POST['rating_general'] ?? 0);
                    $rB = floatval($_POST['rating_beauty'] ?? 0);
                    $rD = floatval($_POST['rating_difficulty'] ?? 0);
                    if ($rG < 1 || $rG > 10 || $rB < 1 || $rB > 10 || $rD < 1 || $rD > 10) {
                        $error = 'Chaque note doit être entre 1 et 10.';
                    } else {
                        $ok = $ratingModel->create($via['id'], $rG, $rB, $rD, $auth->getUserId(), $visitorHash, $_SERVER['REMOTE_ADDR'] ?? null);
                        if ($ok) { $success = 'Note enregistrée avec succès !'; $hasVoted = true; header("Refresh:0"); }
                        else { $error = 'Erreur lors de l\'enregistrement de la note.'; }
                    }
                }
                break;

            case 'comment':
                if ($hasCommented && !$auth->isLoggedIn()) {
                    $error = 'Vous avez déjà commenté cette via ferrata.';
                } elseif (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
                    $error = 'Vérification anti-spam échouée. Veuillez réessayer.';
                } else {
                    $authorName = $auth->isLoggedIn() ? $auth->getUsername() : trim($_POST['author_name'] ?? '');
                    $content    = trim($_POST['content'] ?? '');
                    if (empty($authorName) || strlen($content) < 10) {
                        $error = 'Tous les champs sont requis (commentaire min. 10 caractères).';
                    } else {
                        $ok = $commentModel->create($via['id'], $authorName, $content, $auth->getUserId(), $visitorHash, $auth->getUserEmail(), $_SERVER['REMOTE_ADDR'] ?? null);
                        if ($ok) { $success = 'Commentaire publié !'; $hasCommented = true; header("Refresh:0"); }
                        else { $error = 'Erreur lors de la publication du commentaire.'; }
                    }
                }
                break;

            case 'reply':
                if (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
                    $error = 'Vérification anti-spam échouée. Veuillez réessayer.';
                } else {
                    $parentId   = (int)($_POST['parent_comment_id'] ?? 0);
                    $authorName = $auth->isLoggedIn() ? $auth->getUsername() : trim($_POST['author_name'] ?? 'Anonyme');
                    $content    = trim($_POST['content'] ?? '');
                    if ($parentId < 1 || strlen($content) < 2) {
                        $error = 'Réponse invalide (2 caractères minimum).';
                    } else {
                        $ok = $commentModel->createReply($via['id'], $parentId, $authorName, $content, $auth->getUserId(), $visitorHash, $auth->getUserEmail(), $_SERVER['REMOTE_ADDR'] ?? null);
                        if ($ok) { $success = 'Réponse publiée !'; header("Refresh:1"); }
                        else { $error = 'Erreur lors de la publication de la réponse.'; }
                    }
                }
                break;

            case 'favorite':
                if (!$auth->isLoggedIn()) redirect(BASE_URL . '/connexion');
                $status = $_POST['status'] ?? 'to_do';
                if ($favoriteStatus === $status) { $favoriteModel->remove($auth->getUserId(), $via['id']); $favoriteStatus = null; }
                else { $favoriteModel->addOrUpdate($auth->getUserId(), $via['id'], $status); $favoriteStatus = $status; }
                header("Refresh:0"); exit;

            case 'upload_photo':
                if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    $phpErr = $_FILES['photo']['error'] ?? -1;
                    $error = match($phpErr) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux. La limite est de 20 Mo.',
                        default => 'Erreur lors de l\'upload du fichier. Code: ' . $phpErr,
                    };
                } elseif (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
                    $error = 'Vérification anti-spam échouée. Veuillez réessayer.';
                } elseif (!$photoModel->canUpload($via['id'], $visitorHash)) {
                    $error = 'Vous avez atteint la limite de photos pour cette via.';
                } else {
                    $authorName = $auth->isLoggedIn() ? $auth->getUsername() : 'Anonyme';
                    $result = $photoModel->upload($via['id'], $_FILES['photo'], $auth->getUserId(), $authorName, $visitorHash, $_SERVER['REMOTE_ADDR'] ?? null);
                    if (is_int($result) && $result > 0) {
                        $success = 'Photo envoyée ! Elle sera visible après validation d\'un modérateur.';
                        header("Refresh:2");
                    } else {
                        $errCode = is_string($result) ? $result : 'inconnu';
                        $error = 'Upload refusé [' . htmlspecialchars($errCode) . ']. Formats : JPG, PNG, WEBP, GIF, AVIF — Max 20 Mo.';
                    }
                }
                break;

            case 'delete_comment':
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $commentToDelete = $commentModel->getById($commentId);
                if (!$commentToDelete) {
                    $error = 'Commentaire introuvable.';
                } elseif ($auth->isModerator() || (int)($commentToDelete['user_id'] ?? -1) === (int)$auth->getUserId()) {
                    $ok = $commentModel->delete($commentId);
                    if ($ok) { $success = 'Commentaire supprimé.'; header("Refresh:1"); }
                    else { $error = 'Erreur lors de la suppression.'; }
                } else {
                    $error = 'Vous n\'avez pas la permission de supprimer ce commentaire.';
                }
                break;

            case 'delete_photo':
                if (!$auth->isModerator()) {
                    $error = 'Accès refusé.';
                } else {
                    $photoId = (int)($_POST['photo_id'] ?? 0);
                    $ok = $photoModel->delete($photoId);
                    if ($ok) { $success = 'Photo supprimée.'; header("Refresh:1"); }
                    else { $error = 'Erreur lors de la suppression.'; }
                }
                break;

            case 'admin_edit_via':
                if (!$auth->isModerator()) { $error = 'Accès refusé.'; break; }
                $editData = [
                    'name'                  => trim($_POST['edit_name'] ?? ''),
                    'location'              => trim($_POST['edit_location'] ?? ''),
                    'latitude'              => $_POST['edit_latitude'] !== '' ? floatval($_POST['edit_latitude']) : null,
                    'longitude'             => $_POST['edit_longitude'] !== '' ? floatval($_POST['edit_longitude']) : null,
                    'difficulty'            => (int)($_POST['edit_difficulty'] ?? 5),
                    'duration_hours'        => $_POST['edit_duration'] !== '' ? floatval($_POST['edit_duration']) : null,
                    'estimated_duration'    => $_POST['edit_duration'] !== '' ? floatval($_POST['edit_duration']) : null,
                    'approach_time'         => $_POST['edit_approach_time'] !== '' ? (int)$_POST['edit_approach_time'] : null,
                    'return_time'           => $_POST['edit_return_time'] !== '' ? (int)$_POST['edit_return_time'] : null,
                    'elevation_gain'        => $_POST['edit_elevation_gain'] !== '' ? (int)$_POST['edit_elevation_gain'] : null,
                    'altitude_max'          => $_POST['edit_altitude_max'] !== '' ? (int)$_POST['edit_altitude_max'] : null,
                    'length_km'             => $_POST['edit_length_km'] !== '' ? floatval($_POST['edit_length_km']) : null,
                    'opening_status'        => $_POST['edit_opening_status'] ?? 'ouvert',
                    'opening_period'        => trim($_POST['edit_opening_period'] ?? ''),
                    'pricing'               => $_POST['edit_pricing'] ?? 'gratuit',
                    'description'           => $_POST['edit_description'] ?? '',
                    'image_url'             => trim($_POST['edit_image_url'] ?? ''),
                    'google_maps_url'       => trim($_POST['edit_google_maps_url'] ?? ''),
                    'description_link'      => trim($_POST['edit_description_link'] ?? ''),
                    'rental_equipment_url'  => trim($_POST['edit_rental_equipment_url'] ?? ''),
                    'tourism_office_name'   => trim($_POST['edit_tourism_office_name'] ?? ''),
                    'tourism_office_phone'  => trim($_POST['edit_tourism_office_phone'] ?? ''),
                    'tourism_office_email'  => trim($_POST['edit_tourism_office_email'] ?? ''),
                ];
                if (empty($editData['name'])) { $error = 'Le nom est obligatoire.'; break; }
                $ok = $viaModel->update($via['id'], $editData);
                if ($ok) {
                    $_SESSION['flash']['success'] = 'Via mise à jour avec succès.';
                    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
                } else {
                    $error = 'Erreur lors de la mise à jour.';
                }
                break;
        }
    }
}

$csrfToken = $auth->generateCsrfToken();

// ---- Image ----
$imageUrl = !empty($via['image_url']) ? $via['image_url'] : BASE_URL . '/assets/images/default.png';

// ---- Difficulté label ----
$diffInt   = (int)($via['difficulty'] ?? $via['difficulty_rating'] ?? 5);
$diffLabel = getDifficultyLabel($diffInt);

// ---- Navigation entre parties ----
$group_parts = [];
$pays_param  = isset($_GET['pays']) ? strtolower(trim($_GET['pays'])) : 'fr';
try {
    $pdo_d = Database::getInstance()->getConnection();
    $col_ok = $pdo_d->query("SHOW COLUMNS FROM vias LIKE 'parent_id'")->rowCount() > 0;
    if ($col_ok) {
        // Racine du groupe : soit le parent_id de la via courante, soit la via elle-même
        $root_id = !empty($via['parent_id']) ? (int)$via['parent_id'] : (int)$via['id'];
        $parts_stmt = $pdo_d->prepare("
            SELECT id, name, slug, part_number
            FROM vias
            WHERE (id = :root OR parent_id = :root2)
              AND is_active = 1
            ORDER BY COALESCE(part_number, 0)
        ");
        $parts_stmt->execute(['root' => $root_id, 'root2' => $root_id]);
        $all_parts = $parts_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($all_parts) >= 2) {
            $group_parts = $all_parts;
        }
    }
} catch (Exception $_e) {
    $group_parts = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Image (FIX 1.1 applied) -->
<div class="relative bg-slate-900 h-56 md:h-80 w-full flex items-end pb-6">
    <div class="absolute inset-0">
        <img src="<?= escape($imageUrl) ?>" alt="<?= escape($via['name']) ?>"
             class="w-full h-full object-cover opacity-60"
             onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/50 to-transparent"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full z-10">
        <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="bg-brand-500 text-white text-xs font-bold px-2.5 py-1 rounded-md"><?= escape($diffLabel) ?></span>
            <?php if (!empty($via['department_code'])): ?><span class="bg-white/20 backdrop-blur text-white text-xs font-medium px-2 py-1 rounded-md">📍 <?= escape($via['department_code']) ?> — <?= escape($via['department_name'] ?? '') ?></span><?php endif; ?>
            <?php if (!empty($via['opening_status'])): ?><span class="bg-white/20 backdrop-blur text-white text-xs font-medium px-2 py-1 rounded-md"><?= $via['opening_status']==='ouvert' ? '✅ Ouvert' : ($via['opening_status']==='ferme' ? '⚠️ Fermé temporairement' : '🚫 Fermé') ?></span><?php endif; ?>
        </div>
        <h1 class="text-2xl md:text-4xl font-bold text-white drop-shadow-md"><?= escape($via['name']) ?></h1>
    </div>
</div>

<!-- Content Layout -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

    <!-- Breadcrumb -->
    <nav class="flex text-sm text-slate-500 mb-6 gap-1.5 flex-wrap items-center">
        <a href="<?= BASE_URL ?>/" class="hover:text-brand-600">Accueil</a>
        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="<?= BASE_URL ?>/france" class="hover:text-brand-600">France</a>
        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-slate-700 font-medium"><?= escape($via['name']) ?></span>
    </nav>

    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm"><?= escape($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm"><?= escape($success) ?></div><?php endif; ?>

    <?php
    $viaStatus = $via['opening_status'] ?? 'ouvert';
    if ($viaStatus === 'ferme_definitif'):
    ?>
    <div class="mb-5 bg-red-50 border border-red-300 rounded-xl px-5 py-4 flex items-start gap-3">
        <span class="text-2xl flex-shrink-0">🚫</span>
        <div>
            <p class="font-bold text-red-800 text-sm">Cette via ferrata est fermée définitivement</p>
            <?php if (!empty($via['closure_reason'])): ?>
            <p class="text-red-700 text-sm mt-1"><?= escape($via['closure_reason']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($viaStatus === 'ferme'): ?>
    <div class="mb-5 bg-amber-50 border border-amber-300 rounded-xl px-5 py-4 flex items-start gap-3">
        <span class="text-2xl flex-shrink-0">⚠️</span>
        <div>
            <p class="font-bold text-amber-800 text-sm">Cette via ferrata est temporairement fermée</p>
            <?php if (!empty($via['closure_reason'])): ?>
            <p class="text-amber-700 text-sm mt-1"><?= escape($via['closure_reason']) ?></p>
            <?php endif; ?>
            <?php if (!empty($via['closure_end_date'])): ?>
            <p class="text-amber-600 text-xs mt-1">Réouverture prévue : <strong><?= escape($via['closure_end_date']) ?></strong></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation entre parties -->
    <?php if (!empty($group_parts)): ?>
    <div class="mb-6 bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">⛓ Itinéraire multi-parties — <?= count($group_parts) ?> parties</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($group_parts as $part): ?>
            <?php $is_current = (int)$part['id'] === (int)$via['id']; ?>
            <a href="<?= BASE_URL ?>/via/<?= escape($part['slug']) ?>?pays=<?= escape($pays_param) ?>"
               class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all
                      <?= $is_current
                          ? 'bg-brand-500 text-white border-brand-500 shadow-sm cursor-default pointer-events-none'
                          : 'bg-white text-slate-700 border-slate-300 hover:border-brand-400 hover:text-brand-600 hover:bg-brand-50' ?>">
                <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                             <?= $is_current ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= (int)($part['part_number'] ?? 0) ?>
                </span>
                <span class="max-w-[180px] truncate" title="<?= escape($part['name']) ?>">
                    Partie <?= (int)($part['part_number'] ?? 0) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- LEFT: Main Info -->
        <div class="w-full lg:w-2/3 space-y-6">

            <!-- Stats Grid (FIX 1.4: Département, Difficulté, Approche, Retour) -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <?php if (!empty($via['department_code'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">🏔️ Département</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['department_code']) ?> – <?= escape($via['department_name'] ?? '') ?></p></div>
                    <?php endif; ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">⚠️ Difficulté</p><p class="font-semibold text-slate-900 text-sm"><?= escape($diffLabel) ?></p></div>
                    <?php if (!empty($via['estimated_duration'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">⏱️ Durée totale</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['estimated_duration']) ?>h</p></div>
                    <?php endif; ?>
                    <?php if (!empty($via['approach_time'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">🚶 Temps d'approche</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['approach_time']) ?> min</p></div>
                    <?php endif; ?>
                    <?php if (!empty($via['return_time'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">🔙 Temps de retour</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['return_time']) ?> min</p></div>
                    <?php endif; ?>
                    <?php if (!empty($via['elevation_gain'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">📈 Dénivelé</p><p class="font-semibold text-slate-900 text-sm">+<?= escape($via['elevation_gain']) ?>m</p></div>
                    <?php endif; ?>
                    <?php if (!empty($via['altitude_max'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">⛰️ Altitude max</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['altitude_max']) ?>m</p></div>
                    <?php endif; ?>
                    <?php if (!empty($via['length_km'])): ?>
                    <div><p class="text-xs text-slate-500 mb-0.5">📏 Longueur</p><p class="font-semibold text-slate-900 text-sm"><?= escape($via['length_km']) ?> km</p></div>
                    <?php endif; ?>
                </div>

                <!-- Favorite & Road Trip Buttons -->
                <?php if ($auth->isLoggedIn()): ?>
                <hr class="my-4 border-slate-100">
                <div class="flex flex-wrap gap-2">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="favorite">
                        <input type="hidden" name="status" value="to_do">
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors <?= $favoriteStatus === 'to_do' ? 'bg-brand-500 text-white border-brand-500' : 'bg-white text-slate-700 border-slate-300 hover:border-brand-400 hover:text-brand-600' ?>">
                            <?= $favoriteStatus === 'to_do' ? '✓ ' : '' ?><?= t('btn_todo') ?>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="favorite">
                        <input type="hidden" name="status" value="done">
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors <?= $favoriteStatus === 'done' ? 'bg-green-500 text-white border-green-500' : 'bg-white text-slate-700 border-slate-300 hover:border-green-400 hover:text-green-600' ?>">
                            <?= $favoriteStatus === 'done' ? '✓ ' : '' ?><?= t('btn_done') ?>
                        </button>
                    </form>
                    <!-- Road trip button -->
                    <button onclick="document.getElementById('add-trip-modal').classList.remove('hidden')"
                            class="px-4 py-2 rounded-lg text-sm font-semibold border border-slate-300 bg-white text-slate-700 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                        <?= t('btn_add_trip') ?>
                    </button>
                </div>
                <?php else: ?>
                <p class="text-xs text-slate-400 mt-3"><a href="<?= BASE_URL ?>/connexion" class="text-brand-600 hover:underline"><?= t('login_cta') ?></a> <?= t('login_to_list') ?></p>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-900 mb-3">À propos de la voie</h2>
                <div class="text-slate-600 leading-relaxed text-sm prose prose-sm max-w-none description-html">
                    <?= safeHtml($via['description'] ?? '<p>Aucune description disponible.</p>') ?>
                </div>
            </div>

            <!-- Average Ratings Display (2.1) -->
            <?php if (!empty($via['avg_general']) || !empty($via['avg_overall'])): ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-900 mb-4">⭐ Notes de la communauté</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                    <div><p class="text-xs text-slate-500 mb-1">Note générale</p><p class="text-2xl font-bold text-brand-600"><?= formatRating($via['avg_general'] ?? null) ?></p><p class="text-xs text-slate-400">/10</p></div>
                    <div><p class="text-xs text-slate-500 mb-1">🌄 Beauté</p><p class="text-2xl font-bold text-brand-600"><?= formatRating($via['avg_beauty'] ?? null) ?></p><p class="text-xs text-slate-400">/10</p></div>
                    <div><p class="text-xs text-slate-500 mb-1">⚠️ Difficulté ressentie</p><p class="text-2xl font-bold text-brand-600"><?= formatRating($via['avg_difficulty'] ?? null) ?></p><p class="text-xs text-slate-400">/10</p></div>
                    <div><p class="text-xs text-slate-500 mb-1">📊 Moyenne globale</p><p class="text-2xl font-bold text-brand-600"><?= formatRating($via['avg_overall'] ?? null) ?></p><p class="text-xs text-slate-400"><?= $via['total_ratings'] ?? 0 ?> vote(s)</p></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Vote Form (2.1) -->
            <?php if (!$hasVoted): ?>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-900 mb-4">✍️ Noter cette via ferrata</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="rate">
                    <?php
                    $ratingFields = [
                        ['name'=>'rating_general','label'=>'Note générale'],
                        ['name'=>'rating_beauty','label'=>'🌄 Beauté du parcours'],
                        ['name'=>'rating_difficulty','label'=>'⚠️ Difficulté ressentie'],
                    ];
                    foreach ($ratingFields as $rf): ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= $rf['label'] ?> <span class="text-slate-400 font-normal">(1–10)</span></label>
                        <input type="number" name="<?= $rf['name'] ?>" min="1" max="10" step="0.5" required
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none">
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="w-full py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl shadow-sm transition-colors text-sm">Envoyer ma note</button>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-500">✅ Vous avez déjà noté cette via ferrata.</div>
            <?php endif; ?>

            <!-- Photos Gallery (2.2) -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-900 mb-4">📸 Photos de la communauté (<?= count($photos) ?>)</h2>
                <?php if (!empty($photos)): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                    <?php foreach ($photos as $i => $photo): ?>
                    <div class="relative aspect-square rounded-lg overflow-hidden group">
                        <div class="cursor-pointer w-full h-full" onclick="openLightbox(<?= $i ?>)">
                            <img src="<?= BASE_URL ?>/<?= escape($photo['file_path']) ?>" alt="Par <?= escape($photo['author_name']) ?>" class="w-full h-full object-cover">
                        </div>
                        <?php if ($auth->isModerator()): ?>
                        <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirm('Supprimer cette photo ?')">
                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_photo">
                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                            <button type="submit" title="Supprimer" class="bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold shadow">×</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-slate-400 text-sm mb-3">Aucune photo pour le moment. Soyez le premier !</p>
                <?php endif; ?>

                <?php if ($photoModel->canUpload($via['id'], $visitorHash)): ?>
                <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="mt-1 px-4 py-2 border border-brand-400 text-brand-600 hover:bg-brand-50 rounded-lg text-sm font-medium transition-colors">
                    📷 Ajouter une photo
                </button>
                <p class="text-xs text-slate-400 mt-1">Visible après validation d'un modérateur</p>
                <?php endif; ?>
            </div>

            <!-- Comments (2.3) -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-900 mb-4">💬 Commentaires (<?= count($comments) ?>)</h2>

                <?php
                // Le script Turnstile est chargé une seule fois en bas de page
                ?>
                <form method="POST" class="space-y-3 mb-6 pb-6 border-b border-slate-100">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="comment">
                    <?php if (!$auth->isLoggedIn()): ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Votre nom</label>
                        <input type="text" name="author_name" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none">
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Commentaire <span class="text-slate-400 font-normal">(min. 10 caractères)</span></label>
                        <textarea name="content" required minlength="10" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500 outline-none resize-none"></textarea>
                    </div>
                    <?php if (!$auth->isLoggedIn()): ?>
                    <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
                    <?php endif; ?>
                    <button type="submit" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg text-sm transition-colors">Publier</button>
                </form>



                <div class="space-y-4">
                    <?php foreach ($comments as $comment): ?>
                    <?php $replies = $commentModel->getByParent($comment['id']); ?>
                    <div class="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                        <div class="flex justify-between items-start gap-2 mb-1">
                            <div>
                                <span class="font-semibold text-slate-900 text-sm"><?= escape($comment['author_name']) ?></span>
                                <span class="text-xs text-slate-400 ml-2"><?= formatDate($comment['created_at']) ?></span>
                            </div>
                            <?php
                            $canDeleteComment = $auth->isModerator() ||
                                ($auth->isLoggedIn() && (int)($comment['user_id'] ?? -1) === (int)$auth->getUserId());
                            ?>
                            <div class="flex gap-3 items-center flex-shrink-0">
                                <button onclick="toggleReply(<?= $comment['id'] ?>)" class="text-xs text-brand-600 hover:text-brand-800 hover:underline">💬 Répondre</button>
                                <?php if ($canDeleteComment): ?>
                                <form method="POST" onsubmit="return confirm('Supprimer ce commentaire ?')">
                                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">🗑️ Supprimer</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br(escape($comment['content'])) ?></p>

                        <!-- Réponses existantes -->
                        <?php if (!empty($replies)): ?>
                        <div class="mt-3 ml-4 pl-3 border-l-2 border-brand-200 space-y-2">
                            <?php foreach ($replies as $reply): ?>
                            <div class="text-sm">
                                <span class="font-semibold text-slate-800"><?= escape($reply['author_name']) ?></span>
                                <span class="text-xs text-slate-400 ml-1"><?= formatDate($reply['created_at']) ?></span>
                                <?php if ($auth->isModerator()): ?>
                                <form method="POST" class="inline ml-2" onsubmit="return confirm('Supprimer cette réponse ?')">
                                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?= $reply['id'] ?>">
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600">×</button>
                                </form>
                                <?php endif; ?>
                                <p class="text-slate-600 mt-0.5"><?= nl2br(escape($reply['content'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Formulaire de réponse (caché par défaut) -->
                        <div id="reply-form-<?= $comment['id'] ?>" class="hidden mt-3 ml-4 pl-3 border-l-2 border-slate-200">
                            <form method="POST" class="space-y-2">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="parent_comment_id" value="<?= $comment['id'] ?>">
                                <?php if (!$auth->isLoggedIn()): ?>
                                <input type="text" name="author_name" placeholder="Votre nom" required
                                       class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-brand-500 focus:border-brand-500">
                                <?php endif; ?>
                                <textarea name="content" required rows="2" placeholder="Votre réponse..."
                                          class="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm outline-none resize-none focus:ring-brand-500 focus:border-brand-500"></textarea>
                                <?php if (!$auth->isLoggedIn()): ?>
                                <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
                                <?php endif; ?>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-3 py-1.5 bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold rounded-lg transition-colors">Envoyer</button>
                                    <button type="button" onclick="toggleReply(<?= $comment['id'] ?>)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs rounded-lg transition-colors">Annuler</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($comments)): ?><p class="text-slate-400 text-sm">Aucun commentaire pour le moment.</p><?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT: Sidebar with Map & Info -->
        <div class="w-full lg:w-1/3 space-y-5">

            <!-- Mini Map (FIX 1.3) -->
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-200">
                <div id="mini-map" class="h-60 w-full bg-slate-100"></div>
                <div class="p-4 border-t border-slate-100 space-y-2">
                    <?php if (!empty($via['location'])): ?>
                    <p class="text-sm font-medium text-slate-700 flex items-center gap-1">📍 <?= escape($via['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($via['google_maps_url'])): ?>
                    <a href="<?= escape($via['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer"
                       class="block text-center w-full border border-slate-300 text-slate-700 rounded-lg py-2 text-sm hover:bg-slate-50 transition-colors">
                        🗺️ Ouvrir dans Google Maps
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($via['description_link'])): ?>
                    <a href="<?= escape($via['description_link']) ?>" target="_blank" rel="noopener noreferrer"
                       class="block text-center w-full bg-brand-500 hover:bg-brand-600 text-white rounded-lg py-2 text-sm font-semibold transition-colors">
                        📖 Voir plus d'informations
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Practical Info -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 mb-3">Informations pratiques</h3>
                <ul class="space-y-3 text-sm">
                    <?php if (!empty($via['opening_period'])): ?>
                    <li class="flex gap-2"><span>📅</span><span><strong>Période :</strong> <?= escape($via['opening_period']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($via['pricing'])): ?>
                    <li class="flex gap-2"><span>💶</span><span><strong>Tarif :</strong> <span class="capitalize"><?= escape($via['pricing']) ?></span></span></li>
                    <?php endif; ?>
                    <?php if (!empty($via['tourism_office_name'])): ?>
                    <li class="flex gap-2"><span>ℹ️</span><div><strong><?= escape($via['tourism_office_name']) ?></strong>
                        <?php if (!empty($via['tourism_office_phone'])): ?><br><a href="tel:<?= escape($via['tourism_office_phone']) ?>" class="text-brand-600 hover:underline"><?= escape($via['tourism_office_phone']) ?></a><?php endif; ?>
                        <?php if (!empty($via['tourism_office_email'])): ?><br><a href="mailto:<?= escape($via['tourism_office_email']) ?>" class="text-brand-600 hover:underline"><?= escape($via['tourism_office_email']) ?></a><?php endif; ?>
                    </div></li>
                    <?php endif; ?>
                    <?php if (!empty($via['rental_equipment_url'])): ?>
                    <li><a href="<?= escape($via['rental_equipment_url']) ?>" target="_blank" rel="noopener" class="text-brand-600 hover:underline">🎒 Louer le matériel</a></li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </div>
</div>

<!-- Photo Upload Modal (2.2) -->
<div id="upload-modal" class="hidden fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" onclick="this.classList.add('hidden')">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-900">📷 Ajouter une photo</h3>
            <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="action" value="upload_photo">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Photo (JPG, PNG, WEBP, AVIF, GIF &mdash; Max 20 Mo)</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
            <button type="submit" class="w-full py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl text-sm transition-colors">Envoyer la photo</button>
        </form>
    </div>
</div>

<style>
.description-html h2 { font-size:1.15rem; font-weight:700; color:#1e293b; margin:1rem 0 .4rem; }
.description-html h3 { font-size:1rem; font-weight:700; color:#334155; margin:.85rem 0 .35rem; }
.description-html h4 { font-size:.925rem; font-weight:600; color:#475569; margin:.75rem 0 .3rem; }
.description-html p  { margin:.5rem 0; }
.description-html ul,.description-html ol { padding-left:1.4rem; margin:.5rem 0; }
.description-html li { margin:.25rem 0; }
.description-html ul { list-style:disc; }
.description-html ol { list-style:decimal; }
.description-html strong,.description-html b { font-weight:700; color:#1e293b; }
.description-html em,.description-html i { font-style:italic; }
.description-html a { color:#2563eb; text-decoration:underline; }
.description-html blockquote { border-left:3px solid #e2e8f0; padding:.25rem .75rem; color:#64748b; margin:.5rem 0; font-style:italic; }
.description-html code { background:#f1f5f9; border-radius:.25rem; padding:.1rem .35rem; font-size:.85em; font-family:monospace; }
.description-html pre  { background:#f1f5f9; border-radius:.5rem; padding:.75rem 1rem; overflow-x:auto; font-size:.85em; font-family:monospace; margin:.5rem 0; }
.description-html hr  { border:none; border-top:1px solid #e2e8f0; margin:.75rem 0; }
.description-html table { width:100%; border-collapse:collapse; margin:.5rem 0; font-size:.85rem; }
.description-html th,.description-html td { padding:.4rem .7rem; border:1px solid #e2e8f0; }
.description-html th { background:#f8fafc; font-weight:600; text-align:left; }
.description-html img { max-width:100%; border-radius:.5rem; margin:.5rem 0; }
</style>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<!-- Cloudflare Turnstile (chargé une seule fois pour toute la page) -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>
// Toggle inline reply form
function toggleReply(commentId) {
    var form = document.getElementById('reply-form-' + commentId);
    if (form) form.classList.toggle('hidden');
}

// FIX 1.3 — Mini Map
document.addEventListener("DOMContentLoaded", function() {
    var lat = <?= !empty($via['latitude']) ? floatval($via['latitude']) : 'null' ?>;
    var lng = <?= !empty($via['longitude']) ? floatval($via['longitude']) : 'null' ?>;
    var mapDiv = document.getElementById('mini-map');
    if (lat && lng) {
        var minimap = L.map('mini-map', { zoomControl:false, scrollWheelZoom:false }).setView([lat,lng], 13);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {attribution:'&copy; CARTO'}).addTo(minimap);
        L.control.zoom({ position:'topright' }).addTo(minimap);
        var icon = L.divIcon({ className:'', html:'<div style="background:#10b981;width:18px;height:18px;border-radius:50%;border:3px solid white;box-shadow:0 0 6px rgba(0,0,0,.5)"></div>', iconSize:[18,18], iconAnchor:[9,9] });
        L.marker([lat,lng],{icon}).addTo(minimap);
    } else {
        mapDiv.innerHTML = '<div class="w-full h-full flex flex-col items-center justify-center text-slate-400 bg-slate-50 text-sm gap-2"><svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"/></svg><span>Coordonnées GPS non renseignées</span></div>';
    }
});

// Lightbox for photos
<?php if (!empty($photos)): ?>
const photosList = <?= json_encode(array_map(fn($p) => ['file_path'=>$p['file_path'], 'author_name'=>$p['author_name']], $photos)) ?>;
let lightboxIdx = 0;
function openLightbox(idx) {
    lightboxIdx = idx;
    document.getElementById('lb-img').src = '<?= BASE_URL ?>/' + photosList[idx].file_path;
    document.getElementById('lb-author').textContent = 'Par ' + photosList[idx].author_name;
    document.getElementById('lightbox').style.display = 'flex';
}
function closeLightbox() { document.getElementById('lightbox').style.display = 'none'; }
function changeLb(d) {
    lightboxIdx = (lightboxIdx + d + photosList.length) % photosList.length;
    openLightbox(lightboxIdx);
}
document.addEventListener('keydown', e => {
    if (document.getElementById('lightbox').style.display==='flex') {
        if (e.key==='ArrowLeft') changeLb(-1);
        if (e.key==='ArrowRight') changeLb(1);
        if (e.key==='Escape') closeLightbox();
    }
});
<?php endif; ?>
</script>

<?php if (!empty($photos)): ?>
<!-- Lightbox Overlay -->
<div id="lightbox" class="hidden fixed inset-0 bg-black/90 z-50 items-center justify-center" style="display:none" onclick="closeLightbox()">
    <button onclick="event.stopPropagation();changeLb(-1)" class="absolute left-4 text-white text-3xl font-bold opacity-70 hover:opacity-100">❮</button>
    <div class="flex flex-col items-center gap-2" onclick="event.stopPropagation()">
        <img id="lb-img" src="" alt="" class="max-h-[80vh] max-w-[90vw] rounded-xl object-contain">
        <p id="lb-author" class="text-white/60 text-sm"></p>
    </div>
    <button onclick="event.stopPropagation();changeLb(1)" class="absolute right-4 text-white text-3xl font-bold opacity-70 hover:opacity-100">❯</button>
    <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white text-2xl opacity-70 hover:opacity-100">✕</button>
</div>
<?php endif; ?>

<?php if ($auth->isModerator()): ?>
<!-- ══ BOUTON FLOTTANT ADMIN ══════════════════════════════════════════ -->
<button onclick="document.getElementById('admin-drawer').classList.remove('translate-x-full')"
        title="Modifier cette via"
        class="fixed bottom-6 right-6 z-40 flex items-center gap-2 px-4 py-3 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-2xl shadow-xl transition-colors">
    ✏️ Modifier la via
</button>

<!-- ══ SLIDE-OVER PANEL ADMIN ════════════════════════════════════════ -->
<div id="admin-drawer"
     class="fixed inset-y-0 right-0 z-50 w-full max-w-xl bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col">

    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50 flex-shrink-0">
        <h2 class="text-lg font-bold text-slate-900">✏️ Modifier la via</h2>
        <button onclick="document.getElementById('admin-drawer').classList.add('translate-x-full')"
                class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
    </div>

    <!-- Form -->
    <form method="POST" id="admin-edit-form" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <input type="hidden" name="action" value="admin_edit_via">

        <!-- Identité -->
        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Identité</legend>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="edit_name" required value="<?= escape($via['name']) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Localisation</label>
                <input type="text" name="edit_location" value="<?= escape($via['location'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                       placeholder="Ville / commune">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">URL image principale</label>
                <input type="text" name="edit_image_url" value="<?= escape($via['image_url'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                       placeholder="https://...">
            </div>
        </fieldset>

        <!-- GPS -->
        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">GPS &amp; Carte</legend>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                    <input type="number" name="edit_latitude" step="0.000001" value="<?= escape($via['latitude'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                    <input type="number" name="edit_longitude" step="0.000001" value="<?= escape($via['longitude'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Lien Google Maps</label>
                <input type="text" name="edit_google_maps_url" value="<?= escape($via['google_maps_url'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                       placeholder="https://maps.google.com/...">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Lien description (FVF / Camptocamp)</label>
                <input type="text" name="edit_description_link" value="<?= escape($via['description_link'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                       placeholder="https://franceviaferrata.fr/... ou https://www.camptocamp.org/...">
            </div>
        </fieldset>

        <!-- Caractéristiques -->
        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Caractéristiques</legend>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Difficulté</label>
                <select name="edit_difficulty" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
                    <?php
                    $diffLabels = ['','F — Facile','F — Facile','PD','AD','D — Difficile','D — Difficile','TD','TD','ED — Extrême','ED — Extrême'];
                    for ($d = 1; $d <= 10; $d++):
                        $sel = ((int)($via['difficulty'] ?? 5)) === $d ? 'selected' : '';
                    ?>
                    <option value="<?= $d ?>" <?= $sel ?>><?= $d ?> — <?= $diffLabels[$d] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Durée (h)</label>
                    <input type="number" name="edit_duration" step="0.5" min="0"
                           value="<?= escape($via['estimated_duration'] ?? $via['duration_hours'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dénivelé (m)</label>
                    <input type="number" name="edit_elevation_gain" min="0" value="<?= escape($via['elevation_gain'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Approche (min)</label>
                    <input type="number" name="edit_approach_time" min="0" value="<?= escape($via['approach_time'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Retour (min)</label>
                    <input type="number" name="edit_return_time" min="0" value="<?= escape($via['return_time'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Altitude max (m)</label>
                    <input type="number" name="edit_altitude_max" min="0" value="<?= escape($via['altitude_max'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Longueur (km)</label>
                    <input type="number" name="edit_length_km" step="0.1" min="0" value="<?= escape($via['length_km'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
        </fieldset>

        <!-- Statut & Accès -->
        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Statut &amp; Accès</legend>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Statut d'ouverture</label>
                    <select name="edit_opening_status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
                        <option value="ouvert" <?= ($via['opening_status'] ?? '') === 'ouvert' ? 'selected' : '' ?>>✅ Ouvert</option>
                        <option value="ferme" <?= ($via['opening_status'] ?? '') === 'ferme' ? 'selected' : '' ?>>⚠️ Fermé temporairement</option>
                        <option value="ferme_definitif" <?= ($via['opening_status'] ?? '') === 'ferme_definitif' ? 'selected' : '' ?>>🚫 Fermé définitivement</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tarif</label>
                    <select name="edit_pricing" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-slate-50">
                        <option value="gratuit" <?= ($via['pricing'] ?? '') === 'gratuit' ? 'selected' : '' ?>>Gratuit</option>
                        <option value="payant" <?= ($via['pricing'] ?? '') === 'payant' ? 'selected' : '' ?>>Payant</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Période d'ouverture</label>
                <input type="text" name="edit_opening_period" value="<?= escape($via['opening_period'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="ex : Juin à octobre">
            </div>
        </fieldset>

        <!-- Description -->
        <fieldset>
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</legend>
            <input type="hidden" name="edit_description" id="edit-desc-hidden" value="<?= escape($via['description'] ?? '') ?>">
            <div class="quill-wrap">
                <div id="quill-edit-desc"></div>
            </div>
        </fieldset>

        <!-- Infos pratiques -->
        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Infos pratiques</legend>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Office de tourisme</label>
                <input type="text" name="edit_tourism_office_name" value="<?= escape($via['tourism_office_name'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="Nom de l'office">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                    <input type="text" name="edit_tourism_office_phone" value="<?= escape($via['tourism_office_phone'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500"
                           placeholder="04 79 XX XX XX">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="edit_tourism_office_email" value="<?= escape($via['tourism_office_email'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Lien location matériel</label>
                <input type="text" name="edit_rental_equipment_url" value="<?= escape($via['rental_equipment_url'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500"
                       placeholder="https://...">
            </div>
        </fieldset>

        <!-- Actions -->
        <div class="flex gap-3 pt-2 pb-6">
            <button type="submit"
                    class="flex-1 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl text-sm transition-colors shadow-sm">
                💾 Enregistrer
            </button>
            <button type="button"
                    onclick="document.getElementById('admin-drawer').classList.add('translate-x-full')"
                    class="px-5 py-2.5 border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-xl text-sm font-medium transition-colors">
                Annuler
            </button>
        </div>
    </form>
</div>

<!-- Overlay pour fermer le drawer en cliquant à côté -->
<div id="admin-overlay" class="fixed inset-0 z-40 bg-black/40 hidden"
     onclick="document.getElementById('admin-drawer').classList.add('translate-x-full');this.classList.add('hidden')"></div>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<style>
#admin-drawer .ql-toolbar.ql-snow { border-color:#cbd5e1; border-radius:.5rem .5rem 0 0; background:#f8fafc; padding:6px 8px; }
#admin-drawer .ql-container.ql-snow { border-color:#cbd5e1; border-radius:0 0 .5rem .5rem; font-size:.875rem; font-family:inherit; min-height:130px; }
#admin-drawer .ql-editor { min-height:130px; color:#475569; line-height:1.6; }
#admin-drawer .ql-editor.ql-blank::before { color:#94a3b8; font-style:normal; }
#admin-drawer .quill-wrap:focus-within .ql-toolbar.ql-snow,
#admin-drawer .quill-wrap:focus-within .ql-container.ql-snow { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.15); }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    var drawer  = document.getElementById('admin-drawer');
    var overlay = document.getElementById('admin-overlay');

    // Ouvre l'overlay quand le drawer s'ouvre
    var observer = new MutationObserver(function() {
        if (!drawer.classList.contains('translate-x-full')) {
            overlay.classList.remove('hidden');
        } else {
            overlay.classList.add('hidden');
        }
    });
    observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });

    // Quill pour la description de la via
    var toolbar = [
        ['bold', 'italic', 'underline'],
        [{ header: [2, 3, false] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link', 'blockquote'],
        ['clean']
    ];
    var editQuill = new Quill('#quill-edit-desc', {
        theme: 'snow',
        modules: { toolbar: toolbar },
        placeholder: 'Description de la voie...'
    });
    var hiddenDesc = document.getElementById('edit-desc-hidden');
    if (hiddenDesc && hiddenDesc.value.trim()) {
        editQuill.clipboard.dangerouslyPasteHTML(hiddenDesc.value);
    }

    document.getElementById('admin-edit-form').addEventListener('submit', function() {
        hiddenDesc.value = editQuill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php if ($auth->isLoggedIn()): ?>
<!-- ══ ROAD TRIP MODAL ══════════════════════════════════════════════════ -->
<div id="add-trip-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">🗺️ <?= t('btn_add_to_trip') ?></h3>
            <button onclick="document.getElementById('add-trip-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <?php
        $tripModel  = new RoadTrip();
        $userTrips  = $tripModel->getByUser($auth->getUserId());
        ?>
        <?php if (empty($userTrips)): ?>
        <p class="text-sm text-slate-500 mb-4"><?= t('trips_empty_msg') ?></p>
        <a href="<?= BASE_URL ?>/road-trip"
           class="block w-full text-center bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
            + <?= t('trips_create') ?>
        </a>
        <?php else: ?>
        <form id="add-trip-form" class="space-y-3">
            <input type="hidden" name="via_id" value="<?= (int)$via['id'] ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trips_title') ?></label>
                <select name="trip_id" id="trip-select"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                    <?php foreach ($userTrips as $ut): ?>
                    <option value="<?= (int)$ut['id'] ?>" data-days="<?= (int)$ut['nb_days'] ?>">
                        <?= escape($ut['name']) ?> (<?= (int)$ut['nb_days'] ?> <?= t('trip_days') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_select_day') ?></label>
                <select name="day_number" id="trip-day-select"
                        class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                </select>
            </div>
            <button type="button" onclick="submitAddToTrip()"
                    class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                + <?= t('btn_add_to_trip') ?>
            </button>
        </form>
        <div class="mt-3 pt-3 border-t border-slate-100 text-center">
            <a href="<?= BASE_URL ?>/road-trip" class="text-xs text-brand-600 hover:underline">+ <?= t('trips_create') ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
// Populate day selector based on selected trip
(function() {
    const tripSel = document.getElementById('trip-select');
    const daySel  = document.getElementById('trip-day-select');
    if (!tripSel || !daySel) return;
    function populateDays() {
        const nbDays = +tripSel.options[tripSel.selectedIndex]?.dataset?.days || 1;
        daySel.innerHTML = '';
        for (let d = 1; d <= nbDays; d++) {
            const opt = document.createElement('option');
            opt.value = d; opt.textContent = '<?= t('trip_day') ?> ' + d;
            daySel.appendChild(opt);
        }
    }
    tripSel.addEventListener('change', populateDays);
    populateDays();
})();

async function submitAddToTrip() {
    const form   = document.getElementById('add-trip-form');
    const tripId = form.querySelector('[name=trip_id]').value;
    const viaId  = form.querySelector('[name=via_id]').value;
    const day    = form.querySelector('[name=day_number]').value;
    const btn    = form.querySelector('button[type=button]');
    btn.disabled = true; btn.textContent = '...';
    const r = await fetch('<?= BASE_URL ?>/api/trip/add-via', {
        method: 'POST',
        body: new URLSearchParams({
            csrf_token: '<?= escape($csrfToken) ?>',
            trip_id: tripId, via_id: viaId, day_number: day
        })
    });
    const d = await r.json();
    if (d.ok) {
        btn.textContent = '✓';
        setTimeout(() => document.getElementById('add-trip-modal').classList.add('hidden'), 800);
    } else {
        btn.disabled = false; btn.textContent = '+ <?= t('btn_add_to_trip') ?>';
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
