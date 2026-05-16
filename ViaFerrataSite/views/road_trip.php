<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();
$auth->requireAuth(BASE_URL . '/connexion');

$userId        = $auth->getUserId();
$tripModel     = new RoadTrip();
$favoriteModel = new Favorite();
$csrfToken     = $auth->generateCsrfToken();

// ── Determine mode: list (no id) or planner (with id) ──────────────────────
$tripId     = isset($trip_id) && (int)$trip_id > 0 ? (int)$trip_id : 0;
$activeTrip = null;
$viasByDay  = [];
$isOwner    = false;
$shares     = [];

if ($tripId) {
    $activeTrip = $tripModel->getById($tripId);
    $isOwner    = $activeTrip && $tripModel->owns($tripId, $userId);
    $canView    = $activeTrip && ($isOwner || $tripModel->canView($tripId, $userId));
    if (!$canView) {
        http_response_code(403);
        setFlash('error', 'Trip introuvable ou accès refusé.');
        redirect(BASE_URL . '/road-trip');
    }
    $viasByDay = $tripModel->getViasByDay($tripId);
    if ($isOwner) $shares = $tripModel->getShares($tripId, $userId);
}

// ── All user trips (for list and sidebar selector) ─────────────────────────
$myTrips  = $tripModel->getByUser($userId);
$todoVias = $isOwner ? $favoriteModel->getByUser($userId, 'to_do') : [];

