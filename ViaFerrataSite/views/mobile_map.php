<?php
/**
 * Vue carte mobile – chargée dans un WebView Android/iOS.
 * Paramètres GET acceptés :
 *   lat    float  latitude initiale   (défaut: 46.0)
 *   lng    float  longitude initiale  (défaut: 8.0)
 *   zoom   int    niveau de zoom      (défaut: 5)
 *   via    string slug d'une via à centrer/surligner
 *   token  string JWT Bearer (transmis aux appels API si présent)
 *   country string filtre pays (code ISO ou nom)
 *   dark   1|0    thème sombre
 */
$lat     = (float) ($_GET['lat']     ?? 46.0);
$lng     = (float) ($_GET['lng']     ?? 8.0);
$zoom    = max(2, min(18, (int)($_GET['zoom']    ?? 5)));
$via_slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['via'] ?? '');
$token   = preg_replace('/[^A-Za-z0-9\-_\.]+/', '', $_GET['token'] ?? '');
$country = htmlspecialchars($_GET['country'] ?? '', ENT_QUOTES);
$dark    = ($_GET['dark'] ?? '0') === '1';

$api_base = rtrim(BASE_URL, '/') . '/mobile-api';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Carte – ViaFerrata Monde</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { height: 100%; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; }
  #map { width: 100%; height: 100vh; background: <?= $dark ? '#1a1a2e' : '#e8f0f7' ?>; }

  /* Loader */
  #loader {
    position: fixed; inset: 0; z-index: 9999;
    background: <?= $dark ? '#0f172a' : '#f8fafc' ?>;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 14px;
  }
  #loader .spinner {
    width: 44px; height: 44px; border-radius: 50%;
    border: 4px solid <?= $dark ? '#334155' : '#e2e8f0' ?>;
    border-top-color: #10b981;
    animation: spin .8s linear infinite;
  }
  #loader p { font-size: 14px; color: <?= $dark ? '#94a3b8' : '#64748b' ?>; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Compteur */
  #counter {
    position: fixed; top: 10px; left: 50%; transform: translateX(-50%);
    background: <?= $dark ? 'rgba(15,23,42,.85)' : 'rgba(255,255,255,.9)' ?>;
    color: <?= $dark ? '#e2e8f0' : '#1e293b' ?>;
    border-radius: 20px; padding: 5px 14px;
    font-size: 12px; font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    backdrop-filter: blur(6px);
    display: none; z-index: 1000;
  }

  /* Popup Leaflet */
  .leaflet-popup-content-wrapper {
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(0,0,0,.18) !important;
    <?= $dark ? 'background:#1e293b!important;color:#e2e8f0!important;' : '' ?>
  }
  .leaflet-popup-tip { <?= $dark ? 'background:#1e293b!important;' : '' ?> }
  .popup-card { min-width: 200px; max-width: 240px; }
  .popup-card h3 {
    font-size: 14px; font-weight: 700; margin-bottom: 4px;
    color: <?= $dark ? '#f1f5f9' : '#0f172a' ?>;
  }
  .popup-card .badge {
    display: inline-block; font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 99px; margin-bottom: 8px;
    background: #10b981; color: #fff;
  }
  .popup-card .meta {
    font-size: 12px; color: <?= $dark ? '#94a3b8' : '#64748b' ?>;
    margin-bottom: 8px; display: flex; gap: 10px;
  }
  .popup-card .meta span { display: flex; align-items: center; gap: 3px; }
  .popup-btn {
    display: block; text-align: center; width: 100%;
    background: #10b981; color: #fff;
    border-radius: 8px; padding: 7px 0;
    font-size: 12px; font-weight: 700; text-decoration: none;
    border: none; cursor: pointer;
  }
  .popup-btn:hover { background: #059669; }

  /* Attribution custom */
  .leaflet-control-attribution {
    font-size: 10px !important;
    <?= $dark ? 'background:rgba(15,23,42,.7)!important;color:#94a3b8!important;' : '' ?>
  }
  .leaflet-control-attribution a { <?= $dark ? 'color:#6ee7b7!important;' : '' ?> }
</style>
</head>
<body>

<div id="loader">
  <div class="spinner"></div>
  <p>Chargement de la carte…</p>
</div>

<div id="counter"></div>
<div id="map"></div>

<script>
(function () {
  'use strict';

  var API_BASE  = <?= json_encode($api_base) ?>;
  var TOKEN     = <?= json_encode($token) ?>;
  var VIA_SLUG  = <?= json_encode($via_slug) ?>;
  var COUNTRY   = <?= json_encode($country) ?>;
  var DARK      = <?= $dark ? 'true' : 'false' ?>;

  // ── Carte ──────────────────────────────────────────────────────────────────
  var tileUrl = DARK
    ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
    : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';

  var map = L.map('map', { zoomControl: true, tap: true })
    .setView([<?= $lat ?>, <?= $lng ?>], <?= $zoom ?>);

  L.tileLayer(tileUrl, {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
    maxZoom: 19
  }).addTo(map);

  // ── Icônes ─────────────────────────────────────────────────────────────────
  function makeIcon(color, size, border) {
    border = border || '#fff';
    return L.divIcon({
      className: '',
      html: '<div style="background:' + color + ';width:' + size + 'px;height:' + size + 'px;border-radius:50%;border:2.5px solid ' + border + ';box-shadow:0 2px 6px rgba(0,0,0,.35)"></div>',
      iconSize: [size, size], iconAnchor: [size/2, size/2], popupAnchor: [0, -size/2]
    });
  }

  var iconNormal    = makeIcon('#10b981', 14);
  var iconHighlight = makeIcon('#f97316', 22, '#fff');

  function difficultyColor(d) {
    d = parseInt(d) || 0;
    if (d <= 2) return '#22c55e';
    if (d <= 4) return '#84cc16';
    if (d <= 6) return '#eab308';
    if (d <= 8) return '#f97316';
    return '#ef4444';
  }

  function difficultyLabel(d) {
    var labels = ['', 'F', 'PD-', 'PD', 'PD+', 'AD-', 'AD', 'AD+', 'D', 'D+', 'ED'];
    return labels[parseInt(d)] || ('D' + d);
  }

  // ── Popup ──────────────────────────────────────────────────────────────────
  function buildPopup(via) {
    var rating = via.avg_overall ? parseFloat(via.avg_overall).toFixed(1) + '/10 ⭐' : 'non noté';
    var btnHref = 'viaferrata://via/' + via.slug;   // deep-link Android

    return '<div class="popup-card">'
      + '<h3>' + escHtml(via.name) + '</h3>'
      + (via.difficulty ? '<span class="badge" style="background:' + difficultyColor(via.difficulty) + '">' + difficultyLabel(via.difficulty) + '</span>' : '')
      + '<div class="meta">'
      +   (via.department_name ? '<span>📍 ' + escHtml(via.department_name) + '</span>' : '')
      +   '<span>⭐ ' + rating + '</span>'
      + '</div>'
      + '<a class="popup-btn" href="' + btnHref + '">Voir la via →</a>'
      + '</div>';
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Chargement des données ─────────────────────────────────────────────────
  var headers = { 'Content-Type': 'application/json' };
  if (TOKEN) headers['Authorization'] = 'Bearer ' + TOKEN;

  var url = API_BASE + '/vias/map';
  if (COUNTRY) url += '?country=' + encodeURIComponent(COUNTRY);

  fetch(url, { headers: headers })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      document.getElementById('loader').style.display = 'none';

      if (!res.ok || !res.data) {
        document.getElementById('loader').innerHTML = '<p style="color:#ef4444">Impossible de charger les données.</p>';
        document.getElementById('loader').style.display = 'flex';
        return;
      }

      var vias    = res.data;
      var markers = L.featureGroup().addTo(map);
      var highlighted = null;

      vias.forEach(function (via) {
        if (!via.latitude || !via.longitude) return;

        var isHighlight = VIA_SLUG && via.slug === VIA_SLUG;
        var icon = isHighlight ? iconHighlight : makeIcon(difficultyColor(via.difficulty), 14);

        var m = L.marker([via.latitude, via.longitude], { icon: icon });
        m.bindPopup(buildPopup(via), { maxWidth: 260 });
        markers.addLayer(m);

        if (isHighlight) {
          highlighted = m;
        }
      });

      // Compteur
      var counter = document.getElementById('counter');
      counter.textContent = vias.length + ' via' + (vias.length > 1 ? 's' : '');
      counter.style.display = 'block';

      // Si une via est surlignée, centrer dessus
      if (highlighted) {
        highlighted.openPopup();
        map.setView(highlighted.getLatLng(), 14);
      } else if (markers.getLayers().length > 0) {
        // Si filtre pays, adapter le zoom
        if (COUNTRY) {
          try { map.fitBounds(markers.getBounds(), { padding: [30, 30], maxZoom: 10 }); }
          catch(e) {}
        }
      }
    })
    .catch(function () {
      var loader = document.getElementById('loader');
      loader.innerHTML = '<p style="color:#ef4444;padding:20px;text-align:center">Erreur réseau – vérifiez votre connexion.</p>';
      loader.style.display = 'flex';
    });

})();
</script>
</body>
</html>
