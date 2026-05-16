<?php
require_once __DIR__ . '/../config/config.php';

// ==================
// RÉSOLUTION DU PAYS
// ==================
$pays_param = isset($_GET['pays']) ? trim(strtolower($_GET['pays'])) : '';

// Table de correspondance nom → code ISO
$name_to_code = [
    'france'        => 'FR', 'espagne' => 'ES', 'spain'       => 'ES',
    'italie'        => 'IT', 'italy'   => 'IT', 'allemagne'   => 'DE',
    'germany'       => 'DE', 'suisse'  => 'CH', 'switzerland' => 'CH',
    'autriche'      => 'AT', 'austria' => 'AT', 'portugal'    => 'PT',
    'belgique'      => 'BE', 'belgium' => 'BE', 'luxembourg'  => 'LU',
    'grece'         => 'GR', 'greece'  => 'GR', 'slovenie'    => 'SI',
    'slovenia'      => 'SI', 'croatie' => 'HR', 'croatia'     => 'HR',
    'czechia'       => 'CZ', 'tcheque' => 'CZ', 'pologne'    => 'PL',
    'poland'        => 'PL', 'roumanie'=> 'RO', 'romania'     => 'RO',
    'bulgarie'      => 'BG', 'bulgaria'=> 'BG', 'hongrie'     => 'HU',
    'hungary'       => 'HU', 'serbie'  => 'RS', 'serbia'      => 'RS',
    'montenegro'    => 'ME', 'andorre' => 'AD', 'andorra'     => 'AD',
    'usa'           => 'US', 'etats-unis' => 'US', 'canada'   => 'CA',
    'mexique'       => 'MX', 'mexico'  => 'MX', 'bresil'      => 'BR',
    'brazil'        => 'BR', 'chili'   => 'CL', 'chile'       => 'CL',
    'argentine'     => 'AR', 'argentina'=> 'AR', 'perou'      => 'PE',
    'peru'          => 'PE', 'afrique-du-sud' => 'ZA', 'maroc' => 'MA',
    'morocco'       => 'MA', 'egypte'  => 'EG', 'egypt'       => 'EG',
    'chine'         => 'CN', 'china'   => 'CN', 'japon'       => 'JP',
    'japan'         => 'JP', 'australie' => 'AU', 'australia' => 'AU',
    'nouvelle-zelande' => 'NZ', 'new-zealand' => 'NZ',
];

$country_names = [
    'FR'=>'France','ES'=>'Espagne','IT'=>'Italie','DE'=>'Allemagne',
    'CH'=>'Suisse','AT'=>'Autriche','PT'=>'Portugal','BE'=>'Belgique',
    'LU'=>'Luxembourg','GR'=>'Grèce','SI'=>'Slovénie','HR'=>'Croatie',
    'CZ'=>'Tchéquie','PL'=>'Pologne','RO'=>'Roumanie','BG'=>'Bulgarie',
    'HU'=>'Hongrie','RS'=>'Serbie','ME'=>'Monténégro','AD'=>'Andorre',
    'US'=>'États-Unis','CA'=>'Canada','MX'=>'Mexique','BR'=>'Brésil',
    'CL'=>'Chili','AR'=>'Argentine','PE'=>'Pérou','ZA'=>'Afrique du Sud',
    'MA'=>'Maroc','EG'=>'Égypte','CN'=>'Chine','JP'=>'Japon',
    'AU'=>'Australie','NZ'=>'Nouvelle-Zélande',
];