$pageTitle = t('trips_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

<?php if (!$tripId): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     LIST MODE — all trips + create form
══════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">🗺️ <?= t('trips_title') ?></h1>
    <button onclick="document.getElementById('create-panel').classList.toggle('hidden')"
            class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2 rounded-xl text-sm shadow-sm transition-colors">
        + <?= t('trips_create') ?>
    </button>
</div>

<!-- Create form (collapsible) -->
<div id="create-panel" class="hidden mb-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-bold text-slate-900 mb-4">✨ <?= t('trips_create') ?></h2>
    <form method="POST" action="<?= BASE_URL ?>/api/trip/create" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_name') ?> <span class="text-red-500">*</span></label>
                <input type="text" name="trip_name" required maxlength="255" placeholder="Ex : Alpes du Sud 2026"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_start_date') ?></label>
                <input type="date" name="start_date"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_end_date') ?></label>
                <input type="date" name="end_date"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_nb_days') ?></label>
                <input type="number" name="nb_days" value="3" min="1" max="30"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_description') ?></label>
                <input type="text" name="description" maxlength="500"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            </div>
        </div>
        <div class="flex gap-3 pt-1">
            <button type="submit"
                    class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                <?= t('btn_create_trip') ?>
            </button>
            <button type="button" onclick="document.getElementById('create-panel').classList.add('hidden')"
                    class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                <?= t('cancel') ?>
            </button>
        </div>
    </form>
</div>

<!-- Trips grid -->
<?php if (empty($myTrips)): ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
    <div class="text-5xl mb-4">🗺️</div>
    <h3 class="font-bold text-slate-700 text-lg"><?= t('trips_empty') ?></h3>
    <p class="text-slate-400 text-sm mt-2 mb-5"><?= t('trips_empty_msg') ?></p>
    <button onclick="document.getElementById('create-panel').classList.remove('hidden');document.getElementById('create-panel').scrollIntoView({behavior:'smooth'})"
            class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
        + <?= t('trips_create') ?>
    </button>
</div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($myTrips as $trip): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden group">
        <div class="h-2 bg-gradient-to-r from-brand-400 to-brand-600"></div>
        <div class="p-5">
            <div class="flex items-start justify-between gap-2 mb-3">
                <h3 class="font-bold text-slate-900 text-base line-clamp-1"><?= escape($trip['name']) ?></h3>
                <form method="POST" action="<?= BASE_URL ?>/api/trip/delete"
                      onsubmit="return confirm('<?= escape(t('btn_delete_trip')) ?> ?')"
                      class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
                    <button type="submit" class="p-1.5 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="<?= t('delete') ?>">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                        </svg>
                    </button>
                </form>
            </div>
            <?php if (!empty($trip['description'])): ?>
            <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?= escape($trip['description']) ?></p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-2 text-xs text-slate-500 mb-4">
                <?php if (!empty($trip['start_date'])): ?>
                <span>📅 <?= date('d/m/Y', strtotime($trip['start_date'])) ?>
                    <?php if (!empty($trip['end_date'])): ?>→ <?= date('d/m/Y', strtotime($trip['end_date'])) ?><?php endif; ?>
                </span>
                <?php endif; ?>
                <span>📆 <?= (int)$trip['nb_days'] ?> <?= t('trip_days') ?></span>
                <span>📍 <?= (int)$trip['via_count'] ?> <?= t('trip_vias') ?></span>
            </div>
            <a href="<?= BASE_URL ?>/road-trip/<?= (int)$trip['id'] ?>"
               class="block w-full text-center bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2 rounded-xl text-sm transition-colors">
                <?= t('trip_planner') ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════
     PLANNER MODE — single trip with day-by-day organizer
══════════════════════════════════════════════════════════════════════ -->

<!-- Read-only banner for shared trips -->
<?php if (!$isOwner): ?>
<div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-2.5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
    </svg>
    <?= t('trip_read_only') ?> <strong><?= escape($activeTrip['owner_name'] ?? '') ?></strong>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div class="min-w-0">
        <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
            <a href="<?= BASE_URL ?>/road-trip" class="hover:text-brand-600 transition-colors">← <?= t('trips_title') ?></a>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
            🗺️ <span id="trip-title-display"><?= escape($activeTrip['name']) ?></span>
        </h1>
        <div class="flex flex-wrap gap-3 mt-1 text-sm text-slate-500">
            <?php if (!empty($activeTrip['start_date'])): ?>
            <span>📅 <?= date('d/m/Y', strtotime($activeTrip['start_date'])) ?>
                  <?php if (!empty($activeTrip['end_date'])): ?>→ <?= date('d/m/Y', strtotime($activeTrip['end_date'])) ?><?php endif; ?>
            </span>
            <?php endif; ?>
            <span>📆 <?= (int)$activeTrip['nb_days'] ?> <?= t('trip_days') ?></span>
            <span>📍 <?= array_sum(array_map('count', $viasByDay)) ?> <?= t('trip_vias') ?></span>
        </div>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?php if ($isOwner): ?>
        <button onclick="document.getElementById('edit-trip-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 border border-slate-300 text-slate-600 hover:bg-slate-50 font-medium px-3 py-2 rounded-xl text-sm transition-colors">
            ✏️ <?= t('edit') ?>
        </button>
        <button onclick="document.getElementById('share-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 border border-brand-300 text-brand-600 hover:bg-brand-50 font-medium px-3 py-2 rounded-xl text-sm transition-colors">
            🔗 <?= t('trip_share') ?>
        </button>
        <button onclick="document.getElementById('add-via-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2 rounded-xl text-sm transition-colors shadow-sm">
            + <?= t('trip_from_bucket') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Day tabs -->
<?php $nbDays = (int)$activeTrip['nb_days']; ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
    <!-- Tab bar -->
    <div class="flex overflow-x-auto border-b border-slate-100" id="day-tabs">
        <?php for ($d = 1; $d <= $nbDays; $d++): ?>
        <?php $dayVias = $viasByDay[$d] ?? []; ?>
        <button onclick="showDay(<?= $d ?>)"
                id="day-tab-<?= $d ?>"
                class="day-tab flex-shrink-0 flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all
                    <?= $d === 1 ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <?= t('trip_day') ?> <?= $d ?>
            <?php if (!empty($dayVias)): ?>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                <?= $d === 1 ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' ?>">
                <?= count($dayVias) ?>
            </span>
            <?php endif; ?>
        </button>
        <?php endfor; ?>
    </div>

    <!-- Day panels -->
    <div class="flex flex-col lg:flex-row gap-0">
        <!-- Via list -->
        <div class="flex-1 min-w-0 p-5">
            <?php for ($d = 1; $d <= $nbDays; $d++): ?>
            <?php $dayVias = $viasByDay[$d] ?? []; ?>
            <div id="day-panel-<?= $d ?>" class="day-panel <?= $d !== 1 ? 'hidden' : '' ?>">

                <!-- Day stats -->
                <?php
                $totalDur = 0; $totalKm = 0;
                foreach ($dayVias as $dv) {
                    $totalDur += (float)($dv['duration_hours'] ?? 0);
                    $totalKm  += (float)($dv['length_km'] ?? 0);
                }
                // Build Google Maps URL for this day
                $gpsVias = array_filter($dayVias, fn($v) => !empty($v['latitude']) && !empty($v['longitude']));
                ?>
                <?php if (!empty($dayVias)): ?>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mb-4 pb-3 border-b border-slate-100">
                    <?php if ($totalDur > 0): ?><span>⏱️ <?= number_format($totalDur, 1) ?> <?= t('trip_total_dur') ?></span><?php endif; ?>
                    <?php if ($totalKm > 0):  ?><span>📏 <?= number_format($totalKm, 1) ?> <?= t('trip_total_km') ?></span><?php endif; ?>
                    <?php if ($isOwner): ?><span class="text-slate-400"><?= t('trip_drag_hint') ?></span><?php endif; ?>
                    <button onclick="openGMaps(<?= $d ?>)"
                            class="ml-auto flex-shrink-0 inline-flex items-center gap-1 bg-white border border-slate-200 hover:border-brand-300 hover:bg-brand-50 text-slate-600 hover:text-brand-600 text-xs font-medium px-2.5 py-1 rounded-lg transition-colors">
                        <?= t('trip_gmaps') ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Sortable via list -->
                <ul id="sortable-day-<?= $d ?>" class="space-y-2 min-h-[60px]" data-day="<?= $d ?>" data-trip="<?= $tripId ?>">
                    <?php foreach ($dayVias as $dv):
                        $imgUrl    = !empty($dv['image_url']) ? escape($dv['image_url']) : BASE_URL.'/assets/images/default.png';
                        $diffLabel = getDifficultyLabel((int)($dv['difficulty'] ?? 5));
                    ?>
                    <li class="via-item group flex items-center gap-3 bg-slate-50 hover:bg-white border border-slate-200 hover:border-brand-200 hover:shadow-sm rounded-xl p-3 <?= $isOwner ? 'cursor-grab active:cursor-grabbing' : '' ?> transition-all"
                        data-via-id="<?= (int)$dv['via_id'] ?>">
                        <?php if ($isOwner): ?>
                        <!-- Drag handle -->
                        <span class="text-slate-300 flex-shrink-0 cursor-grab">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                        <!-- Position badge -->
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-brand-500 text-white text-xs font-bold flex items-center justify-center pos-badge"></span>
                        <!-- Thumbnail -->
                        <a href="<?= BASE_URL ?>/france/<?= escape($dv['slug']) ?>" target="_blank" class="flex-shrink-0 w-14 h-10 rounded-lg overflow-hidden bg-slate-200">
                            <img src="<?= $imgUrl ?>" alt="" class="w-full h-full object-cover"
                                 onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                        </a>
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <a href="<?= BASE_URL ?>/france/<?= escape($dv['slug']) ?>" target="_blank"
                               class="font-semibold text-slate-900 hover:text-brand-600 text-sm line-clamp-1 transition-colors">
                                <?= escape($dv['name']) ?>
                            </a>
                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-400 mt-0.5">
                                <?php if (!empty($dv['location'])): ?>
                                <span>📍 <?= escape($dv['location']) ?></span>
                                <?php endif; ?>
                                <span><?= escape($diffLabel) ?></span>
                                <?php if (!empty($dv['duration_hours'])): ?>
                                <span>⏱ <?= escape($dv['duration_hours']) ?>h</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isOwner): ?>
                        <!-- Move to day selector -->
                        <select onchange="moveToDay(<?= $tripId ?>, <?= (int)$dv['via_id'] ?>, this.value, this)"
                                class="flex-shrink-0 hidden group-hover:block border border-slate-300 rounded-lg text-xs px-2 py-1 bg-white outline-none focus:ring-1 focus:ring-brand-500"
                                title="<?= t('trip_select_day') ?>">
                            <?php for ($dd = 1; $dd <= $nbDays; $dd++): ?>
                            <option value="<?= $dd ?>" <?= $dd === $d ? 'selected' : '' ?>><?= t('trip_day') ?> <?= $dd ?></option>
                            <?php endfor; ?>
                        </select>
                        <!-- Remove -->
                        <button onclick="removeVia(<?= $tripId ?>, <?= (int)$dv['via_id'] ?>, this)"
                                class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1.5 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                title="<?= t('delete') ?>">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (empty($dayVias)): ?>
                <div class="text-center py-8 text-slate-400 text-sm">
                    <div class="text-3xl mb-2">📍</div>
                    <?= t('trip_no_via_day') ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <!-- RIGHT: map -->
        <div class="lg:w-80 flex-shrink-0 border-t lg:border-t-0 lg:border-l border-slate-100">
            <div id="trip-map" class="h-64 lg:h-full lg:min-h-[400px] bg-slate-100 w-full"></div>
        </div>
    </div>
