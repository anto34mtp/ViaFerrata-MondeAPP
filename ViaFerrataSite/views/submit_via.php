<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();

$error = ''; $success = '';
$pageTitle = 'Proposer une Via Ferrata';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide.';
    } elseif (!verifyCloudflareTurnstile($_POST['cf-turnstile-response'] ?? null)) {
        $error = 'Vérification anti-spam échouée. Veuillez réessayer.';
    } else {
        $nb_parts = min(15, max(1, (int)($_POST['nb_parts'] ?? 1)));
        $location = trim($_POST['location'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        $author_email = trim($_POST['author_email'] ?? '');

        if (empty($location)) {
            $error = 'La localisation est obligatoire.';
        } else {
            // Construire les données de chaque partie
            $parts = [];
            for ($p = 1; $p <= $nb_parts; $p++) {
                $name = trim($_POST["name_$p"] ?? '');
                $desc = trim($_POST["description_$p"] ?? '');
                if (empty($name) || empty($desc)) {
                    $error = "Le nom et la description sont obligatoires pour la partie $p.";
                    break;
                }
                $parts[] = [
                    'name'           => $name,
                    'difficulty'     => (int)($_POST["difficulty_$p"] ?? 5),
                    'duration_hours' => floatval($_POST["duration_hours_$p"] ?? 0),
                    'approach_time'  => (int)($_POST["approach_time_$p"] ?? 0),
                    'return_time'    => (int)($_POST["return_time_$p"] ?? 0),
                    'elevation_gain' => (int)($_POST["elevation_gain_$p"] ?? 0),
                    'description'    => $desc,
                ];
            }

            if (empty($error)) {
                // Auto-translate name & description to French if submitted in another language
                $wasTranslated = false;
                foreach ($parts as &$part) {
                    $nameResult = Translator::toFrench($part['name']);
                    if ($nameResult['translated']) {
                        $part['name'] = $nameResult['text'];
                        $wasTranslated = true;
                    }
                    $descResult = Translator::toFrench($part['description']);
                    if ($descResult['translated']) {
                        $part['description'] = $descResult['text'];
                        $wasTranslated = true;
                    }
                }
                unset($part);

                $submission = new ViaSubmission();
                $shared = [
                    'location'     => $location,
                    'latitude'     => $latitude,
                    'longitude'    => $longitude,
                    'author_email' => $author_email,
                    'user_id'      => $auth->getUserId(),
                ];

                if ($nb_parts === 1) {
                    $data = array_merge($shared, $parts[0]);
                    $ok = (bool)$submission->create($data);
                } else {
                    $ok = $submission->createGroup($shared, $parts);
                }

                if ($ok) {
                    $success = $nb_parts === 1
                        ? 'Merci ! Votre proposition a été envoyée et sera examinée par notre équipe.'
                        : "Merci ! Vos $nb_parts parties ont été envoyées et seront examinées par notre équipe.";
                    if ($wasTranslated) {
                        $success .= ' ' . t('submit_translated');
                    }
                } else {
                    $error = 'Erreur lors de l\'envoi. Veuillez réessayer.';
                }
            }
        }
    }
}

$csrfToken = $auth->generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<style>
/* Intégration Quill au design Tailwind/slate */
.ql-toolbar.ql-snow { border-color:#cbd5e1; border-radius:.5rem .5rem 0 0; background:#f8fafc; padding:6px 8px; }
.ql-container.ql-snow { border-color:#cbd5e1; border-radius:0 0 .5rem .5rem; font-size:.875rem; font-family:inherit; min-height:110px; }
.ql-editor { min-height:110px; color:#475569; line-height:1.6; }
.ql-editor.ql-blank::before { color:#94a3b8; font-style:normal; }
.ql-snow .ql-toolbar button:hover,.ql-snow .ql-toolbar button.ql-active { color:#3b82f6; }
.ql-snow .ql-toolbar button:hover .ql-stroke,.ql-snow .ql-toolbar button.ql-active .ql-stroke { stroke:#3b82f6; }
.ql-snow .ql-toolbar button:hover .ql-fill,.ql-snow .ql-toolbar button.ql-active .ql-fill { fill:#3b82f6; }
.ql-snow .ql-picker:hover .ql-picker-label { color:#3b82f6; }
/* focus ring */
.quill-wrap:focus-within .ql-toolbar.ql-snow,
.quill-wrap:focus-within .ql-container.ql-snow { border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.15); }
</style>

<div class="max-w-2xl mx-auto px-4 sm:px-6 py-10">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">➕ Proposer une Via Ferrata</h1>
        <p class="text-slate-500 text-sm mt-1">Votre proposition sera vérifiée avant publication. Merci de la communauté !</p>
    </div>

    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm"><?= escape($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm"><?= escape($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" id="submit-form" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

        <!-- ── Nombre de parties ─────────────────────────────── -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Nombre de parties</label>
            <select name="nb_parts" id="nb-parts" class="w-full sm:w-48 border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-slate-50">
                <?php for ($i = 1; $i <= 15; $i++): ?>
                <option value="<?= $i ?>" <?= (isset($_POST['nb_parts']) && (int)$_POST['nb_parts'] === $i) ? 'selected' : ($i===1?'selected':'') ?>><?= $i === 1 ? '1 partie (via unique)' : "$i parties" ?></option>
                <?php endfor; ?>
            </select>
            <p class="text-xs text-slate-400 mt-1.5">Exemples : 2 parties pour "Adultes + Enfants", 7 pour les circuits du Diable d'Avrieux.</p>
        </div>

        <!-- ── Informations communes ──────────────────────────── -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <span class="bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded-md">Commun à toutes les parties</span>
            </h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Localisation (ville / commune) <span class="text-red-500">*</span></label>
                    <input type="text" name="location" required
                           value="<?= escape($_POST['location'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : Avrieux, Savoie">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Latitude GPS</label>
                    <input type="number" name="latitude" step="0.000001"
                           value="<?= escape($_POST['latitude'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 45.2310">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Longitude GPS</label>
                    <input type="number" name="longitude" step="0.000001"
                           value="<?= escape($_POST['longitude'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 6.7520">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Votre email (pour suivi)</label>
                    <input type="email" name="author_email"
                           value="<?= escape($_POST['author_email'] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="contact@exemple.fr">
                </div>
            </div>
        </div>

        <!-- ── Sections par partie (1 à 15, JS les affiche/cache) ── -->
        <?php for ($p = 1; $p <= 15; $p++): ?>
        <div class="part-section bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
             data-part="<?= $p ?>" <?= $p > 1 ? 'style="display:none"' : '' ?>>

            <!-- En-tête de partie -->
            <div class="flex items-center gap-3 px-5 py-3.5 bg-slate-50 border-b border-slate-200">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-brand-500 text-white text-xs font-bold flex items-center justify-center"><?= $p ?></span>
                <span class="font-semibold text-slate-800 text-sm">Partie <?= $p ?></span>
            </div>

            <div class="p-5 grid sm:grid-cols-2 gap-4">
                <!-- Nom -->
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nom de la voie <span class="text-red-500">*</span></label>
                    <input type="text" name="name_<?= $p ?>"
                           value="<?= escape($_POST["name_$p"] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : Via Ferrata du Diable — Les Angelots">
                </div>
                <!-- Difficulté -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Difficulté <span class="text-slate-400">(1 = facile, 10 = extrême)</span></label>
                    <select name="difficulty_<?= $p ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none bg-slate-50">
                        <?php
                        $labels = ['','F — Facile','F — Facile','PD','AD','D — Difficile','D — Difficile','TD','TD','ED — Extrême','ED — Extrême'];
                        for ($d = 1; $d <= 10; $d++):
                            $sel = ((int)($_POST["difficulty_$p"] ?? 5)) === $d ? 'selected' : '';
                        ?>
                        <option value="<?= $d ?>" <?= $sel ?>><?= $d ?> — <?= $labels[$d] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Durée -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Durée totale (heures)</label>
                    <input type="number" name="duration_hours_<?= $p ?>" step="0.5" min="0"
                           value="<?= escape($_POST["duration_hours_$p"] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 2.5">
                </div>
                <!-- Approche -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Temps d'approche (min.)</label>
                    <input type="number" name="approach_time_<?= $p ?>" min="0"
                           value="<?= escape($_POST["approach_time_$p"] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 30">
                </div>
                <!-- Retour -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Temps de retour (min.)</label>
                    <input type="number" name="return_time_<?= $p ?>" min="0"
                           value="<?= escape($_POST["return_time_$p"] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 45">
                </div>
                <!-- Dénivelé -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dénivelé (m.)</label>
                    <input type="number" name="elevation_gain_<?= $p ?>" min="0"
                           value="<?= escape($_POST["elevation_gain_$p"] ?? '') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
                           placeholder="ex : 300">
                </div>
                <!-- Description (éditeur riche) -->
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description <span class="text-red-500">*</span></label>
                    <input type="hidden" name="description_<?= $p ?>" id="desc-hidden-<?= $p ?>"
                           value="<?= escape($_POST["description_$p"] ?? '') ?>">
                    <div class="quill-wrap">
                        <div id="quill-<?= $p ?>"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>

        <!-- ── Envoi ──────────────────────────────────────────── -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
            <div class="cf-turnstile mb-4" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="light"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <button type="submit" class="w-full py-3 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl shadow-sm transition-colors text-sm">
                Envoyer ma proposition
            </button>
            <p class="text-xs text-slate-400 text-center mt-2">Cette proposition sera vérifiée par notre équipe avant publication.</p>
        </div>
    </form>

    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
    (function() {
        var select   = document.getElementById('nb-parts');
        var sections = document.querySelectorAll('.part-section');
        var TOTAL    = 15;

        // ── Toolbar Quill ─────────────────────────────────────────
        var toolbar = [
            ['bold', 'italic', 'underline'],
            [{ header: [2, 3, false] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'blockquote'],
            ['clean']
        ];

        // ── Initialisation des éditeurs ───────────────────────────
        var editors = {};
        for (var i = 1; i <= TOTAL; i++) {
            (function(p) {
                var q = new Quill('#quill-' + p, {
                    theme: 'snow',
                    modules: { toolbar: toolbar },
                    placeholder: 'Ambiance, équipement, accessibilité, points remarquables...'
                });
                // Pré-remplissage si POST (validation échouée)
                var hidden = document.getElementById('desc-hidden-' + p);
                if (hidden && hidden.value.trim()) {
                    q.clipboard.dangerouslyPasteHTML(hidden.value);
                }
                editors[p] = q;
            })(i);
        }

        // ── Affiche/cache les sections ────────────────────────────
        function updateSections() {
            var n = parseInt(select.value, 10);
            sections.forEach(function(sec) {
                var p = parseInt(sec.getAttribute('data-part'), 10);
                sec.style.display = p <= n ? '' : 'none';
                // required sur name uniquement (description validée manuellement)
                sec.querySelectorAll('input[name^="name_"]').forEach(function(el) {
                    el.required = p <= n;
                });
            });
        }

        select.addEventListener('change', updateSections);
        updateSections();

        // ── Collecte HTML + validation au submit ──────────────────
        document.getElementById('submit-form').addEventListener('submit', function(e) {
            var n = parseInt(select.value, 10);
            for (var p = 1; p <= n; p++) {
                var q      = editors[p];
                var hidden = document.getElementById('desc-hidden-' + p);
                if (!q || !hidden) continue;
                var text = q.getText().trim();
                if (!text) {
                    e.preventDefault();
                    // Scroll vers la section manquante
                    var sec = document.querySelector('.part-section[data-part="' + p + '"]');
                    if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    q.focus();
                    alert('La description de la partie ' + p + ' est obligatoire.');
                    return;
                }
                hidden.value = q.root.innerHTML;
            }
        });
    })();
    </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
