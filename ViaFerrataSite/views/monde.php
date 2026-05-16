<?php
/**
 * Monde — Carte SVG mondiale (jsVectorMap)
 */
require_once __DIR__ . '/../config/config.php';

// ── Noms de pays (indépendant de la table countries) ──────────
$country_names = [
    'FR'=>'France','CH'=>'Suisse','IT'=>'Italie','ES'=>'Espagne','AT'=>'Autriche',
    'DE'=>'Allemagne','PT'=>'Portugal','BE'=>'Belgique','NL'=>'Pays-Bas','LU'=>'Luxembourg',
    'GB'=>'Royaume-Uni','IE'=>'Irlande','NO'=>'Norvège','SE'=>'Suède','DK'=>'Danemark',
    'FI'=>'Finlande','PL'=>'Pologne','CZ'=>'Tchéquie','SK'=>'Slovaquie','HU'=>'Hongrie',
    'RO'=>'Roumanie','BG'=>'Bulgarie','GR'=>'Grèce','HR'=>'Croatie','SI'=>'Slovénie',
    'BA'=>'Bosnie','RS'=>'Serbie','ME'=>'Monténégro','MK'=>'Macédoine du Nord','AL'=>'Albanie',
    'US'=>'États-Unis','CA'=>'Canada','MX'=>'Mexique','BR'=>'Brésil','AR'=>'Argentine',
    'CL'=>'Chili','PE'=>'Pérou','CO'=>'Colombie','ZA'=>'Afrique du Sud',
    'AU'=>'Australie','NZ'=>'Nouvelle-Zélande','CN'=>'Chine','JP'=>'Japon',
    'IN'=>'Inde','TH'=>'Thaïlande','TR'=>'Turquie','MA'=>'Maroc',
];

$flags = [
    'FR'=>'🇫🇷','CH'=>'🇨🇭','IT'=>'🇮🇹','ES'=>'🇪🇸','AT'=>'🇦🇹','DE'=>'🇩🇪',
    'PT'=>'🇵🇹','BE'=>'🇧🇪','NL'=>'🇳🇱','LU'=>'🇱🇺','GB'=>'🇬🇧','IE'=>'🇮🇪',
    'NO'=>'🇳🇴','SE'=>'🇸🇪','DK'=>'🇩🇰','FI'=>'🇫🇮','PL'=>'🇵🇱','CZ'=>'🇨🇿',
    'SK'=>'🇸🇰','HU'=>'🇭🇺','RO'=>'🇷🇴','BG'=>'🇧🇬','GR'=>'🇬🇷','HR'=>'🇭🇷',
    'SI'=>'🇸🇮','BA'=>'🇧🇦','RS'=>'🇷🇸','ME'=>'🇲🇪','MK'=>'🇲🇰','AL'=>'🇦🇱',
    'US'=>'🇺🇸','CA'=>'🇨🇦','MX'=>'🇲🇽','BR'=>'🇧🇷','AR'=>'🇦🇷','CL'=>'🇨🇱',
    'PE'=>'🇵🇪','CO'=>'🇨🇴','ZA'=>'🇿🇦','AU'=>'🇦🇺','NZ'=>'🇳🇿',
    'CN'=>'🇨🇳','JP'=>'🇯🇵','IN'=>'🇮🇳','TH'=>'🇹🇭','TR'=>'🇹🇷','MA'=>'🇲🇦',
];