</div>

<?php if ($isOwner): ?>
<!-- Edit trip modal -->
<div id="edit-trip-modal" class="hidden fixed inset-0 z-[1001] flex items-center justify-center p-4 bg-black/60"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">✏️ <?= t('edit') ?></h3>
            <button onclick="document.getElementById('edit-trip-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/api/trip/update" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="trip_id" value="<?= $tripId ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_name') ?></label>
                <input type="text" name="trip_name" required maxlength="255" value="<?= escape($activeTrip['name']) ?>"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_start_date') ?></label>
                    <input type="date" name="start_date" value="<?= escape($activeTrip['start_date'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_end_date') ?></label>
                    <input type="date" name="end_date" value="<?= escape($activeTrip['end_date'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_nb_days') ?></label>
                <input type="number" name="nb_days" min="1" max="30" value="<?= (int)$activeTrip['nb_days'] ?>"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_description') ?></label>
                <input type="text" name="description" maxlength="500" value="<?= escape($activeTrip['description'] ?? '') ?>"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors"><?= t('save') ?></button>
                <button type="button" onclick="document.getElementById('edit-trip-modal').classList.add('hidden')"
                        class="border border-slate-300 text-slate-600 px-5 py-2.5 rounded-xl text-sm hover:bg-slate-50 transition-colors"><?= t('cancel') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Add via modal (from bucket list) -->