$country_flags = [
    'FR'=>'🇫🇷','ES'=>'🇪🇸','IT'=>'🇮🇹','DE'=>'🇩🇪','CH'=>'🇨🇭',
    'AT'=>'🇦🇹','PT'=>'🇵🇹','BE'=>'🇧🇪','LU'=>'🇱🇺','GR'=>'🇬🇷',
    'SI'=>'🇸🇮','HR'=>'🇭🇷','CZ'=>'🇨🇿','PL'=>'🇵🇱','RO'=>'🇷🇴',
    'BG'=>'🇧🇬','HU'=>'🇭🇺','RS'=>'🇷🇸','ME'=>'🇲🇪','AD'=>'🇦🇩',
    'US'=>'🇺🇸','CA'=>'🇨🇦','MX'=>'🇲🇽','BR'=>'🇧🇷','CL'=>'🇨🇱',
    'AR'=>'🇦🇷','PE'=>'🇵🇪','ZA'=>'🇿🇦','MA'=>'🇲🇦','EG'=>'🇪🇬',
    'CN'=>'🇨🇳','JP'=>'🇯🇵','AU'=>'🇦🇺','NZ'=>'🇳🇿',
];

// Centroids [lat, lng, zoom]
$centroids = [
    'FR'=>[46.60, 1.89, 6],  'ES'=>[40.42, -3.70, 6],
    'IT'=>[41.87, 12.57, 6], 'DE'=>[51.17, 10.45, 6],
    'CH'=>[46.82, 8.23, 8],  'AT'=>[47.52, 14.55, 7],
    'PT'=>[39.40, -8.22, 7], 'BE'=>[50.50, 4.47, 8],
    'LU'=>[49.82, 6.13, 9],  'GR'=>[39.07, 21.82, 7],
    'SI'=>[46.12, 14.80, 8], 'HR'=>[45.10, 15.20, 7],
    'CZ'=>[49.82, 15.47, 7], 'PL'=>[51.92, 19.15, 6],
    'RO'=>[45.94, 24.97, 7], 'BG'=>[42.73, 25.49, 7],
    'HU'=>[47.16, 19.51, 7], 'RS'=>[44.02, 21.01, 7],
    'ME'=>[42.71, 19.37, 8], 'AD'=>[42.55, 1.58, 11],
    'US'=>[37.09, -95.71, 4],'CA'=>[56.13, -106.35, 4],
    'MX'=>[23.63, -102.55, 5],'BR'=>[-14.24, -51.93, 4],
    'CL'=>[-35.68, -71.54, 5],'AR'=>[-38.42, -63.62, 4],
    'PE'=>[-9.19, -75.02, 5],'ZA'=>[-30.56, 22.94, 6],
    'MA'=>[31.79, -7.09, 6], 'EG'=>[26.82, 30.80, 6],
    'CN'=>[35.86, 104.20, 4],'JP'=>[36.20, 138.25, 5],
    'AU'=>[-25.27, 133.78, 4],'NZ'=>[-40.90, 174.89, 5],
];

// Déterminer le code pays
if (strlen($pays_param) === 2) {
    $code_pays = strtoupper($pays_param);
} elseif (isset($name_to_code[$pays_param])) {
    $code_pays = $name_to_code[$pays_param];
} else {
    $code_pays = 'FR';
}

$country_name = $country_names[$code_pays] ?? $code_pays;
$country_flag = $country_flags[$code_pays] ?? '';
$centroid     = $centroids[$code_pays] ?? [20, 0, 2];
$map_lat      = $centroid[0];
$map_lng      = $centroid[1];
$map_zoom     = $centroid[2];

// ==================
// FILTRES & PAGINATION
// ==================
$per_page  = 30;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;
$q         = trim($_GET['q'] ?? '');
$sort      = $_GET['sort'] ?? 'name_asc';
$diff_cat  = isset($_GET['difficulty']) && $_GET['difficulty'] !== '' ? strtoupper($_GET['difficulty']) : null;
$pricing   = isset($_GET['pricing']) && $_GET['pricing'] !== '' ? $_GET['pricing'] : null;

// Plages de difficulté (champ difficulty 1-10)
$diff_ranges = [
    'F'  => [1, 2],
    'PD' => [2, 3],
    'AD' => [4, 4],
    'D'  => [5, 6],
    'TD' => [7, 8],
    'ED' => [9, 10],
];

