<?php
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$viaModel = new ViaFerrata();

$pageTitle = 'Via Ferrata en France';
$headerCountry = 'France';
$pageDesc = 'Découvrez toutes les via ferrata de France sur notre carte interactive. Filtrez par difficulté, département et note.';

// ==================
// FILTRES (1.2)
// ==================
$filters = [
    'difficulty'     => isset($_GET['difficulty'])      && $_GET['difficulty']      !== '' ? strtoupper($_GET['difficulty']) : null,
    'department_id'  => isset($_GET['department_id'])  && $_GET['department_id']  !== '' ? $_GET['department_id']  : null,
    'pricing'        => isset($_GET['pricing'])         && $_GET['pricing']         !== '' ? $_GET['pricing']         : null,
    'opening_status' => isset($_GET['opening_status'])  && $_GET['opening_status']  !== '' ? $_GET['opening_status']  : null,
    'sort'           => isset($_GET['sort'])             && $_GET['sort']             !== '' ? $_GET['sort']             : 'name_asc',
];

// Plages de difficulté (champ difficulty 1-10)
$diff_ranges = [
    'F'  => [1, 2],
    'PD' => [2, 3],
    'AD' => [4, 4],
    'D'  => [5, 6],
    'TD' => [7, 8],
    'ED' => [9, 10],
];