<div id="add-via-modal" class="hidden fixed inset-0 z-[1001] flex items-center justify-center p-4 bg-black/60"
     onclick="if(event.target===this) closeAddViaModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[80vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-slate-900">📍 <?= t('trip_from_bucket') ?></h3>
            <button onclick="closeAddViaModal()" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>

        <!-- Day selector -->
        <div class="mb-4 flex-shrink-0">
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('trip_select_day') ?></label>
            <select id="modal-day-select" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                <?php for ($d = 1; $d <= $nbDays; $d++): ?>
                <option value="<?= $d ?>"><?= t('trip_day') ?> <?= $d ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Search -->
        <input type="text" id="modal-via-search" placeholder="🔍 Rechercher..."
               oninput="filterModalVias(this.value)"
               class="mb-3 flex-shrink-0 w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">

        <!-- Via list -->
        <div class="overflow-y-auto flex-1 space-y-2" id="modal-via-list">
            <?php if (empty($todoVias)): ?>
            <div class="text-center py-8 text-slate-400 text-sm">
                <div class="text-3xl mb-2">🏔️</div>
                <?= t('bucket_empty_msg') ?>
                <br>
                <a href="<?= BASE_URL ?>/france" class="text-brand-600 hover:underline mt-2 inline-block"><?= t('btn_explore_vias') ?></a>
            </div>
            <?php else: ?>
            <?php
            $addedIds = [];
            foreach ($viasByDay as $dayVias) {
                foreach ($dayVias as $dv) $addedIds[] = (int)$dv['via_id'];
            }
            ?>
            <?php foreach ($todoVias as $tv):
                $imgUrl  = !empty($tv['image_url']) ? escape($tv['image_url']) : BASE_URL.'/assets/images/default.png';
                $already = in_array((int)$tv['via_id'], $addedIds);
            ?>
            <div class="modal-via-item flex items-center gap-3 p-3 border border-slate-200 rounded-xl hover:border-brand-200 hover:bg-brand-50/30 transition-all"
                 data-name="<?= strtolower(escape($tv['name'])) ?>">
                <img src="<?= $imgUrl ?>" alt="" class="flex-shrink-0 w-12 h-10 rounded-lg object-cover bg-slate-100"
                     onerror="this.src='<?= BASE_URL ?>/assets/images/default.png'">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-900 text-sm line-clamp-1"><?= escape($tv['name']) ?></p>
                    <?php if (!empty($tv['location'])): ?><p class="text-xs text-slate-400">📍 <?= escape($tv['location']) ?></p><?php endif; ?>
                </div>
                <button onclick="addViaToTrip(<?= $tripId ?>, <?= (int)$tv['via_id'] ?>, this)"
                        <?= $already ? 'disabled' : '' ?>
                        class="flex-shrink-0 <?= $already ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-brand-500 hover:bg-brand-600 text-white' ?> font-semibold text-xs px-3 py-1.5 rounded-lg transition-colors min-w-[60px] text-center">
                    <?= $already ? '✓' : '+ ' . t('btn_add_to_trip') ?>
                </button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Share modal -->