$pageTitle     = "ViaFerrata-$country_name";
$pageDesc      = "Découvrez toutes les via ferrata de $country_name sur notre carte interactive.";
$headerCountry = $country_name;

// ==================
// REQUÊTES
// ==================
$vias        = [];
$total_count = 0;
$total_pages = 1;
$all_vias    = []; // for map markers (all pages)

try {
    $pdo = Database::getInstance()->getConnection();

    // Vérifier si code_pays existe
    $col    = $pdo->query("SHOW COLUMNS FROM vias LIKE 'code_pays'");
    $hasCol = $col && $col->rowCount() > 0;

    // Exclure les vias enfants (partie 2, 3…) — seuls les parents et standalone sont listés
    $base_cond = "v.is_active = 1 AND v.is_approved = 1 AND v.parent_id IS NULL";
    $params    = [];

    if ($hasCol) {
        $base_cond .= " AND v.code_pays = :code_pays";
        $params['code_pays'] = $code_pays;
    }

    if ($q !== '') {
        $base_cond .= " AND v.name LIKE :q";
        $params['q'] = '%' . $q . '%';
    }
    if ($diff_cat !== null && isset($diff_ranges[$diff_cat])) {
        [$diff_min_val, $diff_max_val] = $diff_ranges[$diff_cat];
        $base_cond .= " AND v.difficulty BETWEEN :diff_min AND :diff_max";
        $params['diff_min'] = $diff_min_val;
        $params['diff_max'] = $diff_max_val;
    }
    if ($pricing !== null) {
        $base_cond .= " AND v.pricing = :pricing";
        $params['pricing'] = $pricing;
    }

    // Count total
    $count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM vias v WHERE $base_cond");
    $count_stmt->execute($params);
    $total_count = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, (int)ceil($total_count / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    // Order (tri par difficulty = champ 1-10)
    $order_sql = match($sort) {
        'rating_desc'     => 'avg_overall DESC, v.name ASC',
        'difficulty_asc'  => 'v.difficulty ASC',
        'difficulty_desc' => 'v.difficulty DESC',
        'duration_asc'    => 'v.duration_hours ASC',
        default           => 'v.name ASC',
    };

    // Paginated list — inclut le nombre de parties enfants
    $list_stmt = $pdo->prepare("
        SELECT v.*, d.code as department_code,
               AVG((r.rating_general + r.rating_beauty + r.rating_difficulty)/3) as avg_overall,
               COUNT(DISTINCT r.id) as total_ratings,
               (SELECT COUNT(*) FROM vias v2 WHERE v2.parent_id = v.id) AS children_count
        FROM vias v
        LEFT JOIN departments d ON v.department_id = d.code
        LEFT JOIN ratings r ON v.id = r.via_id
        WHERE $base_cond
        GROUP BY v.id
        ORDER BY $order_sql
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) { $list_stmt->bindValue($k, $v); }
    $list_stmt->bindValue('limit',  $per_page, PDO::PARAM_INT);
    $list_stmt->bindValue('offset', $offset,   PDO::PARAM_INT);
    $list_stmt->execute();
    $vias = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marqueurs map — regroupés par GPS identique pour éviter les superpositions
    $marker_params = [];
    $marker_cond   = "is_active = 1 AND is_approved = 1";
    if ($hasCol) {
        $marker_cond .= " AND code_pays = :code_pays";
        $marker_params['code_pays'] = $code_pays;
    }
    $marker_stmt = $pdo->prepare("
        SELECT
            latitude, longitude,
            COUNT(*)                                                                 AS via_count,
            GROUP_CONCAT(id    ORDER BY COALESCE(part_number, 0), id SEPARATOR ',') AS ids,
            GROUP_CONCAT(name  ORDER BY COALESCE(part_number, 0), id SEPARATOR '||') AS names,
            GROUP_CONCAT(slug  ORDER BY COALESCE(part_number, 0), id SEPARATOR '||') AS slugs
        FROM vias
        WHERE $marker_cond
          AND latitude  IS NOT NULL
          AND longitude IS NOT NULL
        GROUP BY latitude, longitude
    ");
    // part_number peut ne pas exister encore — fallback si colonne absente
    try {
        $marker_stmt->execute($marker_params);
        $all_vias = $marker_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        // Si part_number n'existe pas encore, requête sans tri par part_number
        $marker_stmt2 = $pdo->prepare("
            SELECT latitude, longitude, COUNT(*) AS via_count,
                   GROUP_CONCAT(id   ORDER BY id SEPARATOR ',')  AS ids,
                   GROUP_CONCAT(name ORDER BY id SEPARATOR '||') AS names,
                   GROUP_CONCAT(slug ORDER BY id SEPARATOR '||') AS slugs
            FROM vias WHERE $marker_cond AND latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY latitude, longitude
        ");
        $marker_stmt2->execute($marker_params);
        $all_vias = $marker_stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // Marqueurs orange si < 10 résultats filtrés (ou si filtre actif)
    $orange_vias = [];
    $has_active_filter = ($q !== '' || $diff_cat !== null || $pricing !== null);
    if ($has_active_filter && $total_count > 0 && $total_count < 10) {
        $orange_stmt = $pdo->prepare("
            SELECT v.latitude, v.longitude,
                   COUNT(*) AS via_count,
                   GROUP_CONCAT(v.name ORDER BY COALESCE(v.part_number,0), v.id SEPARATOR '||') AS names,
                   GROUP_CONCAT(v.slug ORDER BY COALESCE(v.part_number,0), v.id SEPARATOR '||') AS slugs
            FROM vias v
            WHERE $base_cond AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL
            GROUP BY v.latitude, v.longitude
        ");
        foreach ($params as $k => $val) { $orange_stmt->bindValue($k, $val); }
        $orange_stmt->execute();
        $orange_vias = $orange_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $vias        = [];
    $all_vias    = [];
    $orange_vias = [];
}

$all_vias_json    = json_encode($all_vias);
$orange_vias_json = json_encode($orange_vias);
$few_results      = $has_active_filter && $total_count > 0 && $total_count < 10;

// URL de base pour la pagination (sans page ni q)
function build_url(string $base, array $keep, array $extra = []): string {
    $params = [];
    foreach ($keep as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
    }
    foreach ($extra as $k => $v) {
        if ($v !== null && $v !== '') $params[$k] = $v;
    }
    return $base . '?' . http_build_query($params);
}

$base_url  = BASE_URL . '/via';
$keep_keys = ['pays', 'q', 'sort', 'difficulty', 'pricing'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Map & List Layout -->
<div class="flex flex-col lg:flex-row flex-grow w-full relative">

    <!-- Leaflet Map -->
    <div class="w-full lg:w-1/2 order-1 lg:order-2 map-container shadow-md border-b lg:border-l border-slate-200">
        <div id="map" class="w-full h-full"></div>
    </div>

    <!-- List + Filters -->
    <div class="w-full lg:w-1/2 order-2 lg:order-1 bg-slate-50 flex flex-col overflow-y-auto">

        <!-- Filter Bar -->
        <div class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-20 px-4 py-3">

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 mb-2 text-sm">
                <a href="<?= BASE_URL ?>/monde" class="text-slate-500 hover:text-brand-600 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <?= t('via_list_world_map') ?>
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-slate-700 font-medium"><?= $country_flag ?> <?= escape($country_name) ?></span>
            </div>

            <form method="GET" action="<?= $base_url ?>" id="filter-form">
                <input type="hidden" name="pays" value="<?= escape(strtolower($code_pays)) ?>">
                <div class="flex flex-wrap gap-2 items-end">

                    <!-- Recherche par nom -->
                    <div class="flex flex-col gap-1 flex-grow min-w-[160px]">
                        <label class="text-xs font-medium text-slate-500"><?= t('via_list_search') ?></label>
                        <div class="relative">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="q" value="<?= escape($q) ?>" placeholder="<?= t('via_list_search_ph') ?>"
                                class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg pl-8 pr-3 py-1.5 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition">
                        </div>
                    </div>

                    <!-- Tri -->
                    <div class="flex flex-col gap-1 min-w-[130px]">
                        <label class="text-xs font-medium text-slate-500"><?= t('via_list_sort_by') ?></label>
                        <select name="sort" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            <option value="name_asc"       <?= $sort==='name_asc'       ? 'selected':'' ?>><?= t('sort_alpha') ?></option>
                            <option value="rating_desc"    <?= $sort==='rating_desc'    ? 'selected':'' ?>><?= t('sort_rating') ?></option>
                            <option value="difficulty_asc" <?= $sort==='difficulty_asc' ? 'selected':'' ?>><?= t('sort_diff_asc') ?></option>
                            <option value="difficulty_desc"<?= $sort==='difficulty_desc'? 'selected':'' ?>><?= t('sort_diff_desc') ?></option>
                            <option value="duration_asc"   <?= $sort==='duration_asc'   ? 'selected':'' ?>><?= t('sort_dur_asc') ?></option>
                        </select>
                    </div>

                    <!-- Difficulté (sélection par catégorie) -->
                    <div class="flex flex-col gap-1 min-w-[120px]">
                        <label class="text-xs font-medium text-slate-500"><?= t('stat_difficulty') ?></label>
                        <select name="difficulty" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            <option value=""><?= t('filter_all') ?></option>
                            <option value="F"  <?= $diff_cat==='F'  ? 'selected':'' ?>>F — <?= t('diff_F') ?></option>
                            <option value="PD" <?= $diff_cat==='PD' ? 'selected':'' ?>>PD</option>
                            <option value="AD" <?= $diff_cat==='AD' ? 'selected':'' ?>>AD</option>
                            <option value="D"  <?= $diff_cat==='D'  ? 'selected':'' ?>>D — <?= t('diff_D') ?></option>
                            <option value="TD" <?= $diff_cat==='TD' ? 'selected':'' ?>>TD</option>
                            <option value="ED" <?= $diff_cat==='ED' ? 'selected':'' ?>>ED</option>
                        </select>
                    </div>

                    <!-- Tarif -->
                    <div class="flex flex-col gap-1 min-w-[100px]">
                        <label class="text-xs font-medium text-slate-500"><?= t('via_list_pricing') ?></label>
                        <select name="pricing" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                            <option value=""><?= t('filter_all_m') ?></option>
                            <option value="gratuit" <?= $pricing==='gratuit' ? 'selected':'' ?>><?= t('pricing_free') ?></option>
                            <option value="payant"  <?= $pricing==='payant'  ? 'selected':'' ?>><?= t('pricing_paid') ?></option>
                        </select>
                    </div>

                    <div class="flex gap-2 mt-auto">
                        <button type="submit" class="px-4 py-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            <?= t('btn_filter') ?>
                        </button>
                        <?php if ($q !== '' || $diff_cat !== null || $pricing !== null): ?>
                        <a href="<?= $base_url ?>?pays=<?= escape(strtolower($code_pays)) ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm rounded-lg transition-colors"><?= t('btn_reset_filters') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Header -->
        <div class="px-4 sm:px-6 pt-4 pb-2 flex justify-between items-center flex-wrap gap-2">
            <div>
                <h1 class="text-xl font-bold text-slate-900">
                    <?= $country_flag ?> Via Ferrata — <?= escape($country_name) ?>
                </h1>
                <p class="text-slate-500 text-sm">
                    <?= $total_count ?> <?= t('via_list_routes') ?>
                    <?php if ($q !== ''): ?> <?= t('via_list_for') ?> "<strong><?= escape($q) ?></strong>"<?php endif; ?>
                    <?php if ($total_pages > 1): ?>— <?= t('via_list_page') ?> <?= $page ?>/<?= $total_pages ?><?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="px-4 sm:px-6 pb-4">
            <?php if (empty($vias)): ?>
                <div class="bg-white rounded-xl p-8 text-center border border-slate-100 mt-2">
                    <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-slate-500 font-medium"><?= t('via_list_empty_msg') ?></p>
                    <a href="<?= $base_url ?>?pays=<?= escape(strtolower($code_pays)) ?>" class="mt-3 inline-block text-brand-600 hover:underline text-sm"><?= t('via_list_reset_link') ?></a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                    <?php foreach ($vias as $via): ?>
                        <?php
                        $imageUrl      = !empty($via['image_url']) ? escape($via['image_url']) : BASE_URL . '/assets/images/default.png';
                        $diffLabel     = getDifficultyLabel((int)($via['difficulty'] ?? $via['difficulty_rating'] ?? 5));
                        $avgRating     = isset($via['avg_overall']) && $via['avg_overall'] !== null ? round((float)$via['avg_overall'], 1) : null;
                        $via_url       = BASE_URL . '/via/' . escape($via['slug']) . '?pays=' . strtolower($code_pays);
                        $children      = (int)($via['children_count'] ?? 0);
                        $total_parts   = $children > 0 ? $children + 1 : 0;
                        $status        = $via['opening_status'] ?? 'ouvert';
                        $isClosed      = in_array($status, ['ferme', 'ferme_definitif']);
                        $isDefinitive  = $status === 'ferme_definitif';
                        $cardBorder    = $isClosed ? 'border-red-200 hover:border-red-300' : 'border-slate-200 hover:border-brand-200';
                        ?>
                        <a href="<?= $via_url ?>" class="group bg-white rounded-xl overflow-hidden shadow-sm border <?= $cardBorder ?> hover:shadow-md transition-all flex flex-col <?= $isClosed ? 'opacity-80' : '' ?>">
                            <div class="h-44 w-full relative bg-slate-200 overflow-hidden">
                                <img src="<?= $imageUrl ?>" alt="<?= escape($via['name']) ?>" loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                                <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                    <span class="text-xs font-bold text-slate-800"><?= escape($diffLabel) ?></span>
                                </div>
                                <?php if ($isClosed): ?>
                                <div class="absolute inset-0 bg-red-900/30 flex items-center justify-center pointer-events-none">
                                    <span class="<?= $isDefinitive ? 'bg-red-600' : 'bg-amber-500' ?> text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg uppercase tracking-wide">
                                        <?= $isDefinitive ? t('status_closed_perm_ovl') : t('status_closed_temp_ovl') ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($avgRating && !$isClosed): ?>
                                <div class="absolute top-2 left-2 bg-brand-500/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                    <span class="text-xs font-bold text-white">⭐ <?= $avgRating ?>/10</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 flex-grow flex flex-col">
                                <h3 class="font-bold text-slate-900 leading-tight mb-1 group-hover:text-brand-600 transition-colors line-clamp-2 text-sm">
                                    <?= escape($via['name']) ?>
                                </h3>
                                <p class="text-xs text-slate-500 mb-2 flex items-center gap-1 line-clamp-1">
                                    📍 <?= escape($via['department_code'] ?? '') ?><?= !empty($via['department_code']) && !empty($via['location']) ? ' — ' : '' ?><?= escape($via['location'] ?? '') ?>
                                </p>
                                <div class="mt-auto flex flex-wrap gap-1.5 text-xs">
                                    <?php if ($isClosed): ?>
                                        <span class="<?= $isDefinitive ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?> px-2 py-0.5 rounded font-semibold">
                                            🔒 <?= $isDefinitive ? t('status_closed_perm_short') : t('status_closed_temp_short') ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($via['duration_hours'])): ?>
                                        <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded">⏱ <?= escape($via['duration_hours']) ?>h</span>
                                    <?php endif; ?>
                                    <?php if (!empty($via['elevation_gain'])): ?>
                                        <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">📈 +<?= escape($via['elevation_gain']) ?>m</span>
                                    <?php endif; ?>
                                    <?php if (!empty($via['pricing'])): ?>
                                        <span class="<?= $via['pricing']==='gratuit' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' ?> px-2 py-0.5 rounded">
                                            <?= $via['pricing']==='gratuit' ? t('pricing_free') : t('pricing_paid') ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($total_parts > 0): ?>
                                        <span class="bg-purple-50 text-purple-700 px-2 py-0.5 rounded font-semibold">⛓ <?= $total_parts ?> <?= t('via_parts') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 sm:px-6 pb-6 flex items-center justify-center gap-2 flex-wrap">
            <?php if ($page > 1): ?>
                <a href="<?= build_url($base_url, $keep_keys, ['page' => $page - 1]) ?>"
                   class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <?= t('pagination_prev') ?>
                </a>
            <?php endif; ?>

            <?php
            $range_start = max(1, $page - 2);
            $range_end   = min($total_pages, $page + 2);
            if ($range_start > 1): ?>
                <a href="<?= build_url($base_url, $keep_keys, ['page' => 1]) ?>" class="px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">1</a>
                <?php if ($range_start > 2): ?><span class="px-2 text-slate-400 text-sm">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $range_start; $p <= $range_end; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="px-3 py-1.5 text-sm font-bold bg-brand-500 text-white rounded-lg shadow-sm"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= build_url($base_url, $keep_keys, ['page' => $p]) ?>" class="px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($range_end < $total_pages): ?>
                <?php if ($range_end < $total_pages - 1): ?><span class="px-2 text-slate-400 text-sm">…</span><?php endif; ?>
                <a href="<?= build_url($base_url, $keep_keys, ['page' => $total_pages]) ?>" class="px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= build_url($base_url, $keep_keys, ['page' => $page + 1]) ?>"
                   class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors shadow-sm">
                    <?= t('pagination_next') ?>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /list panel -->

</div><!-- /flex layout -->

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
var VIA_SEE_LABEL   = '<?= addslashes(t('via_see')) ?>';
var VIA_COUNT_LABEL = '<?= addslashes(t('via_count_here')) ?>';
var FILTER_LBL      = '<?= addslashes(t('filtered_result_label')) ?>';

document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([<?= $map_lat ?>, <?= $map_lng ?>], <?= $map_zoom ?>);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        maxZoom: 19
    }).addTo(map);

    var BASE       = '<?= BASE_URL ?>';
    var PAYS       = '<?= strtolower($code_pays) ?>';
    var fewResults = <?= $few_results ? 'true' : 'false' ?>;

    // Icône simple verte
    var singleIcon = L.divIcon({
        className: '',
        html: '<div style="background:#10b981;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 4px rgba(0,0,0,.4)"></div>',
        iconSize: [14,14], iconAnchor: [7,7], popupAnchor: [0,-7]
    });
    // Icône groupée verte
    function groupIcon(n) {
        return L.divIcon({
            className: '',
            html: '<div style="background:#059669;width:26px;height:26px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;font-family:Outfit,sans-serif">' + n + '</div>',
            iconSize: [26,26], iconAnchor: [13,13], popupAnchor: [0,-13]
        });
    }
    // Icône orange (résultats filtrés < 5)
    var orangeIcon = L.divIcon({
        className: '',
        html: '<div style="background:#f97316;width:20px;height:20px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(249,115,22,.6)"></div>',
        iconSize: [20,20], iconAnchor: [10,10], popupAnchor: [0,-10]
    });

    function buildPopup(names, slugs, n) {
        var h = '<div style="min-width:190px;font-family:Outfit,sans-serif">';
        if (n === 1) {
            h += '<strong style="font-size:13px;display:block;margin-bottom:6px">' + names[0] + '</strong>'
               + '<a href="' + BASE + '/via/' + slugs[0] + '?pays=' + PAYS + '" style="display:block;text-align:center;background:#10b981;color:#fff;border-radius:6px;padding:5px 8px;font-size:12px;text-decoration:none;font-weight:600">' + VIA_SEE_LABEL + '</a>';
        } else {
            h += '<p style="font-size:11px;font-weight:700;color:#059669;margin:0 0 7px;text-transform:uppercase;letter-spacing:.05em">' + n + ' ' + VIA_COUNT_LABEL + '</p>';
            names.forEach(function(name, i) {
                h += '<a href="' + BASE + '/via/' + slugs[i] + '?pays=' + PAYS + '" style="display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:#1e293b;font-size:12px">'
                   + '<span style="flex-shrink:0;width:18px;height:18px;background:#10b981;border-radius:50%;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center">' + (i+1) + '</span>'
                   + '<span style="font-weight:500">' + name + '</span></a>';
            });
        }
        return h + '</div>';
    }

    // Marqueurs normaux (tous les vias du pays, verts)
    var clusters = <?= $all_vias_json ?>;
    var markers  = L.featureGroup();

    clusters.forEach(function(c) {
        if (!c.latitude || !c.longitude) return;
        var n     = parseInt(c.via_count, 10);
        var names = c.names.split('||');
        var slugs = c.slugs.split('||');
        var icon  = n > 1 ? groupIcon(n) : singleIcon;
        var m = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], {icon: icon});
        m.bindPopup(buildPopup(names, slugs, n), {maxWidth: 260});
        markers.addLayer(m);
    });
    map.addLayer(markers);

    // Marqueurs orange si filtre actif et < 10 résultats
    <?php if ($few_results): ?>
    var orangeVias  = <?= $orange_vias_json ?>;
    var orangeLayer = L.featureGroup();
    orangeVias.forEach(function(c) {
        if (!c.latitude || !c.longitude) return;
        var n     = parseInt(c.via_count, 10);
        var names = c.names.split('||');
        var slugs = c.slugs.split('||');
        var h = '<div style="min-width:190px;font-family:Outfit,sans-serif">';
        h += '<p style="font-size:10px;font-weight:700;color:#f97316;margin:0 0 5px;text-transform:uppercase">&#128997; ' + FILTER_LBL + '</p>';
        if (n === 1) {
            h += '<strong style="font-size:13px;display:block;margin-bottom:6px">' + names[0] + '</strong>'
               + '<a href="' + BASE + '/via/' + slugs[0] + '?pays=' + PAYS + '" style="display:block;text-align:center;background:#f97316;color:#fff;border-radius:6px;padding:5px 8px;font-size:12px;text-decoration:none;font-weight:600">' + VIA_SEE_LABEL + '</a>';
        } else {
            names.forEach(function(name, i) {
                h += '<a href="' + BASE + '/via/' + slugs[i] + '?pays=' + PAYS + '" style="display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #fde8d0;text-decoration:none;color:#1e293b;font-size:12px">'
                   + '<span style="flex-shrink:0;width:18px;height:18px;background:#f97316;border-radius:50%;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center">' + (i+1) + '</span>'
                   + '<span style="font-weight:500">' + name + '</span></a>';
            });
        }
        h += '</div>';
        var m = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], {icon: orangeIcon, zIndexOffset: 1000});
        m.bindPopup(h, {maxWidth: 260});
        orangeLayer.addLayer(m);
    });
    map.addLayer(orangeLayer);
    if (orangeLayer.getLayers().length > 0) {
        map.fitBounds(orangeLayer.getBounds(), {padding: [80, 80]});
    } else
    <?php endif; ?>
    if (markers.getLayers().length > 0) {
        map.fitBounds(markers.getBounds(), {padding: [50, 50]});
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
