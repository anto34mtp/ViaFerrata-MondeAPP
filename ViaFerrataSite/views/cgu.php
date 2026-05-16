<?php
require_once __DIR__ . '/../config/config.php';
$auth = new Auth();
$pageTitle = 'Conditions Générales d\'Utilisation';
$pageDesc  = 'Conditions d\'utilisation et mentions légales de ViaFerrata-Monde.fr — site d\'information à titre indicatif.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Conditions Générales d'Utilisation</h1>
        <p class="text-slate-500 text-sm">Dernière mise à jour : <?= date('d/m/Y') ?> — ViaFerrata-Monde.fr</p>
    </div>

    <div class="space-y-8 text-slate-700 text-sm leading-relaxed">

        <!-- Article 1 -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 1 — Présentation du site</h2>
            <p>Le site <strong>ViaFerrata-Monde.fr</strong> est un portail d'information communautaire consacré à la pratique de la via ferrata. Il recense des informations sur des parcours situés en France et dans le monde, fournies à titre purement <strong>indicatif et non contractuel</strong>.</p>
            <p class="mt-2">Les informations publiées sont issues de contributions communautaires, de sources publiques et de données partenaires. Elles ne constituent en aucun cas une garantie de l'état, de l'accessibilité ou de la sécurité des parcours référencés.</p>
        </section>

        <!-- Article 2 — Décharge de responsabilité -->
        <section class="bg-red-50 rounded-2xl p-6 border border-red-200">
            <h2 class="text-lg font-bold text-red-800 mb-3">⚠️ Article 2 — Décharge de responsabilité — À lire impérativement</h2>

            <div class="space-y-3">
                <p><strong class="text-red-700">ViaFerrata-Monde.fr est un site d'information uniquement.</strong> Les informations présentées sur ce site sont données à titre indicatif et ne sauraient engager la responsabilité des éditeurs du site.</p>

                <p>La pratique de la via ferrata est une <strong>activité sportive de pleine nature comportant des risques sérieux</strong>, notamment de chutes, de blessures graves, voire mortelles. En accédant aux informations de ce site et en pratiquant la via ferrata, l'utilisateur reconnaît :</p>

                <ul class="list-disc pl-5 space-y-1.5">
                    <li>Pratiquer sous son entière et exclusive responsabilité</li>
                    <li>Être en possession de l'équipement homologué requis (baudrier, longe via ferrata amortissante, casque, gants)</li>
                    <li>Avoir les compétences et le niveau physique adaptés au parcours choisi</li>
                    <li>Avoir consulté les informations les plus récentes auprès des <strong>offices de tourisme locaux</strong> et/ou des gestionnaires de parcours avant tout départ</li>
                    <li>Avoir vérifié les conditions météorologiques et l'état du terrain</li>
                </ul>

                <p class="font-semibold text-red-700">En aucun cas ViaFerrata-Monde.fr, ses éditeurs, contributeurs ou partenaires ne pourront être tenus responsables de tout accident, blessure, décès, dommage matériel ou préjudice de quelque nature que ce soit survenu lors de la pratique de la via ferrata, directement ou indirectement en lien avec les informations publiées sur ce site.</p>
            </div>
        </section>

        <!-- Article 3 -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 3 — Obligation de vérification</h2>
            <p>Avant toute sortie en via ferrata, l'utilisateur s'engage à <strong>vérifier impérativement</strong> :</p>
            <ul class="list-disc pl-5 mt-2 space-y-1.5">
                <li>L'état d'ouverture du parcours auprès de <strong>l'office de tourisme local</strong> ou du gestionnaire du site</li>
                <li>Les conditions météorologiques prévues sur place</li>
                <li>Les éventuelles restrictions ou fermetures temporaires (travaux, risques naturels, faune protégée…)</li>
                <li>L'accessibilité du parking et du départ de voie</li>
            </ul>
            <p class="mt-3 text-slate-500 text-xs italic">Les informations disponibles sur ViaFerrata-Monde.fr peuvent être incomplètes, erronées ou obsolètes. Elles ne remplacent pas une information officielle et à jour.</p>
        </section>

        <!-- Article 4 -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 4 — Contenu communautaire</h2>
            <p>Les avis, commentaires, notes et photos publiés sur ce site sont le fruit de contributions d'utilisateurs. ViaFerrata-Monde.fr ne garantit ni l'exactitude, ni l'actualité, ni la fiabilité de ces contenus. Tout contenu inapproprié peut être signalé via le formulaire de contact.</p>
        </section>

        <!-- Article 5 — Cookies -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 5 — Cookies et données</h2>
            <p>Ce site utilise des cookies et données de session pour :</p>
            <ul class="list-disc pl-5 mt-2 space-y-1">
                <li>Maintenir votre connexion à votre compte (cookie de session)</li>
                <li>Mémoriser vos préférences de navigation (avertissements, consentement cookies)</li>
                <li>Protéger les formulaires contre les soumissions automatiques (captcha Cloudflare Turnstile)</li>
            </ul>
            <p class="mt-3">Aucune donnée personnelle n'est vendue à des tiers. Les cookies strictement nécessaires ne requièrent pas de consentement préalable.</p>
            <p class="mt-2">Vous pouvez à tout moment refuser les cookies non essentiels via la bannière de consentement présente sur le site, ou paramétrer votre navigateur pour bloquer les cookies.</p>
        </section>

        <!-- Article 6 -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 6 — Propriété intellectuelle</h2>
            <p>L'ensemble du contenu éditorial (textes, design, logo) de ViaFerrata-Monde.fr est protégé par le droit d'auteur. Toute reproduction, même partielle, est interdite sans autorisation expresse. Les photos soumises par les utilisateurs restent leur propriété, mais ils accordent au site un droit d'affichage.</p>
        </section>

        <!-- Article 7 -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Article 7 — Contact</h2>
            <p>Pour toute question relative aux présentes CGU, vous pouvez nous contacter via le <a href="<?= BASE_URL ?>/contact" class="text-brand-600 hover:underline font-medium">formulaire de contact</a> ou par email à : <a href="mailto:<?= ADMIN_EMAIL ?>" class="text-brand-600 hover:underline"><?= ADMIN_EMAIL ?></a>.</p>
        </section>

        <p class="text-xs text-slate-400 text-center">En utilisant ce site, vous acceptez l'intégralité des présentes Conditions Générales d'Utilisation.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