<div id="share-modal" class="hidden fixed inset-0 z-[1001] flex items-center justify-center p-4 bg-black/60"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[85vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-lg font-bold text-slate-900">🔗 <?= t('trip_share_title') ?></h3>
            <button onclick="document.getElementById('share-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 bg-slate-100 rounded-xl p-1 flex-shrink-0">
            <button onclick="showShareTab('user')" id="share-tab-user"
                    class="flex-1 py-2 text-sm font-semibold rounded-lg transition-colors bg-white text-slate-900 shadow-sm">
                👤 <?= t('trip_share_by_user') ?>
            </button>
            <button onclick="showShareTab('email')" id="share-tab-email"
                    class="flex-1 py-2 text-sm font-semibold rounded-lg transition-colors text-slate-500 hover:text-slate-700">
                ✉️ <?= t('trip_share_by_email') ?>
            </button>
        </div>

        <!-- Tab: By user -->
        <div id="share-panel-user" class="flex-shrink-0">
            <div class="relative">
                <input type="text" id="share-user-input"
                       placeholder="<?= escape(t('trip_share_placeholder')) ?>"
                       oninput="searchUsersToShare(this.value)"
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                <div id="share-user-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">⏳</div>
            </div>
            <div id="share-user-results" class="mt-2 space-y-1 max-h-40 overflow-y-auto"></div>
        </div>

        <!-- Tab: By email -->
        <div id="share-panel-email" class="hidden flex-shrink-0">
            <div class="flex gap-2">
                <input type="email" id="share-email-input"
                       placeholder="<?= escape(t('trip_share_email_ph')) ?>"
                       class="flex-1 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                <button onclick="shareByEmailAction()"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors flex-shrink-0">
                    <?= t('trip_share_btn') ?>
                </button>
            </div>
            <div id="share-email-result" class="mt-2 text-sm min-h-[1.5rem]"></div>
        </div>

        <!-- Current shares -->
        <div class="mt-5 pt-4 border-t border-slate-100 overflow-y-auto flex-1">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3"><?= t('trip_shared_with') ?></h4>
            <div id="shares-list" class="space-y-2">
                <?php if (empty($shares)): ?>
                <p class="text-sm text-slate-400 text-center py-2"><?= t('trip_not_shared_yet') ?></p>
                <?php else: ?>
                <?php foreach ($shares as $share): ?>
                <div class="flex items-center justify-between gap-3 py-2 px-3 bg-slate-50 rounded-xl" id="share-item-<?= (int)$share['id'] ?>">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-700 font-bold text-sm flex items-center justify-center flex-shrink-0">
                            <?= mb_strtoupper(mb_substr($share['username'] ?? $share['invite_email'] ?? '?', 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">
                                <?= escape($share['username'] ?? $share['invite_email'] ?? 'Invitation') ?>
                            </p>
                            <?php if (empty($share['shared_with']) && !empty($share['invite_email'])): ?>
                            <p class="text-xs text-amber-600">⏳ En attente d'inscription</p>
                            <?php elseif (!empty($share['accepted_at'])): ?>
                            <p class="text-xs text-green-600">✓ Accès accordé</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button onclick="removeShareAction(<?= (int)$share['id'] ?>)"
                            class="flex-shrink-0 text-xs text-slate-400 hover:text-red-500 px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                        <?= t('trip_remove_share') ?>
                    </button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; // isOwner modals ?>

<?php endif; // planner mode ?>
</div><!-- /container -->

<!-- Leaflet + SortableJS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
const CSRF    = <?= json_encode($csrfToken) ?>;
const API_URL = <?= json_encode(BASE_URL . '/api/trip') ?>;
const TRIP_ID = <?= $tripId ?>;
const NB_DAYS = <?= isset($nbDays) ? $nbDays : 0 ?>;
const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
const GMAPS_NO_GPS = <?= json_encode(t('trip_gmaps_no_gps')) ?>;

<?php if ($tripId): ?>
// ── Day tabs ─────────────────────────────────────────────────────────────
let currentDay = 1;
function showDay(day) {
    currentDay = day;
    document.querySelectorAll('.day-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.day-tab').forEach((t, i) => {
        const active = (i + 1) === day;
        t.classList.toggle('border-brand-500', active);
        t.classList.toggle('text-brand-600',   active);
        t.classList.toggle('border-transparent', !active);
        t.classList.toggle('text-slate-500',    !active);
        const badge = t.querySelector('span');
        if (badge) {
            badge.classList.toggle('bg-brand-100', active);
            badge.classList.toggle('text-brand-700', active);
            badge.classList.toggle('bg-slate-100', !active);
            badge.classList.toggle('text-slate-500', !active);
        }
    });
    document.getElementById('day-panel-' + day)?.classList.remove('hidden');
    updateMapForDay(day);
    updatePositionBadges(day);
}

// ── Position badges ────────────────────────────────────────────────────
function updatePositionBadges(day) {
    const ul = document.getElementById('sortable-day-' + day);
    if (!ul) return;
    ul.querySelectorAll('.pos-badge').forEach((b, i) => { b.textContent = i + 1; });
}

// ── SortableJS (owner only) ────────────────────────────────────────────
if (IS_OWNER) {
    for (let d = 1; d <= NB_DAYS; d++) {
        const ul = document.getElementById('sortable-day-' + d);
        if (!ul) continue;
        Sortable.create(ul, {
            animation: 150,
            ghostClass: 'opacity-40',
            handle: 'li',
            onEnd: function(evt) {
                const viaIds = [...evt.from.querySelectorAll('[data-via-id]')].map(el => +el.dataset.viaId);
                const day = +evt.from.dataset.day;
                updatePositionBadges(day);
                updateMapForDay(currentDay);
                fetch(API_URL + '/reorder', {
                    method: 'POST',
                    body: new URLSearchParams({ csrf_token: CSRF, trip_id: TRIP_ID, day, via_ids: JSON.stringify(viaIds) })
                });
            }
        });
        updatePositionBadges(d);
    }
}

// ── Add via (keep modal open) ──────────────────────────────────────────
let _viasWereAdded = false;

async function addViaToTrip(tripId, viaId, btn) {
    const day = +document.getElementById('modal-day-select').value;
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '...';
    const r = await fetch(API_URL + '/add-via', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: tripId, via_id: viaId, day_number: day })
    });
    const d = await r.json();
    if (d.ok) {
        _viasWereAdded = true;
        btn.textContent = '✓';
        btn.classList.remove('bg-brand-500', 'hover:bg-brand-600', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-400', 'cursor-not-allowed');
    } else {
        btn.disabled = false;
        btn.textContent = origText;
    }
}

function closeAddViaModal() {
    document.getElementById('add-via-modal').classList.add('hidden');
    if (_viasWereAdded) {
        _viasWereAdded = false;
        sessionStorage.setItem('reopen_add_modal', '1');
        location.reload();
    }
}

// ── Remove via ─────────────────────────────────────────────────────────
async function removeVia(tripId, viaId, btn) {
    const li = btn.closest('li');
    const r = await fetch(API_URL + '/remove-via', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: tripId, via_id: viaId })
    });
    const d = await r.json();
    if (d.ok) {
        li.style.opacity = '0'; li.style.transform = 'scale(.95)'; li.style.transition = 'all .2s';
        setTimeout(() => { li.remove(); updatePositionBadges(currentDay); }, 200);
    }
}