// ── Requête robuste (pas de JOIN countries) ───────────────────
$country_stats = [];
try {
    $pdo = Database::getInstance()->getConnection();

    // Est-ce que code_pays existe ?
    $col = $pdo->query("SHOW COLUMNS FROM vias LIKE 'code_pays'");
    $hasCol = $col && $col->rowCount() > 0;

    if ($hasCol) {
        // Requête simple sans JOIN — noms depuis PHP
        $rows = $pdo->query("
            SELECT code_pays, COUNT(*) AS via_count
            FROM vias
            WHERE is_active = 1 AND is_approved = 1
              AND code_pays IS NOT NULL AND code_pays != ''
            GROUP BY code_pays
            ORDER BY via_count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $up = strtoupper(trim($r['code_pays']));
            if (!preg_match('/^[A-Z]{2}$/', $up)) continue;
            $country_stats[] = [
                'code_pays'    => $up,
                'country_name' => $country_names[$up] ?? $up,
                'via_count'    => (int)$r['via_count'],
            ];
        }
    }

    // Si rien trouvé (colonne vide ou absente), compter tout comme France
    if (empty($country_stats)) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM vias WHERE is_active=1")->fetchColumn();
        if ($cnt > 0) {
            $country_stats = [['code_pays'=>'FR','country_name'=>'France','via_count'=>$cnt]];
        }
    }

} catch (Exception $e) {
    $country_stats = [['code_pays'=>'FR','country_name'=>'France','via_count'=>0]];
}

$total_vias = array_sum(array_column($country_stats, 'via_count'));
$nb_pays    = count($country_stats);

// ── Données JS (codes minuscules pour jsVectorMap) ────────────
$region_info = [];   // { 'fr': { name, count, flag, url } }
$selected    = [];   // ['fr', 'ch', ...] pour selectedRegions

foreach ($country_stats as $cs) {
    if ((int)$cs['via_count'] === 0) continue;
    $up  = strtoupper($cs['code_pays']);
    $low = strtolower($cs['code_pays']);
    $selected[]        = $low;
    $region_info[$low] = [
        'name'  => $cs['country_name'],
        'count' => (int)$cs['via_count'],
        'flag'  => $flags[$up] ?? '🏔',
        'url'   => BASE_URL . '/via?pays=' . $low,
    ];
}

$pageTitle = 'Via Ferrata dans le Monde';
$pageDesc  = 'Explorez les via ferrata du monde entier. Cliquez sur un pays pour découvrir ses itinéraires.';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css">

<style>
#monde-wrapper { display:flex; height:calc(100vh - 64px); background:#0f172a; overflow:hidden; }