// Récupération des données filtrées
$vias = [];
try {
    $pdo = Database::getInstance()->getConnection();

    // Vérifier si les colonnes parent_id / part_number existent (migration optionnelle)
    $colCheck     = $pdo->query("SHOW COLUMNS FROM vias LIKE 'parent_id'");
    $hasParentId  = $colCheck && $colCheck->rowCount() > 0;

    $parentCond   = $hasParentId ? "AND v.parent_id IS NULL" : "";
    $childrenSub  = $hasParentId
        ? "(SELECT COUNT(*) FROM vias v2 WHERE v2.parent_id = v.id)"
        : "0";

    $sql = "SELECT v.*,
                   d.code as department_code, d.name as department_name,
                   AVG((r.rating_general + r.rating_beauty + r.rating_difficulty)/3) as avg_overall,
                   COUNT(r.id) as total_ratings,
                   $childrenSub AS children_count
            FROM vias v
            LEFT JOIN departments d ON v.department_id = d.code
            LEFT JOIN ratings r ON v.id = r.via_id
            WHERE v.is_active = 1 AND v.is_approved = 1 $parentCond";

    $params = [];

    if ($filters['difficulty'] !== null && isset($diff_ranges[$filters['difficulty']])) {
        [$diff_min_val, $diff_max_val] = $diff_ranges[$filters['difficulty']];
        $sql .= " AND v.difficulty BETWEEN :diff_min AND :diff_max";
        $params['diff_min'] = $diff_min_val;
        $params['diff_max'] = $diff_max_val;
    }
    if ($filters['department_id']) {
        $sql .= " AND v.department_id = :dept_id";
        $params['dept_id'] = $filters['department_id'];
    }
    if ($filters['pricing']) {
        $sql .= " AND v.pricing = :pricing";
        $params['pricing'] = $filters['pricing'];
    }
    if ($filters['opening_status']) {
        $sql .= " AND v.opening_status = :status";
        $params['status'] = $filters['opening_status'];
    }

    $sql .= " GROUP BY v.id";

    switch ($filters['sort']) {
        case 'rating_desc':  $sql .= " ORDER BY avg_overall DESC, v.name ASC"; break;
        case 'difficulty_asc': $sql .= " ORDER BY v.difficulty ASC"; break;
        case 'difficulty_desc': $sql .= " ORDER BY v.difficulty DESC"; break;
        case 'duration_asc': $sql .= " ORDER BY v.duration_hours ASC"; break;
        default:             $sql .= " ORDER BY v.name ASC"; break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marqueurs orange si filtre actif et < 10 résultats
    $total_count = count($vias);
    $has_active_filter = ($filters['difficulty'] !== null || $filters['department_id'] !== null
                       || $filters['pricing'] !== null || $filters['opening_status'] !== null);
    $few_results = $has_active_filter && $total_count > 0 && $total_count < 10;
    $orange_vias = [];
    if ($few_results) {
        $orange_sql = "SELECT v.latitude, v.longitude,
                              COUNT(*) AS via_count,
                              GROUP_CONCAT(v.name ORDER BY v.id SEPARATOR '||') AS names,
                              GROUP_CONCAT(v.slug ORDER BY v.id SEPARATOR '||') AS slugs
                       FROM vias v
                       WHERE v.is_active = 1 AND v.is_approved = 1 $parentCond
                         AND v.latitude IS NOT NULL AND v.longitude IS NOT NULL";
        $orange_params = [];
        if ($filters['difficulty'] !== null && isset($diff_ranges[$filters['difficulty']])) {
            [$dmin, $dmax] = $diff_ranges[$filters['difficulty']];
            $orange_sql .= " AND v.difficulty BETWEEN :diff_min AND :diff_max";
            $orange_params['diff_min'] = $dmin;
            $orange_params['diff_max'] = $dmax;
        }
        if ($filters['department_id']) {
            $orange_sql .= " AND v.department_id = :dept_id";
            $orange_params['dept_id'] = $filters['department_id'];
        }
        if ($filters['pricing']) {
            $orange_sql .= " AND v.pricing = :pricing";
            $orange_params['pricing'] = $filters['pricing'];
        }
        if ($filters['opening_status']) {
            $orange_sql .= " AND v.opening_status = :status";
            $orange_params['status'] = $filters['opening_status'];
        }
        $orange_sql .= " GROUP BY v.latitude, v.longitude";
        $orange_stmt = $pdo->prepare($orange_sql);
        $orange_stmt->execute($orange_params);
        $orange_vias = $orange_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Départements pour le filtre
    $depts_stmt = $pdo->query("SELECT DISTINCT d.code as id, d.code, d.name FROM departments d INNER JOIN vias v ON v.department_id = d.code WHERE v.is_active=1 AND v.is_approved=1 ORDER BY d.name");
    $departments = $depts_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marqueurs map groupés par GPS (évite les superpositions)
    try {
        $map_stmt = $pdo->query("
            SELECT latitude, longitude, COUNT(*) AS via_count,
                   GROUP_CONCAT(id   ORDER BY COALESCE(part_number,0), id SEPARATOR ',')  AS ids,
                   GROUP_CONCAT(name ORDER BY COALESCE(part_number,0), id SEPARATOR '||') AS names,
                   GROUP_CONCAT(slug ORDER BY COALESCE(part_number,0), id SEPARATOR '||') AS slugs
            FROM vias
            WHERE is_active = 1 AND is_approved = 1
              AND latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY latitude, longitude
        ");
        $map_clusters = $map_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $map_stmt2 = $pdo->query("
            SELECT latitude, longitude, COUNT(*) AS via_count,
                   GROUP_CONCAT(id   ORDER BY id SEPARATOR ',')  AS ids,
                   GROUP_CONCAT(name ORDER BY id SEPARATOR '||') AS names,
                   GROUP_CONCAT(slug ORDER BY id SEPARATOR '||') AS slugs
            FROM vias
            WHERE is_active=1 AND is_approved=1 AND latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY latitude, longitude
        ");
        $map_clusters = $map_stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $vias        = [];
    $departments = [];
    $map_clusters = [];
}

$vias_json         = json_encode($vias);
$map_clusters_json = json_encode($map_clusters ?? []);
$orange_vias_json  = json_encode($orange_vias ?? []);
$has_filters = array_filter($filters, fn($v) => $v !== null && $v !== '' && $v !== 'name_asc');

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

        <!-- Filter Bar (1.2 - FIXED) -->
        <div class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-20 px-4 py-3">
            <form method="GET" action="<?= BASE_URL ?>/france" id="filter-form">
                <div class="flex flex-wrap gap-2 items-end">
                    <!-- Tri -->
                    <div class="flex flex-col gap-1 min-w-[130px]">
                        <label class="text-xs font-medium text-slate-500">Trier par</label>
                        <select name="sort" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="name_asc" <?= $filters['sort']==='name_asc' ? 'selected':'' ?>>Alphabétique</option>
                            <option value="rating_desc" <?= $filters['sort']==='rating_desc' ? 'selected':'' ?>>Mieux notées</option>
                            <option value="difficulty_asc" <?= $filters['sort']==='difficulty_asc' ? 'selected':'' ?>>Facile → Difficile</option>
                            <option value="difficulty_desc" <?= $filters['sort']==='difficulty_desc' ? 'selected':'' ?>>Difficile → Facile</option>
                            <option value="duration_asc" <?= $filters['sort']==='duration_asc' ? 'selected':'' ?>>Durée croissante</option>
                        </select>
                    </div>

                    <!-- Département -->
                    <div class="flex flex-col gap-1 min-w-[140px]">
                        <label class="text-xs font-medium text-slate-500">Département</label>
                        <select name="department_id" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">Tous</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $filters['department_id'] == $d['id'] ? 'selected':'' ?>>
                                <?= escape($d['code']) ?> - <?= escape($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Difficulté (sélection par catégorie) -->
                    <div class="flex flex-col gap-1 min-w-[120px]">
                        <label class="text-xs font-medium text-slate-500">Difficulté</label>
                        <select name="difficulty" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">Toutes</option>
                            <option value="F"  <?= $filters['difficulty']==='F'  ? 'selected':'' ?>>F — Facile (1-2)</option>
                            <option value="PD" <?= $filters['difficulty']==='PD' ? 'selected':'' ?>>PD (2-3)</option>
                            <option value="AD" <?= $filters['difficulty']==='AD' ? 'selected':'' ?>>AD (4)</option>
                            <option value="D"  <?= $filters['difficulty']==='D'  ? 'selected':'' ?>>D — Difficile (5-6)</option>
                            <option value="TD" <?= $filters['difficulty']==='TD' ? 'selected':'' ?>>TD (7-8)</option>
                            <option value="ED" <?= $filters['difficulty']==='ED' ? 'selected':'' ?>>ED — Extrême (9-10)</option>
                        </select>
                    </div>

                    <!-- Tarif -->
                    <div class="flex flex-col gap-1 min-w-[100px]">
                        <label class="text-xs font-medium text-slate-500">Tarif</label>
                        <select name="pricing" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">Tous</option>
                            <option value="gratuit" <?= $filters['pricing']==='gratuit' ? 'selected':'' ?>>💚 Gratuit</option>
                            <option value="payant" <?= $filters['pricing']==='payant' ? 'selected':'' ?>>💰 Payant</option>
                        </select>
                    </div>

                    <div class="flex gap-2 mt-auto">
                        <button type="submit" class="px-4 py-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            Filtrer
                        </button>
                        <?php if ($has_filters): ?>
                        <a href="<?= BASE_URL ?>/france" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm rounded-lg transition-colors">✕ Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Header -->
        <div class="px-4 sm:px-6 pt-4 pb-2 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Via Ferrata en France</h1>
                <p class="text-slate-500 text-sm"><?= count($vias) ?> itinéraire<?= count($vias) > 1 ? 's' : '' ?> trouvé<?= count($vias) > 1 ? 's' : '' ?></p>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="px-4 sm:px-6 pb-6">
            <?php if (empty($vias)): ?>
                <div class="bg-white rounded-xl p-8 text-center border border-slate-100 mt-2">
                    <p class="text-slate-500">Aucune via ferrata ne correspond à vos critères.</p>
                    <a href="<?= BASE_URL ?>/france" class="mt-3 inline-block text-brand-600 hover:underline text-sm">Réinitialiser les filtres</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                    <?php foreach ($vias as $via): ?>
                        <?php
                        // FIX 1.1: Image par défaut
                        $imageUrl = !empty($via['image_url']) && $via['image_url'] !== 'https://viaferrata.delgehier.com/assets/images/via/default.png'
                            ? escape($via['image_url'])
                            : BASE_URL . '/assets/images/default.png';
                        $diffLabel = getDifficultyLabel((int)($via['difficulty'] ?? $via['difficulty_rating'] ?? 5));
                        $total_parts = (int)($via['children_count'] ?? 0);
                        $avgRating = isset($via['avg_overall']) && $via['avg_overall'] !== null ? round($via['avg_overall'], 1) : null;
                        ?>
                        <a href="<?= BASE_URL ?>/france/<?= escape($via['slug']) ?>" class="group bg-white rounded-xl overflow-hidden shadow-sm border border-slate-200 hover:shadow-md transition-all hover:border-brand-200 flex flex-col">
                            <div class="h-44 w-full relative bg-slate-200 overflow-hidden">
                                <img src="<?= $imageUrl ?>" alt="<?= escape($via['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                                <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                    <span class="text-xs font-bold text-slate-800"><?= escape($diffLabel) ?></span>
                                </div>
                                <?php if ($avgRating): ?>
                                <div class="absolute top-2 left-2 bg-brand-500/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                    <span class="text-xs font-bold text-white">⭐ <?= $avgRating ?>/10</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($total_parts > 0): ?>
                                <div class="absolute bottom-2 left-2 bg-orange-500/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                    <span class="text-xs font-bold text-white">⛓ <?= $total_parts + 1 ?> parties</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 flex-grow flex flex-col">
                                <h3 class="font-bold text-slate-900 leading-tight mb-1 group-hover:text-brand-600 transition-colors line-clamp-2 text-sm"><?= escape($via['name']) ?></h3>
                                <p class="text-xs text-slate-500 mb-2 flex items-center gap-1 line-clamp-1">
                                    📍 <?= escape($via['department_code'] ?? '') ?> — <?= escape($via['location'] ?? '') ?>
                                </p>
                                <div class="mt-auto flex flex-wrap gap-1.5 text-xs">
                                    <?php if (!empty($via['duration_hours'])): ?><span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded">⏱ <?= escape($via['duration_hours']) ?>h</span><?php endif; ?>
                                    <?php if (!empty($via['elevation_gain'])): ?><span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">📈 +<?= escape($via['elevation_gain']) ?>m</span><?php endif; ?>
                                    <?php if (!empty($via['pricing'])): ?><span class="<?= $via['pricing']==='gratuit' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' ?> px-2 py-0.5 rounded"><?= $via['pricing']==='gratuit' ? '💚 Gratuit' : '💰 Payant' ?></span><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map').setView([46.603354, 1.888334], 6);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO', maxZoom: 19
    }).addTo(map);

    var baseUrl = '<?= BASE_URL ?>';

    function buildPopup(names, slugs, n, color, urlPrefix) {
        var html = '<div style="min-width:190px;font-family:Outfit,sans-serif">';
        if (n === 1) {
            html += '<strong style="font-size:13px;display:block;margin-bottom:6px">' + names[0] + '</strong>'
                  + '<a href="' + baseUrl + urlPrefix + slugs[0] + '" style="display:block;text-align:center;background:' + color + ';color:#fff;border-radius:6px;padding:5px 8px;font-size:12px;text-decoration:none;font-weight:600">Voir la fiche →</a>';
        } else {
            html += '<p style="font-size:11px;font-weight:700;color:' + color + ';margin:0 0 7px;text-transform:uppercase;letter-spacing:.05em">' + n + ' via ferrata à cet endroit</p>';
            names.forEach(function(name, i) {
                html += '<a href="' + baseUrl + urlPrefix + slugs[i] + '" style="display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:#1e293b;font-size:12px">'
                      + '<span style="flex-shrink:0;width:18px;height:18px;background:' + color + ';border-radius:50%;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center">' + (i+1) + '</span>'
                      + '<span style="font-weight:500">' + name + '</span></a>';
            });
        }
        return html + '</div>';
    }

    function singleIcon() {
        return L.divIcon({
            className:'',
            html: '<div style="background:#10b981;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 4px rgba(0,0,0,.4)"></div>',
            iconSize:[14,14], iconAnchor:[7,7], popupAnchor:[0,-7]
        });
    }
    function groupIcon(n) {
        return L.divIcon({
            className:'',
            html: '<div style="background:#059669;width:26px;height:26px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;font-family:Outfit,sans-serif">' + n + '</div>',
            iconSize:[26,26], iconAnchor:[13,13], popupAnchor:[0,-13]
        });
    }
    function orangeIcon() {
        return L.divIcon({
            className:'',
            html: '<div style="background:#f97316;width:20px;height:20px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px rgba(249,115,22,.6)"></div>',
            iconSize:[20,20], iconAnchor:[10,10], popupAnchor:[0,-10]
        });
    }

    var clusters     = <?= $map_clusters_json ?>;
    var orangeVias   = <?= $orange_vias_json ?>;
    var fewResults   = <?= $few_results ? 'true' : 'false' ?>;

    var allMarkers   = L.featureGroup();
    var orangeMarkers = L.featureGroup();

    clusters.forEach(function(c) {
        if (!c.latitude || !c.longitude) return;
        var n     = parseInt(c.via_count, 10);
        var names = c.names.split('||');
        var slugs = c.slugs.split('||');
        var icon  = n > 1 ? groupIcon(n) : singleIcon();
        var m = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], {icon: icon});
        m.bindPopup(buildPopup(names, slugs, n, '#10b981', '/france/'), {maxWidth: 260});
        allMarkers.addLayer(m);
    });
    map.addLayer(allMarkers);

    if (fewResults && orangeVias.length > 0) {
        orangeVias.forEach(function(c) {
            if (!c.latitude || !c.longitude) return;
            var n     = parseInt(c.via_count, 10);
            var names = c.names.split('||');
            var slugs = c.slugs.split('||');
            var m = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], {icon: orangeIcon(), zIndexOffset: 1000});
            m.bindPopup(buildPopup(names, slugs, n, '#f97316', '/france/'), {maxWidth: 260});
            orangeMarkers.addLayer(m);
        });
        map.addLayer(orangeMarkers);
        map.fitBounds(orangeMarkers.getBounds(), {padding:[80,80]});
    } else if (allMarkers.getLayers().length > 0) {
        map.fitBounds(allMarkers.getBounds(), {padding:[50,50]});
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