// ── Move to day ────────────────────────────────────────────────────────
async function moveToDay(tripId, viaId, newDay, sel) {
    if (+newDay === currentDay) return;
    const r = await fetch(API_URL + '/move-via', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: tripId, via_id: viaId, day_number: newDay })
    });
    const d = await r.json();
    if (d.ok) location.reload();
    else sel.value = currentDay;
}

// ── Modal search ───────────────────────────────────────────────────────
function filterModalVias(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.modal-via-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}

// ── Google Maps ────────────────────────────────────────────────────────
const viasByDay = <?= json_encode($viasByDay) ?>;

function openGMaps(day) {
    const vias   = viasByDay[day] || [];
    const coords = vias.filter(v => v.latitude && v.longitude)
                       .map(v => encodeURIComponent(v.latitude + ',' + v.longitude));
    if (!coords.length) { alert(GMAPS_NO_GPS); return; }
    window.open('https://www.google.com/maps/dir/current+location/' + coords.join('/'), '_blank');
}

// ── Map ────────────────────────────────────────────────────────────────
let tripMap = null;
function updateMapForDay(day) {
    const mapEl = document.getElementById('trip-map');
    if (!mapEl) return;
    const vias   = viasByDay[day] || [];
    const coords = vias.filter(v => v.latitude && v.longitude).map(v => [+v.latitude, +v.longitude]);
    if (!tripMap) {
        tripMap = L.map('trip-map', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: '© CARTO' }).addTo(tripMap);
    }
    tripMap.eachLayer(l => { if (l instanceof L.Marker || l instanceof L.Polyline) tripMap.removeLayer(l); });
    if (!coords.length) return;
    const icon = L.divIcon({ className: '', html: '<div style="background:#10b981;width:16px;height:16px;border-radius:50%;border:2.5px solid white;box-shadow:0 0 5px rgba(0,0,0,.4)"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });
    coords.forEach((c, i) => {
        L.marker(c, { icon }).addTo(tripMap).bindTooltip((i+1) + '. ' + (vias[i].name || ''), { direction: 'top' });
    });
    if (coords.length > 1) L.polyline(coords, { color: '#10b981', weight: 2, dashArray: '4 4', opacity: 0.7 }).addTo(tripMap);
    tripMap.fitBounds(coords, { padding: [20, 20] });
}

// ── Share modal ────────────────────────────────────────────────────────
function showShareTab(tab) {
    ['user', 'email'].forEach(t => {
        document.getElementById('share-panel-' + t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('share-tab-' + t);
        const active = t === tab;
        btn.classList.toggle('bg-white',      active);
        btn.classList.toggle('text-slate-900', active);
        btn.classList.toggle('shadow-sm',      active);
        btn.classList.toggle('text-slate-500', !active);
    });
}

let _searchTimer = null;
function searchUsersToShare(q) {
    clearTimeout(_searchTimer);
    const res = document.getElementById('share-user-results');
    if (q.length < 2) { res.innerHTML = ''; return; }
    document.getElementById('share-user-spinner').classList.remove('hidden');
    _searchTimer = setTimeout(async () => {
        try {
            const r = await fetch(API_URL + '/search-users?q=' + encodeURIComponent(q) + '&trip_id=' + TRIP_ID);
            const users = await r.json();
            document.getElementById('share-user-spinner').classList.add('hidden');
            if (!users.length) {
                res.innerHTML = '<p class="text-sm text-slate-400 py-2 text-center">Aucun utilisateur trouvé.</p>';
            } else {
                res.innerHTML = users.map(u => `
                    <div class="flex items-center justify-between gap-3 py-2 px-3 bg-slate-50 hover:bg-brand-50 rounded-xl cursor-pointer transition-colors"
                         onclick="shareWithUserAction(${u.id}, ${JSON.stringify(u.username)})">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-brand-100 text-brand-700 font-bold text-xs flex items-center justify-center">
                                ${u.username.charAt(0).toUpperCase()}
                            </div>
                            <span class="text-sm font-medium text-slate-800">${u.username}</span>
                        </div>
                        <span class="text-xs text-brand-600 font-semibold">+ Partager</span>
                    </div>`).join('');
            }
        } catch {
            document.getElementById('share-user-spinner').classList.add('hidden');
        }
    }, 300);
}

async function shareWithUserAction(userId, username) {
    const r = await fetch(API_URL + '/share', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: TRIP_ID, type: 'user', user_id: userId })
    });
    const d = await r.json();
    if (d.ok) {
        document.getElementById('share-user-input').value = '';
        document.getElementById('share-user-results').innerHTML =
            `<p class="text-sm text-green-600 py-1">✓ ${username} peut maintenant voir ce road trip.</p>`;
        sessionStorage.setItem('reopen_share_modal', '1');
        setTimeout(() => location.reload(), 1500);
    } else {
        alert(d.msg || 'Erreur lors du partage.');
    }
}