/* ── Sidebar ── */
#monde-sidebar {
    width:250px; flex-shrink:0;
    display:flex; flex-direction:column;
    background:rgba(8,14,24,.98);
    border-right:1px solid rgba(16,185,129,.13);
    overflow:hidden;
}
#sidebar-top { padding:18px 15px 12px; border-bottom:1px solid rgba(255,255,255,.05); flex-shrink:0; }
#sidebar-top h1 { font-size:1.05rem; font-weight:700; color:#fff; margin-bottom:10px; }
.stat-chips { display:flex; gap:6px; }
.stat-chip { flex:1; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.17); border-radius:9px; padding:7px 4px; text-align:center; }
.stat-chip .n { display:block; font-size:1.2rem; font-weight:700; color:#10b981; line-height:1; }
.stat-chip .l { display:block; font-size:.59rem; color:rgba(255,255,255,.36); text-transform:uppercase; letter-spacing:.07em; margin-top:2px; }

#country-scroll { flex:1; overflow-y:auto; padding:7px 7px 20px; scrollbar-width:thin; scrollbar-color:rgba(16,185,129,.18) transparent; }
#country-scroll::-webkit-scrollbar { width:3px; }
#country-scroll::-webkit-scrollbar-thumb { background:rgba(16,185,129,.18); border-radius:2px; }
.sec-lbl { font-size:.59rem; font-weight:600; color:rgba(255,255,255,.23); text-transform:uppercase; letter-spacing:.1em; padding:7px 8px 3px; }
.c-row { display:flex; align-items:center; gap:7px; padding:6px 9px; border-radius:9px; text-decoration:none; transition:background .13s; }
.c-row:hover, .c-row.hl { background:rgba(16,185,129,.09); }
.c-flag { font-size:.95rem; flex-shrink:0; line-height:1; }
.c-name { flex:1; font-size:.77rem; font-weight:500; color:rgba(255,255,255,.78); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.c-badge { font-size:.63rem; font-weight:700; color:#10b981; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.17); border-radius:20px; padding:1px 6px; flex-shrink:0; }

/* ── Map ── */
#monde-map-zone { flex:1; position:relative; overflow:hidden; }
#world-map { width:100%; height:100%; }

/* ── Tooltip ── */
.jvectormap-tip {
    background:rgba(6,12,22,.97) !important;
    border:1px solid rgba(16,185,129,.32) !important;
    border-radius:11px !important;
    padding:10px 13px !important;
    font-family:'Outfit',sans-serif !important;
    color:#fff !important; font-size:13px !important;
    box-shadow:0 8px 28px rgba(0,0,0,.55) !important;
    pointer-events:none !important;
    min-width:130px !important;
    line-height:1.4 !important;
}

/* ── Zoom ── */
.jvectormap-zoomin,.jvectormap-zoomout {
    background:rgba(12,18,30,.92) !important;
    border:1px solid rgba(16,185,129,.22) !important;
    color:rgba(255,255,255,.6) !important;
    width:26px !important; height:26px !important;
    line-height:24px !important; font-size:16px !important;
    border-radius:7px !important;
}
.jvectormap-zoomin:hover,.jvectormap-zoomout:hover { background:rgba(16,185,129,.17) !important; color:#10b981 !important; }
.jvectormap-zoomin { margin-bottom:3px !important; }

/* ── Hint ── */
#map-hint {
    position:absolute; bottom:14px; left:50%; transform:translateX(-50%);
    background:rgba(12,18,30,.78); backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.06); border-radius:20px;
    padding:5px 14px; font-size:.69rem; color:rgba(255,255,255,.36);
    white-space:nowrap; pointer-events:none; z-index:10;
}

/* ── Mobile ── */
@media (max-width:860px) {
    #monde-wrapper { flex-direction:column; }
    #monde-sidebar { width:100%; max-height:150px; flex-direction:row; border-right:none; border-bottom:1px solid rgba(16,185,129,.13); }
    #sidebar-top { padding:10px 12px; border-bottom:none; border-right:1px solid rgba(255,255,255,.05); }
    #sidebar-top h1 { font-size:.88rem; margin-bottom:7px; }
    #country-scroll { display:flex; flex-wrap:nowrap; gap:4px; padding:6px; overflow-x:auto; overflow-y:hidden; align-items:center; }
    .sec-lbl { display:none; }
    .c-row { flex-direction:column; gap:1px; padding:5px 7px; min-width:58px; text-align:center; }
    .c-name { font-size:.58rem; }
    #monde-map-zone { flex:1; min-height:0; }
    #map-hint { display:none; }
}
</style>

<div id="monde-wrapper">

    <aside id="monde-sidebar">
        <div id="sidebar-top">
            <h1>
                <svg style="display:inline;width:14px;height:14px;vertical-align:-2px;margin-right:4px" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Via Ferrata <span style="color:#10b981">Monde</span>
            </h1>
            <div class="stat-chips">
                <div class="stat-chip"><span class="n"><?= $total_vias ?: '—' ?></span><span class="l">Via ferrata</span></div>
                <div class="stat-chip"><span class="n"><?= $nb_pays ?: '—' ?></span><span class="l">Pays</span></div>
            </div>
        </div>
        <nav id="country-scroll">
            <?php if (empty($country_stats)): ?>
                <p style="color:rgba(255,255,255,.28);font-size:.77rem;padding:10px">Aucune donnée</p>
            <?php else: ?>
                <div class="sec-lbl">Destinations</div>
                <?php foreach ($country_stats as $cs):
                    if ((int)$cs['via_count'] === 0) continue;
                    $up   = strtoupper($cs['code_pays']);
                    $low  = strtolower($cs['code_pays']);
                    $flag = $flags[$up] ?? '🏔';
                    $url  = BASE_URL . '/via?pays=' . $low;
                ?>
                <a href="<?= $url ?>" class="c-row" data-code="<?= $low ?>">
                    <span class="c-flag"><?= $flag ?></span>
                    <span class="c-name"><?= escape($cs['country_name']) ?></span>
                    <span class="c-badge"><?= (int)$cs['via_count'] ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
    </aside>

    <div id="monde-map-zone">
        <div id="world-map"></div>
        <div id="map-hint">Survolez un pays · Cliquez pour explorer ses via ferrata</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js"></script>
<script>
var regionInfo    = <?= json_encode($region_info, JSON_UNESCAPED_UNICODE) ?>;
var selectedCodes = <?= json_encode($selected) ?>;
var activeCode    = null; // toujours en minuscules

function tipHTML(info) {
    return '<div style="display:flex;align-items:center;gap:8px;margin-bottom:7px">'
        + '<span style="font-size:1.3rem">' + info.flag + '</span>'
        + '<strong style="font-size:13px;color:#fff">' + info.name + '</strong>'
        + '</div>'
        + '<div style="font-size:1.3rem;font-weight:800;color:#10b981">' + info.count + '</div>'
        + '<div style="font-size:.67rem;color:rgba(255,255,255,.45);margin-top:2px">via ferrata</div>'
        + '<div style="font-size:.63rem;color:rgba(16,185,129,.6);margin-top:7px;border-top:1px solid rgba(16,185,129,.15);padding-top:6px">Cliquer pour explorer →</div>';
}

new jsVectorMap({
    selector: '#world-map',
    map: 'world',
    backgroundColor: '#0f172a',
    zoomButtons: true,
    zoomOnScroll: true,
    draggable: true,
    regionsSelectable: false,
    selectedRegions: selectedCodes,
    regionStyle: {
        initial:       { fill: '#1e293b', stroke: '#0b1525', strokeWidth: 0.3, fillOpacity: 1 },
        hover:         { fill: '#2d3f55', fillOpacity: 1 },
        selected:      { fill: '#065f46' },
        selectedHover: { fill: '#10b981', cursor: 'pointer' },
    },

    // jsVectorMap 1.5.3 envoie le code en MAJUSCULES — on normalise en minuscules
    onRegionTooltipShow: function(event, tooltip, code) {
        var lcode = code.toLowerCase();
        var info  = regionInfo[lcode];
        activeCode = info ? lcode : null;

        // Sidebar highlight
        document.querySelectorAll('.c-row.hl').forEach(function(r){ r.classList.remove('hl'); });
        if (info) {
            var row = document.querySelector('.c-row[data-code="' + lcode + '"]');
            if (row) { row.classList.add('hl'); row.scrollIntoView({ block: 'nearest' }); }
        }

        // Contenu tooltip — setTimeout(0) pour écraser APRÈS que jsVectorMap
        // ait écrit son texte par défaut (ordre : event → texte défaut → show)
        if (!info) {
            setTimeout(function() {
                var el = document.querySelector('.jvectormap-tip');
                if (el) el.style.display = 'none';
            }, 0);
            return;
        }
        var html = tipHTML(info);
        // Tentative immédiate
        if (tooltip.el) tooltip.el.innerHTML = html;
        // Correction après le texte défaut de jsVectorMap
        setTimeout(function() {
            var el = document.querySelector('.jvectormap-tip');
            if (el) { el.innerHTML = html; el.style.display = ''; }
        }, 0);
    },

    onRegionClick: function(event, code) {
        // code reçu en MAJUSCULES → toLowerCase pour matcher regionInfo
        var lcode = code.toLowerCase();
        var info  = regionInfo[lcode];
        if (info && info.url) window.location.href = info.url;
    },
});

// Réinitialiser sidebar quand la souris quitte la carte
document.getElementById('monde-map-zone').addEventListener('mouseleave', function() {
    activeCode = null;
    document.querySelectorAll('.c-row.hl').forEach(function(r){ r.classList.remove('hl'); });
});

// Fallback clic capture-phase (si onRegionClick bloqué par stopPropagation)
document.getElementById('monde-map-zone').addEventListener('click', function() {
    if (activeCode && regionInfo[activeCode] && regionInfo[activeCode].url) {
        window.location.href = regionInfo[activeCode].url;
    }
}, true);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
