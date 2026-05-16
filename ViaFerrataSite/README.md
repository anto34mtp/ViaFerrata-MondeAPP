# ViaFerrata-Monde.fr — Site Web

Plateforme communautaire de répertoire mondial de vias ferratas.  
Stack : **PHP 8+ natif · MySQL · Tailwind CSS CDN · Leaflet.js**  
Pas de framework, pas de Composer, pas de Node.js.

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Architecture technique](#architecture-technique)
3. [Prérequis](#prérequis)
4. [Installation locale](#installation-locale)
5. [Configuration (.env)](#configuration-env)
6. [Base de données](#base-de-données)
7. [Migrations SQL](#migrations-sql)
8. [Structure des fichiers](#structure-des-fichiers)
9. [Rôles utilisateurs](#rôles-utilisateurs)
10. [Panneau d'administration](#panneau-dadministration)
11. [i18n — Multilingue](#i18n--multilingue)
12. [Traduction automatique (DeepL / MyMemory)](#traduction-automatique-deepl--mymemory)
13. [Road Trip Planner](#road-trip-planner)
14. [API endpoints](#api-endpoints)
15. [Mise à jour de l'application](#mise-à-jour-de-lapplication)
16. [Déploiement en production](#déploiement-en-production)
17. [Cron jobs](#cron-jobs)
18. [Sécurité](#sécurité)

---

## Fonctionnalités

### Catalogue de vias ferratas
- Recherche et filtres : nom, département, difficulté, note
- Fiches détaillées : GPS, carte Leaflet, difficulté, longueur, dénivelé, accès, statut d'ouverture
- Gestion des statuts saisonniers : ouvert / fermé / inconnu avec motif de fermeture
- Catalogue mondial (France + international) avec regroupement par pays/département

### Comptes utilisateurs
- Inscription, connexion (nom d'utilisateur ou e-mail), activation par e-mail
- Rôles : **membre · modérateur · administrateur**
- Protection CSRF sur tous les formulaires POST
- Anti-spam Cloudflare Turnstile

### Espace personnel (Dashboard)
- **Favoris** : marquer une via "à faire" ou "faite"
- **Carnet de sorties** : journal personnel de randonnées (date, conditions, compagnons, notes)
- **Road Trips** : voir et gérer ses voyages planifiés
- **Photos** : gérer ses photos soumises
- **Commentaires** : voir ses commentaires

### Notation et avis
- Notation triple : note générale, beauté, difficulté
- Système anti-doublon (hash IP + User-Agent)
- Agrégat des notes (vue SQL `via_ratings_summary`)

### Commentaires et photos
- Commentaires avec réponses imbriquées (threading)
- Limite : 3 photos par visiteur/via (JPG, PNG, WebP, AVIF, GIF, max 20 MB)
- Modération photos et commentaires par les modérateurs

### Soumission communautaire
- Formulaire de proposition de nouvelle via
- File d'approbation dans le panneau admin

### Road Trip Planner
- Création de voyages multi-jours
- Ajout de vias par jour avec glisser-déposer (SortableJS)
- Carte Leaflet affichant l'itinéraire
- Partage du trip : par utilisateur du site ou par lien e-mail tokenisé
- Révocation d'accès individuelle

### Multilingue (FR / EN / DE / ES)
- Fichiers de traduction statiques (`lang/fr|en|de|es.php`)
- Traduction des contenus DB (noms, descriptions) via DeepL Free + MyMemory (fallback)
- Cache des traductions en base (`via_translations`)
- Auto-traduction des soumissions utilisateurs vers le français avant stockage

### API mobile
- Endpoints JSON dans `api/mobile.php` et `index.php`
- Auth basée sur JWT (`classes/JWT.class.php`)

---

## Architecture technique

```
index.php               ← Front controller / routeur
config/
  config.php            ← Chargement .env, helpers (escape, redirect, setFlash)
  .envExemple           ← Template de configuration
classes/                ← Logique métier (18 classes PHP)
views/                  ← Templates publics (14 vues)
views/admin/            ← Panneau d'administration (9 fichiers)
includes/               ← Header / footer partagés
lang/                   ← Fichiers i18n (fr, en, de, es)
assets/                 ← Images statiques
uploads/photos/         ← Photos uploadées par les utilisateurs
sql/                    ← Migrations SQL
api/
  mobile.php            ← Endpoints API mobile
cron_status.php         ← Mise à jour automatique des statuts saisonniers
```

Patron : **Front Controller + MVC-inspired** sans framework.

---

## Prérequis

| Composant | Version minimum |
|-----------|----------------|
| PHP       | 8.0+           |
| MySQL     | 5.7+ / MariaDB 10.4+ |
| Apache    | 2.4+ avec `mod_rewrite` |
| Extension PHP | `pdo_mysql`, `mbstring`, `gd` ou `imagick` |

---

## Installation locale

```bash
# 1. Cloner le repo
git clone https://github.com/votre-user/viaferrata-monde-site.git
cd viaferrata-monde-site

# 2. Copier et remplir le fichier de configuration
cp config/.envExemple config/.env
# Éditer config/.env avec vos valeurs (voir section ci-dessous)

# 3. Créer la base de données MySQL
mysql -u root -p -e "CREATE DATABASE viaferrata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Importer le schéma principal
mysql -u root -p viaferrata < sql/schema.sql

# 5. Exécuter les migrations
mysql -u root -p viaferrata < sql/i18n_migration.sql
mysql -u root -p viaferrata < sql/road_trip_migration.sql

# 6. Configurer le vhost Apache (ou utiliser le .htaccess existant)
# DocumentRoot → dossier du projet
# AllowOverride All

# 7. Créer le dossier d'uploads et lui donner les droits
mkdir -p uploads/photos
chmod 755 uploads/photos
```

Accéder à `http://localhost/` — le front controller prend en charge toutes les routes.

---

## Configuration (.env)

Fichier à créer dans `config/.env` (modèle : `config/.envExemple`) :

```env
# Environnement
ENVIRONMENT=development         # production | development

# Base de données
DB_HOST=localhost
DB_NAME=viaferrata
DB_USER=root
DB_PASS=votre_mot_de_passe

# Application
BASE_URL=http://localhost
SECRET_KEY=changez_moi_en_prod  # Clé secrète pour sessions/tokens

# Contact
ADMIN_EMAIL=admin@example.com

# Anti-spam Cloudflare Turnstile
TURNSTILE_SITE_KEY=votre_cle_site
TURNSTILE_SECRET_KEY=votre_cle_secrete

# Traduction automatique (optionnel)
DEEPL_API_KEY=votre_cle_deepl_free  # gratuit jusqu'à 500 000 chars/mois
```

> **Note :** Sans `DEEPL_API_KEY`, le fallback MyMemory (gratuit, sans clé) est utilisé automatiquement.

---

## Base de données

### Tables principales

| Table | Description |
|-------|-------------|
| `vias` | Catalogue : nom, slug, GPS, difficulté, statut, pays, département |
| `departments` | Départements français (code + nom) |
| `users` | Comptes (username, email, hash mot de passe, rôle, activation) |
| `favorites` | Favoris utilisateurs (`status` : `to_do` / `done`) |
| `logbook_entries` | Journal de sorties personnelles |
| `ratings` | Notes utilisateurs (général, beauté, difficulté) avec dédup IP+UA |
| `via_ratings_summary` | Vue agrégée des moyennes de notes |
| `comments` | Commentaires avec threading (`parent_id`) |
| `user_photos` | Photos uploadées avec statut de modération |
| `via_submissions` | Propositions en attente d'approbation |
| `road_trips` | Plans de voyage (nom, dates, nb_jours) |
| `road_trip_vias` | Contenu des trips (jour, position, notes) |
| `road_trip_shares` | Partages (utilisateur / e-mail + token d'invitation) |
| `via_translations` | Cache des traductions DeepL/MyMemory |

---

## Migrations SQL

Chaque migration est un fichier SQL **idempotent** (peut être ré-exécuté sans erreur) :

| Fichier | Contenu |
|---------|---------|
| `sql/i18n_migration.sql` | Création de la table `via_translations` pour le cache de traduction |
| `sql/road_trip_migration.sql` | Tables `road_trips`, `road_trip_vias`, `road_trip_shares` |

```bash
# Exécuter une migration
mysql -u root -p viaferrata < sql/i18n_migration.sql
mysql -u root -p viaferrata < sql/road_trip_migration.sql
```

---

## Structure des fichiers

```
ViaFerrataSite/
├── .htaccess                    # Règles de réécriture Apache
├── index.php                    # Front controller (routeur)
├── cron_status.php              # Mise à jour statuts saisonniers (cron)
├── config/
│   ├── config.php               # Configuration + helpers globaux
│   └── .envExemple              # Template .env
├── classes/
│   ├── Auth.class.php           # Sessions, vérification des rôles, CSRF
│   ├── Database.class.php       # Wrapper PDO MySQL
│   ├── User.class.php           # CRUD utilisateurs, rôles, activation
│   ├── ViaFerrata.class.php     # Requêtes catalogue vias
│   ├── Rating.class.php         # Gestion des notes
│   ├── Comment.class.php        # Commentaires + réponses
│   ├── Photo.class.php          # Upload et validation photos
│   ├── Favorite.class.php       # Favoris utilisateurs
│   ├── Logbook.class.php        # Carnet de sorties
│   ├── RoadTrip.class.php       # Planificateur de voyages
│   ├── ViaSubmission.class.php  # Soumissions communautaires
│   ├── Department.class.php     # Données départements
│   ├── Translator.class.php     # Traduction automatique DeepL/MyMemory
│   ├── Lang.class.php           # Gestion du changement de langue
│   ├── JWT.class.php            # Tokens JWT (API mobile)
│   ├── Captcha.class.php        # Cloudflare Turnstile
│   ├── HtmlSanitizer.class.php  # Protection XSS
│   └── DeviceDetector.class.php # Détection device/navigateur
├── views/
│   ├── home.php                 # Page d'accueil
│   ├── via_list.php             # Catalogue filtrable
│   ├── via_detail.php           # Fiche détaillée d'une via
│   ├── country_list.php         # Vias par département (France)
│   ├── monde.php                # Carte mondiale
│   ├── pays_list.php            # Vias par pays
│   ├── dashboard.php            # Espace personnel (5 onglets)
│   ├── road_trip.php            # Planificateur de voyage
│   ├── road_trip_invite.php     # Invitation partagée (token)
│   ├── submit_via.php           # Soumettre une nouvelle via
│   ├── login.php                # Connexion
│   ├── register.php             # Inscription
│   ├── contact.php              # Formulaire de contact
│   └── cgu.php                  # Conditions générales d'utilisation
├── views/admin/
│   ├── index.php                # Tableau de bord admin (stats)
│   ├── vias.php                 # Gestion du catalogue (approbation, fermetures)
│   ├── comments.php             # Modération des commentaires
│   ├── photos.php               # Modération des photos
│   ├── submissions.php          # Propositions utilisateurs
│   └── users.php                # Gestion des comptes (admin only)
├── includes/
│   ├── header.php               # Navigation publique
│   └── footer.php               # Pied de page public
├── lang/
│   ├── fr.php                   # Traductions françaises (référence)
│   ├── en.php                   # Traductions anglaises
│   ├── de.php                   # Traductions allemandes
│   └── es.php                   # Traductions espagnoles
├── api/
│   └── mobile.php               # Endpoints API mobile (JWT auth)
├── sql/
│   ├── i18n_migration.sql       # Migration table cache traductions
│   └── road_trip_migration.sql  # Migration tables road trips
├── assets/
│   └── images/                  # Logo et images par défaut
└── uploads/
    └── photos/                  # Photos uploadées (gitignore)
```

---

## Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| `member` | Espace perso, notation, commentaires, photos, favoris, logbook, road trips |
| `moderator` | + Modération commentaires, photos, soumissions, gestion vias (statuts, fermetures) |
| `admin` | + Gestion des utilisateurs (rôles, activation, suppression) |

---

## Panneau d'administration

Accès : `/admin` (rôle modérateur ou administrateur requis)

| Section | URL | Description |
|---------|-----|-------------|
| Tableau de bord | `/admin` | Statistiques globales, compteurs en attente |
| Vias | `/admin/vias` | Approbation, modification, statuts saisonniers |
| Commentaires | `/admin/comments` | Approbation / rejet / suppression en masse |
| Photos | `/admin/photos` | Grille de modération |
| Soumissions | `/admin/submissions` | Propositions communautaires à valider |
| Utilisateurs | `/admin/users` | Gestion des rôles, activation (admin seulement) |

---

## i18n — Multilingue

La langue se change via `?lang=XX` (valeurs : `fr`, `en`, `de`, `es`).  
Elle est persistée en **session** et **cookie** (30 jours).

**Utilisation dans les vues :**
```php
<?= t('nav.catalog') ?>
```

**Ajouter une clé de traduction :**
1. Ajouter la clé dans `lang/fr.php` (référence)
2. Répliquer dans `lang/en.php`, `lang/de.php`, `lang/es.php`

---

## Traduction automatique (DeepL / MyMemory)

Les noms et descriptions des vias sont stockés en **français** en base.  
Quand un utilisateur consulte le site en EN/DE/ES :
1. `Translator::getViaTranslation()` cherche dans le cache `via_translations`
2. Si absent → appel DeepL Free (si `DEEPL_API_KEY` défini) ou MyMemory (fallback)
3. Résultat mis en cache en base pour les requêtes suivantes

Les soumissions utilisateurs sont **auto-traduites vers le français** avant stockage.

---

## Road Trip Planner

- Création : `POST /api/trip/create`
- Ajout d'une via dans un jour : bouton "Ajouter au road trip" depuis la fiche via
- Réorganisation par glisser-déposer (SortableJS) → `POST /api/trip/reorder`
- Carte Leaflet mise à jour en temps réel avec l'itinéraire
- Partage :
  - Par utilisateur du site : `POST /api/trip/share` → accès immédiat
  - Par e-mail : génère un token d'invitation → lien `/road-trip/invite/{token}`
- Révoquer un accès : `POST /api/trip/unshare`

---

## API endpoints

### Road Trip (authentification session requise)

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/trip/create` | POST | Créer un trip |
| `/api/trip/update` | POST | Modifier nom/dates |
| `/api/trip/delete` | POST | Supprimer un trip |
| `/api/trip/add-via` | POST | Ajouter une via à un jour |
| `/api/trip/remove-via` | POST | Retirer une via |
| `/api/trip/move-via` | POST | Déplacer vers un autre jour |
| `/api/trip/reorder` | POST | Réordonner (drag & drop) |
| `/api/trip/share` | POST | Partager (utilisateur / e-mail) |
| `/api/trip/unshare` | POST | Révoquer un accès |
| `/api/trip/search-users` | GET | Rechercher un utilisateur (AJAX) |

### Logbook & Favoris

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/logbook/save` | POST | Enregistrer une sortie |
| `/api/logbook/delete` | POST | Supprimer une sortie |
| `/api/favorite/remove` | POST | Retirer un favori |
| `/api/favorite/done` | POST | Marquer favori comme "faite" |

### API mobile (`api/mobile.php` — auth JWT)

Voir `recap_fonctionalite.md` pour le détail complet des endpoints mobile.

---

## Mise à jour de l'application

### Mise à jour simple (fichiers PHP uniquement)

```bash
# Tirer les dernières modifications
git pull origin main

# Si des fichiers de cache OPcache existent et posent problème
# Appeler flush.php si disponible, ou redémarrer PHP-FPM
```

### Mise à jour avec migration SQL

```bash
git pull origin main

# Identifier les nouvelles migrations (fichiers sql/*.sql)
# Les exécuter dans l'ordre indiqué dans le changelog / commit message

mysql -u root -p viaferrata < sql/nouvelle_migration.sql
```

### Vérifications post-déploiement

- [ ] Vérifier les logs Apache/PHP (`/var/log/apache2/error.log`)
- [ ] Tester la connexion / inscription
- [ ] Tester l'espace admin
- [ ] Vérifier que les photos s'uploadent correctement
- [ ] Vérifier le sélecteur de langue

---

## Déploiement en production

```bash
# 1. Transférer les fichiers (FTP, rsync, git pull)
rsync -avz --exclude='.env' --exclude='uploads/' ./ user@serveur:/var/www/viaferrata/

# 2. S'assurer que .env est configuré sur le serveur
ssh user@serveur "nano /var/www/viaferrata/config/.env"

# 3. Permissions sur le dossier uploads
ssh user@serveur "chmod 755 /var/www/viaferrata/uploads/photos"

# 4. Exécuter les migrations si nécessaires
ssh user@serveur "mysql -u USER -p viaferrata < /var/www/viaferrata/sql/nouvelle_migration.sql"

# 5. Vérifier la configuration Apache (mod_rewrite activé, AllowOverride All)
```

### Variables d'environnement en production

```env
ENVIRONMENT=production
BASE_URL=https://viaferrata-monde.fr
DB_HOST=localhost
DB_NAME=...
```

> Le fichier `.env` ne doit **jamais** être commité dans le repo (il est dans `.gitignore`).

---

## Cron jobs

### Mise à jour automatique des statuts saisonniers

Le fichier `cron_status.php` met à jour les statuts des vias selon les périodes d'ouverture définies.

**Configuration cron (sur le serveur) :**
```cron
# Exécution quotidienne à 6h00
0 6 * * * /usr/bin/php /var/www/viaferrata/cron_status.php >> /var/log/viaferrata_cron.log 2>&1
```

Ou déclenchement par URL (si accès HTTP protégé) :
```
https://viaferrata-monde.fr/cron_status.php?key=VOTRE_CLE_SECRETE
```

---

## Sécurité

| Mesure | Implémentation |
|--------|---------------|
| Protection XSS | `HtmlSanitizer::clean()` sur tout le contenu utilisateur |
| Protection CSRF | Token CSRF vérifié sur tous les formulaires POST |
| Injection SQL | PDO avec requêtes préparées exclusivement |
| Spam | Cloudflare Turnstile sur inscription, soumission, contact |
| Mots de passe | `password_hash()` / `password_verify()` (bcrypt) |
| Uploads | Validation MIME type + extension + taille (max 20 MB) |
| Auth admin | Vérification de rôle à chaque requête admin |
| JWT | Clé secrète via `SECRET_KEY` dans `.env` |

---

## Licence

Projet privé — tous droits réservés.  
Contact : anthony.abonnements34@gmail.com