async function shareByEmailAction() {
    const email = document.getElementById('share-email-input').value.trim();
    if (!email) return;
    const res = document.getElementById('share-email-result');
    res.innerHTML = '<span class="text-slate-400">⏳ Envoi...</span>';
    const r = await fetch(API_URL + '/share', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: TRIP_ID, type: 'email', email })
    });
    const d = await r.json();
    if (d.ok) {
        const msgs = { direct: `✓ ${d.username} peut maintenant voir ce road trip.`, already: `✓ ${d.username} a déjà accès.`, invite: `✓ Invitation envoyée à ${email}.` };
        res.innerHTML = `<span class="text-green-600">${msgs[d.type] || 'Partagé !'}</span>`;
        document.getElementById('share-email-input').value = '';
        sessionStorage.setItem('reopen_share_modal', '1');
        setTimeout(() => location.reload(), 2000);
    } else {
        const errs = { self: 'Vous ne pouvez pas partager avec vous-même.', already_invited: 'Une invitation a déjà été envoyée à cet email.' };
        res.innerHTML = `<span class="text-red-600">${errs[d.msg] || (d.msg || 'Erreur.')}</span>`;
    }
}

async function removeShareAction(shareId) {
    if (!confirm('Révoquer cet accès ?')) return;
    const r = await fetch(API_URL + '/unshare', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF, trip_id: TRIP_ID, share_id: shareId })
    });
    const d = await r.json();
    if (d.ok) document.getElementById('share-item-' + shareId)?.remove();
}

// ── Session storage: reopen modals after reload ────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateMapForDay(1);
    if (IS_OWNER) {
        if (sessionStorage.getItem('reopen_add_modal') === '1') {
            sessionStorage.removeItem('reopen_add_modal');
            document.getElementById('add-via-modal')?.classList.remove('hidden');
        }
        if (sessionStorage.getItem('reopen_share_modal') === '1') {
            sessionStorage.removeItem('reopen_share_modal');
            document.getElementById('share-modal')?.classList.remove('hidden');
        }
    }
});
<?php else: ?>
document.addEventListener('DOMContentLoaded', () => {});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
