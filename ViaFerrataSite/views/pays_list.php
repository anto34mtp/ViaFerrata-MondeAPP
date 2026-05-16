<?php
/**
 * Pays — Liste des via ferrata d'un pays
 * URL : /pays/{code}   ex: /pays/ch  /pays/it
 */
require_once __DIR__ . '/../config/config.php';

// ── Code pays ─────────────────────────────────────────────────
$code_pays = strtoupper(trim($segment1 ?? ''));
if (empty($code_pays) || !preg_match('/^[A-Z]{2}$/', $code_pays)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// France a sa propre page dédiée
if ($code_pays === 'FR') {
    redirect(BASE_URL . '/france');
}

// ── Drapeaux ──────────────────────────────────────────────────
$country_flags = [
    'CH'=>'🇨🇭','IT'=>'🇮🇹','ES'=>'🇪🇸','AT'=>'🇦🇹','DE'=>'🇩🇪',
    'PT'=>'🇵🇹','BE'=>'🇧🇪','NL'=>'🇳🇱','LU'=>'🇱🇺','GB'=>'🇬🇧','IE'=>'🇮🇪',
    'NO'=>'🇳🇴','SE'=>'🇸🇪','DK'=>'🇩🇰','FI'=>'🇫🇮','PL'=>'🇵🇱','CZ'=>'🇨🇿',
    'SK'=>'🇸🇰','HU'=>'🇭🇺','RO'=>'🇷🇴','BG'=>'🇧🇬','GR'=>'🇬🇷','HR'=>'🇭🇷',
    'SI'=>'🇸🇮','BA'=>'🇧🇦','RS'=>'🇷🇸','ME'=>'🇲🇪','MK'=>'🇲🇰','AL'=>'🇦🇱',
    'US'=>'🇺🇸','CA'=>'🇨🇦','MX'=>'🇲🇽','BR'=>'🇧🇷','AR'=>'🇦🇷','CL'=>'🇨🇱',
    'PE'=>'🇵🇪','CO'=>'🇨🇴','ZA'=>'🇿🇦','AU'=>'🇦🇺','NZ'=>'🇳🇿',
    'CN'=>'🇨🇳','JP'=>'🇯🇵','IN'=>'🇮🇳','TH'=>'🇹🇭','TR'=>'🇹🇷','MA'=>'🇲🇦',
];
$country_flag = $country_flags[$code_pays] ?? '🏔';

// ── Centroïdes pour centrer la carte ──────────────────────────
// [lat, lng, zoom]
$centroids = [
    'CH'=>[46.82, 8.23, 8],  'IT'=>[42.83, 12.83, 6], 'ES'=>[40.42, -3.70, 6],
    'AT'=>[47.52, 14.55, 7], 'DE'=>[51.17, 10.45, 6], 'PT'=>[39.40, -8.22, 7],
    'BE'=>[50.50,  4.47, 8], 'NL'=>[52.13,  5.29, 8], 'LU'=>[49.82,  6.13, 9],
    'GB'=>[54.38, -3.44, 6], 'IE'=>[53.41, -8.24, 7], 'NO'=>[65.47,  8.47, 5],
    'SE'=>[62.13, 15.64, 5], 'DK'=>[56.26,  9.50, 7], 'FI'=>[64.92, 25.75, 5],
    'PL'=>[51.92, 19.15, 6], 'CZ'=>[49.82, 15.47, 7], 'SK'=>[48.67, 19.70, 8],
    'HU'=>[47.16, 19.50, 7], 'RO'=>[45.94, 24.97, 7], 'BG'=>[42.73, 25.49, 7],
    'GR'=>[39.07, 21.82, 7], 'HR'=>[45.10, 15.20, 7], 'SI'=>[46.15, 14.99, 8],
    'BA'=>[44.15, 17.68, 8], 'RS'=>[44.02, 21.01, 7], 'ME'=>[42.71, 19.37, 9],
    'MK'=>[41.61, 21.75, 8], 'AL'=>[41.15, 20.17, 8],
    'US'=>[37.09,-95.71, 4], 'CA'=>[56.13,-106.35,4], 'MX'=>[23.64,-102.55,5],
    'BR'=>[-14.24,-51.93,4], 'AR'=>[-38.42,-63.62,5], 'CL'=>[-35.68,-71.54,5],
    'PE'=>[-9.19,-75.02, 5], 'CO'=>[4.57, -74.30, 6], 'ZA'=>[-30.56, 22.94,5],
    'AU'=>[-25.27,133.78,4], 'NZ'=>[-40.90,174.89, 6],
    'CN'=>[35.86,104.20, 4], 'JP'=>[36.21,138.25, 5], 'IN'=>[20.59, 78.96, 5],
    'TH'=>[13.50,100.50, 6], 'TR'=>[38.96, 35.24, 6], 'MA'=>[31.79, -7.09,6],
];
$centroid     = $centroids[$code_pays] ?? [30, 10, 3];
$map_lat      = $centroid[0];
$map_lng      = $centroid[1];
$map_zoom     = $centroid[2];

// ── Filtres ───────────────────────────────────────────────────
$filters = [
    'difficulty_max' => isset($_GET['difficulty_max']) && $_GET['difficulty_max'] !== '' ? (int)$_GET['difficulty_max'] : null,
    'pricing'        => isset($_GET['pricing'])        && $_GET['pricing']        !== '' ? $_GET['pricing']        : null,
    'sort'           => isset($_GET['sort'])           && $_GET['sort']           !== '' ? $_GET['sort']           : 'name_asc',
];

// ── Requête DB ────────────────────────────────────────────────
$vias         = [];
$country_name = $code_pays;
try {
    $pdo = Database::getInstance()->getConnection();

    // Nom du pays
    $cs = $pdo->prepare("SELECT name FROM countries WHERE code = :code LIMIT 1");
    $cs->execute([':code' => $code_pays]);
    $row = $cs->fetch(PDO::FETCH_ASSOC);
    if ($row) $country_name = $row['name'];

    // Vérifier si la colonne code_pays existe
    $colCheck = $pdo->query("SHOW COLUMNS FROM vias LIKE 'code_pays'");
    $hasCodePays = $colCheck && $colCheck->rowCount() > 0;

    if ($hasCodePays) {
        $sql = "SELECT v.*,
                       AVG((r.rating_general + r.rating_beauty + r.rating_difficulty)/3) AS avg_overall,
                       COUNT(r.id) AS total_ratings
                FROM vias v
                LEFT JOIN ratings r ON v.id = r.via_id
                WHERE v.is_active = 1 AND v.is_approved = 1
                  AND v.code_pays = :code_pays";

        $params = [':code_pays' => $code_pays];

        if ($filters['difficulty_max']) {
            $sql .= " AND v.difficulty_rating <= :diff_max";
            $params[':diff_max'] = $filters['difficulty_max'];
        }
        if ($filters['pricing']) {
            $sql .= " AND v.pricing = :pricing";
            $params[':pricing'] = $filters['pricing'];
        }

        $sql .= " GROUP BY v.id";
        switch ($filters['sort']) {
            case 'rating_desc':     $sql .= " ORDER BY avg_overall DESC, v.name ASC"; break;
            case 'difficulty_asc':  $sql .= " ORDER BY v.difficulty_rating ASC"; break;
            case 'difficulty_desc': $sql .= " ORDER BY v.difficulty_rating DESC"; break;
            case 'duration_asc':    $sql .= " ORDER BY v.duration_hours ASC"; break;
            default:                $sql .= " ORDER BY v.name ASC"; break;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $vias = [];
}

$vias_json  = json_encode($vias);
$has_filter = array_filter($filters, fn($v) => $v !== null && $v !== 'name_asc');

$pageTitle = "Via Ferrata — {$country_name}";
$pageDesc  = "Découvrez les via ferrata de {$country_name} avec carte interactive.";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Map & List — même layout que /france -->
<div class="flex flex-col lg:flex-row flex-grow w-full relative">

    <!-- Carte Leaflet (droite sur desktop) -->
    <div class="w-full lg:w-1/2 order-1 lg:order-2 map-container shadow-md border-b lg:border-l border-slate-200">
        <div id="map" class="w-full h-full"></div>
    </div>

    <!-- Liste + Filtres (gauche) -->
    <div class="w-full lg:w-1/2 order-2 lg:order-1 bg-slate-50 flex flex-col overflow-y-auto">

        <!-- En-tête pays -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-4 sm:px-6 py-4 relative overflow-hidden">
            <div class="absolute inset-0" style="background:radial-gradient(ellipse at 15% 50%,rgba(16,185,129,.12) 0%,transparent 65%)"></div>
            <div class="relative flex items-center gap-3">
                <span class="text-3xl leading-none"><?= $country_flag ?></span>
                <div>
                    <a href="<?= BASE_URL ?>/monde"
                       class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors flex items-center gap-1 mb-0.5">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Carte du monde
                    </a>
                    <h1 class="text-xl font-bold text-white">Via Ferrata — <?= escape($country_name) ?></h1>
                    <p class="text-emerald-400 text-xs font-semibold mt-0.5">
                        <?= count($vias) ?> itinéraire<?= count($vias) > 1 ? 's' : '' ?> trouvé<?= count($vias) > 1 ? 's' : '' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-20 px-4 py-3">
            <form method="GET" action="<?= BASE_URL ?>/pays/<?= strtolower($code_pays) ?>">
                <div class="flex flex-wrap gap-2 items-end">

                    <div class="flex flex-col gap-1 min-w-[130px]">
                        <label class="text-xs font-medium text-slate-500">Trier par</label>
                        <select name="sort" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="name_asc"       <?= $filters['sort']==='name_asc'       ?'selected':''?>>Alphabétique</option>
                            <option value="rating_desc"    <?= $filters['sort']==='rating_desc'    ?'selected':''?>>Mieux notées</option>
                            <option value="difficulty_asc" <?= $filters['sort']==='difficulty_asc' ?'selected':''?>>Facile → Difficile</option>
                            <option value="difficulty_desc"<?= $filters['sort']==='difficulty_desc'?'selected':''?>>Difficile → Facile</option>
                            <option value="duration_asc"   <?= $filters['sort']==='duration_asc'   ?'selected':''?>>Durée croissante</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 min-w-[110px]">
                        <label class="text-xs font-medium text-slate-500">Difficulté max</label>
                        <select name="difficulty_max" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">Toutes</option>
                            <option value="2"  <?= $filters['difficulty_max']==2  ?'selected':''?>>F — Facile</option>
                            <option value="4"  <?= $filters['difficulty_max']==4  ?'selected':''?>>PD</option>
                            <option value="5"  <?= $filters['difficulty_max']==5  ?'selected':''?>>AD</option>
                            <option value="7"  <?= $filters['difficulty_max']==7  ?'selected':''?>>D — Difficile</option>
                            <option value="8"  <?= $filters['difficulty_max']==8  ?'selected':''?>>TD</option>
                            <option value="10" <?= $filters['difficulty_max']==10 ?'selected':''?>>ED — Extrême</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 min-w-[100px]">
                        <label class="text-xs font-medium text-slate-500">Tarif</label>
                        <select name="pricing" class="text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">Tous</option>
                            <option value="gratuit" <?= $filters['pricing']==='gratuit'?'selected':''?>>Gratuit</option>
                            <option value="payant"  <?= $filters['pricing']==='payant' ?'selected':''?>>Payant</option>
                        </select>
                    </div>

                    <div class="flex gap-2 mt-auto">
                        <button type="submit" class="px-4 py-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                            Filtrer
                        </button>
                        <?php if ($has_filter): ?>
                        <a href="<?= BASE_URL ?>/pays/<?= strtolower($code_pays) ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm rounded-lg transition-colors">✕ Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cards -->
        <div class="px-4 sm:px-6 py-4">
            <?php if (empty($vias)): ?>
                <div class="bg-white rounded-xl p-10 text-center border border-slate-100 shadow-sm">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                    </div>
                    <p class="text-slate-600 font-semibold mb-1">Aucune via ferrata trouvée</p>
                    <p class="text-slate-400 text-sm mb-4">
                        <?php if ($has_filter): ?>
                            <a href="<?= BASE_URL ?>/pays/<?= strtolower($code_pays) ?>" class="text-brand-600 hover:underline">Réinitialiser les filtres</a>
                        <?php else: ?>
                            Aucune via ferrata référencée pour <?= escape($country_name) ?> pour l'instant.
                        <?php endif; ?>
                    </p>
                    <a href="<?= BASE_URL ?>/monde" class="inline-flex items-center gap-1.5 text-sm text-brand-600 hover:text-brand-700 font-medium">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Explorer d'autres pays
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($vias as $via):
                        $imageUrl  = !empty($via['image_url'])
                            ? escape($via['image_url'])
                            : BASE_URL . '/assets/images/default.png';
                        $diffLabel = getDifficultyLabel((int)($via['difficulty_rating'] ?? 5));
                        $avgRating = isset($via['avg_overall']) && $via['avg_overall'] !== null
                            ? round($via['avg_overall'], 1) : null;
                        $detailUrl = BASE_URL . '/pays/' . strtolower($code_pays) . '/' . escape($via['slug']);
                    ?>
                    <a href="<?= $detailUrl ?>"
                       class="group bg-white rounded-xl overflow-hidden shadow-sm border border-slate-200 hover:shadow-md transition-all hover:border-brand-200 flex flex-col">
                        <div class="h-44 w-full relative bg-slate-200 overflow-hidden">
                            <img src="<?= $imageUrl ?>" alt="<?= escape($via['name']) ?>" loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                 onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-0.5 rounded-md shadow-sm">
                                <span class="text-xs font-bold text-slate-800"><?= escape($diffLabel) ?></span>
                            </div>
                            <?php if ($avgRating): ?>
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
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= escape($via['location'] ?? '') ?>
                            </p>
                            <div class="mt-auto flex flex-wrap gap-1.5 text-xs">
                                <?php if (!empty($via['duration_hours'])): ?>
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded">⏱ <?= escape($via['duration_hours']) ?>h</span>
                                <?php endif; ?>
                                <?php if (!empty($via['elevation_gain'])): ?>
                                <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">+<?= escape($via['elevation_gain']) ?>m</span>
                                <?php endif; ?>
                                <?php if (!empty($via['pricing'])): ?>
                                <span class="<?= $via['pricing']==='gratuit' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' ?> px-2 py-0.5 rounded">
                                    <?= $via['pricing']==='gratuit' ? 'Gratuit' : 'Payant' ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Vue initiale centrée sur le pays
    var initLat  = <?= $map_lat ?>;
    var initLng  = <?= $map_lng ?>;
    var initZoom = <?= $map_zoom ?>;

    var map = L.map('map').setView([initLat, initLng], initZoom);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        maxZoom: 19
    }).addTo(map);

    var vias = <?= $vias_json ?>;

    if (vias && vias.length > 0) {
        var markers = L.featureGroup();
        var icon = L.divIcon({
            className: '',
            html: '<div style="background:#10b981;width:13px;height:13px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px rgba(16,185,129,.6)"></div>',
            iconSize: [13,13], iconAnchor: [6,6], popupAnchor: [0,-6]
        });

        vias.forEach(function (v) {
            if (!v.latitude || !v.longitude) return;
            var url = '<?= BASE_URL ?>/pays/<?= strtolower($code_pays) ?>/' + v.slug;
            var m = L.marker([parseFloat(v.latitude), parseFloat(v.longitude)], { icon: icon });
            m.bindPopup(
                '<div style="min-width:175px;font-family:Outfit,sans-serif">'
                + '<strong style="font-size:13px">' + v.name + '</strong>'
                + (v.location ? '<p style="font-size:11px;color:#6b7280;margin:3px 0">' + v.location + '</p>' : '')
                + '<a href="' + url + '" style="display:block;text-align:center;background:#10b981;color:#fff;border-radius:6px;padding:5px 8px;font-size:12px;font-weight:600;text-decoration:none;margin-top:4px">Voir la fiche →</a>'
                + '</div>'
            );
            markers.addLayer(m);
        });

        map.addLayer(markers);

        // Si des marqueurs existent, adapter la vue — sinon garder le centroïde
        if (markers.getLayers().length > 0) {
            map.fitBounds(markers.getBounds(), { padding: [40, 40] });
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
