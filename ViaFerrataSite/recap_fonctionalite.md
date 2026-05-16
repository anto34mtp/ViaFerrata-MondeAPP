# ViaFerrata-Monde.fr — Récapitulatif des fonctionnalités
> Document de référence pour le développement de l'application mobile Android / iOS

---

## Stack technique actuelle (site web)

| Composant | Technologie |
|---|---|
| Back-end | PHP 8.2 natif (front controller `index.php`) |
| Base de données | MySQL / PDO |
| Front-end | Tailwind CSS (CDN), JS vanilla |
| Cartes | Leaflet.js + OpenStreetMap |
| Drag & drop | SortableJS |
| Anti-spam | Cloudflare Turnstile |
| i18n | Système maison (fichiers `lang/*.php`) |
| Emails | PHP `mail()`, HTML table-based |

---

## 1. Catalogue de via ferrata

### 1.1 Liste / Recherche
- Affichage de toutes les via ferrata actives et approuvées
- **Filtres disponibles :**
  - Nom (recherche textuelle LIKE)
  - Département (code département)
  - Difficulté min / max
  - Note moyenne minimale
- **Tris disponibles :**
  - Date de création (défaut)
  - Nom alphabétique
  - Note globale
  - Difficulté
  - Beauté
- Pagination (limit / offset)
- Navigation géographique par pays (`/monde`) et par département France (`/france`)

### 1.2 Fiche détaillée d'une via
Chaque via expose les données suivantes :

| Champ | Type | Description |
|---|---|---|
| `name` | string | Nom de la via |
| `slug` | string | Identifiant URL unique |
| `location` | string | Lieu / ville |
| `department_name` | string | Département (France) |
| `difficulty` | int (1–7+) | Niveau de difficulté |
| `duration_hours` | float | Durée en heures |
| `length_km` | float | Longueur en km |
| `altitude_min` / `altitude_max` | int | Altitudes en mètres |
| `elevation_gain` | int | Dénivelé positif en mètres |
| `approach_time` | int | Temps d'approche (minutes) |
| `return_time` | int | Temps de retour (minutes) |
| `latitude` / `longitude` | float | Coordonnées GPS |
| `google_maps_url` | string | Lien Google Maps direct |
| `opening_status` | enum | `ouvert` / `ferme` / `ferme_definitif` / `saisonnier` |
| `opening_period` | string | Période d'ouverture (ex: "Avril–Octobre") |
| `pricing` | string | `gratuit` / `payant` + détails |
| `rental_equipment_url` | string | Lien de location de matériel |
| `tourism_office_name` | string | Nom de l'office de tourisme |
| `tourism_office_phone` | string | Téléphone |
| `tourism_office_email` | string | Email |
| `description` | text | Description longue |
| `image_url` | string | Photo principale (URL) |
| `avg_general` | float | Note générale moyenne (1–10) |
| `avg_beauty` | float | Note beauté moyenne (1–10) |
| `avg_difficulty` | float | Note difficulté moyenne (1–10) |
| `avg_overall` | float | Note globale calculée |
| `total_ratings` | int | Nombre de notes |

### 1.3 Mise à jour automatique des statuts
- Un cron (`cron_status.php`) met à jour automatiquement `opening_status` selon `opening_period`
- Visible dans le dashboard admin (nombre de vias mises à jour)

---

## 2. Authentification & comptes utilisateurs

### 2.1 Inscription
- Champs : `username`, `email`, `password` (hashé bcrypt)
- Validation unicité username + email
- Connexion automatique après inscription
- Redirect optionnel post-inscription (`?redirect=/chemin`)

### 2.2 Connexion
- Login par username **ou** email
- Vérification compte actif (`is_active = 1`)
- Régénération session (protection session fixation)
- Redirect optionnel post-connexion (`?redirect=/chemin`)

### 2.3 Déconnexion
- Destruction complète de la session + cookie

### 2.4 Rôles
| Rôle | Accès |
|---|---|
| `member` | Fonctions utilisateur (favoris, carnet, road trip, notes, commentaires) |
| `modo` | Modération commentaires + photos |
| `admin` | Accès complet (+ gestion vias, utilisateurs, propositions) |

### 2.5 Sécurité
- Tokens CSRF sur tous les formulaires POST
- Protection open redirect (regex `/^\/[a-zA-Z0-9\-_\/]+$/`)

---

## 3. Espace personnel (Dashboard)

Accessible sur `/mon-espace` (authentification requise).

**Statistiques affichées :**
- Nombre total de favoris
- Nombre de sorties au carnet
- Nombre de road trips créés
- Nombre de sorties cette année

**Sections :**
- Mes favoris (aperçu)
- Mon carnet de bord (aperçu)
- Mes road trips
- Road trips partagés avec moi

---

## 4. Favoris

- Ajouter une via en favori avec statut **"À faire"** ou **"Faite"**
- Modifier le statut (toggle to_do ↔ done)
- Supprimer un favori
- Lister tous les favoris (filtrable par statut)
- Comptage total et par statut

**Données liées :** nom, slug, image, difficulté, lieu, département, notes moyennes

---

## 5. Carnet de bord (Logbook)

Journal personnel des sorties effectuées.

**Par entrée :**
| Champ | Description |
|---|---|
| `via_id` | Via concernée |
| `done_date` | Date de la sortie |
| `conditions` | Conditions météo / état voie |
| `companion` | Accompagnants |
| `notes` | Notes libres |

- Création / mise à jour (upsert par user + via)
- Suppression
- Historique trié par date décroissante
- Comptage total + comptage année en cours

---

## 6. Notation (Ratings)

- **3 critères** notés de 1 à 10 :
  - Général
  - Beauté
  - Difficulté
- Note globale = moyenne des 3 critères
- Anti-doublon par visiteur (hash SHA-256 : IP + User-Agent + cookie unique)
- Résumé agrégé dans la vue `via_ratings_summary`
- Disponible pour utilisateurs connectés ET visiteurs anonymes

---

## 7. Commentaires

- Commentaire principal par via
- **Réponses threadées** (1 niveau de nesting)
- Anti-doublon (un commentaire par visiteur/via pour les anonymes)
- Anti-spam Cloudflare Turnstile
- Modération admin (approuvé / non approuvé)
- Disponible pour utilisateurs connectés ET visiteurs anonymes (avec nom saisi)

---

## 8. Photos utilisateurs

- Upload par membres et visiteurs
- **Limite :** 3 photos par visiteur et par via
- **Formats acceptés :** JPG, PNG, WebP, AVIF, GIF
- **Taille max :** 20 MB
- Validation MIME type côté serveur
- Stockage local (`/uploads/photos/`)
- Modération admin avant publication

---

## 9. Road Trip (Planificateur)

Fonctionnalité principale de planification multi-jours.

### 9.1 Gestion des trips
- Créer un trip : nom, description, date début/fin, nombre de jours (1–30)
- Modifier les infos d'un trip
- Supprimer un trip (et toutes ses vias associées)
- Lister ses trips avec compteur de vias

### 9.2 Gestion des vias dans un trip
- Ajouter une via à un jour précis (avec notes optionnelles)
  - **Multi-ajout** : la modale reste ouverte pour ajouter plusieurs vias sans fermer
- Supprimer une via du trip
- Déplacer une via vers un autre jour
- **Réordonner les vias par drag & drop** (SortableJS)
- Les vias sont organisées par `day_number` + `position`

### 9.3 Vue du trip
- Vue par jour avec liste des vias
- **Carte interactive Leaflet** avec marqueurs par jour
- Statistiques par jour (nombre de vias, durée totale, longueur totale)
- **Bouton Google Maps par jour** : ouvre un itinéraire depuis la position actuelle vers toutes les vias GPS du jour
- Alertes si des vias n'ont pas de coordonnées GPS

### 9.4 Partage
- **Partage par username** : recherche utilisateurs en temps réel (debounce AJAX), partage direct
- **Partage par email :**
  - Si l'email correspond à un compte existant → partage direct
  - Si l'email est inconnu → génération d'un lien d'invitation tokenisé (64 hex chars) + envoi d'un email HTML
- **Email d'invitation HTML** : design moderne, carte du trip, bouton CTA, compatible Outlook (VML)
- **Lien d'invitation** : `/road-trip/invite/{token}`
  - Non connecté → affiche infos du trip + CTAs login/inscription
  - Connecté → consomme le token et redirige vers le trip
- Révoquer un accès partagé
- Lister les personnes ayant accès
- **Vue lecture seule** pour les utilisateurs partagés (pas de modification, bandeau d'information)
- Les trips partagés apparaissent dans le dashboard de l'utilisateur invité

---

## 10. Proposer une via ferrata

- Formulaire de soumission ouvert à tous
- **Support multi-parties** (via ferrata avec plusieurs secteurs groupés par token)
- Champs : nom, lieu, GPS, difficulté, durée, temps d'approche/retour, dénivelé, description, email auteur
- Soumission en statut `pending` → validation admin avant publication

---

## 11. Internationalisation (i18n)

- **4 langues :** Français (FR), Anglais (EN), Allemand (DE), Espagnol (ES)
- Changement de langue via `?lang=XX` (persiste en cookie/session)
- Traduction automatique des fiches via ferrata via `Translator::getViaTranslation()`
- Toutes les chaînes UI sont dans `lang/{code}.php`

---

## 12. Administration

Accessible sur `/admin/*` (rôle admin requis).

### Pages admin
| Page | Fonctionnalité |
|---|---|
| `/admin` | Dashboard (stats globales, activité récente) |
| `/admin/vias` | Gestion des via ferrata (CRUD, approbation, rejet) |
| `/admin/comments` | Modération des commentaires |
| `/admin/photos` | Modération des photos |
| `/admin/submissions` | Traitement des propositions de nouvelles vias |
| `/admin/users` | Gestion des utilisateurs |

### Badges de modération
- Commentaires en attente
- Photos en attente
- Propositions en attente
- Vias en attente d'approbation

---

## 13. Navigation géographique

- **Page Monde** (`/monde`) : carte Leaflet avec tous les pays ayant des vias, cliquable
- **Page Pays** (`/pays/{code}`) : liste des vias d'un pays
- **Page France** (`/france`) : liste par département avec carte interactive
- **Page Département** : liste des vias + stats

---

## 14. Pages statiques / utilitaires

| URL | Contenu |
|---|---|
| `/` | Page d'accueil : top notées, dernières ajoutées, stats globales |
| `/contact` | Formulaire de contact |
| `/cgu` | Conditions générales d'utilisation |
| `/proposer` | Formulaire de proposition de via |

---

## 15. API interne (JSON)

Toutes les actions dynamiques passent par `/api/*` avec CSRF token.

### Endpoints Road Trip (`/api/trip/*`)
| Méthode | Endpoint | Action |
|---|---|---|
| POST | `/api/trip/create` | Créer un trip |
| POST | `/api/trip/update` | Modifier un trip |
| POST | `/api/trip/delete` | Supprimer un trip |
| POST | `/api/trip/add-via` | Ajouter une via |
| POST | `/api/trip/remove-via` | Retirer une via |
| POST | `/api/trip/move-via` | Déplacer vers un autre jour |
| POST | `/api/trip/reorder` | Réordonner les vias d'un jour |
| POST | `/api/trip/share` | Partager (type: user ou email) |
| POST | `/api/trip/unshare` | Révoquer un accès |
| GET | `/api/trip/search-users` | Rechercher des membres à partager |

### Endpoints Via Ferrata (dans `via_detail.php`)
| Méthode | Action |
|---|---|
| POST `action=rate` | Soumettre une note |
| POST `action=comment` | Poster un commentaire |
| POST `action=reply` | Répondre à un commentaire |
| POST `action=photo` | Uploader une photo |
| POST `action=favorite` | Ajouter/modifier/supprimer un favori |
| POST `action=logbook` | Enregistrer une sortie carnet |

---

## 16. Modèle de données (tables principales)

```
vias                  → via ferrata (données complètes)
departments           → départements français
users                 → comptes utilisateurs
favorites             → favoris (user ↔ via, statut to_do/done)
logbook_entries       → carnet de bord personnel
ratings               → notes (général, beauté, difficulté)
via_ratings_summary   → vue agrégée des moyennes
comments              → commentaires + réponses (parent_id)
user_photos           → photos uploadées
via_submissions       → propositions de nouvelles vias
road_trips            → planificateurs multi-jours
road_trip_vias        → vias par trip (day_number, position)
road_trip_shares      → partages (shared_with, invite_email, invite_token)
```

---

## 17. Points d'attention pour l'app mobile

### Authentification mobile
- Prévoir une API **token-based** (JWT ou Bearer) au lieu des sessions PHP côté serveur
- Conserver le flow inscription / connexion (email ou username)
- Gérer le redirect post-login pour les invitations

### Fonctionnalités offline / cache
- Fiche via (données statiques) → cacheable
- Carte : tuiles OpenStreetMap téléchargeables
- Road trip en cours → persistance locale

### Permissions natif
- **GPS** : requis pour le bouton "Itinéraire Google Maps" et affichage position sur carte
- **Caméra / Galerie** : upload de photos
- **Notifications push** : optionnel (invitation road trip reçue, réponse à un commentaire)

### Fonctionnalités clés à prioriser (MVP)
1. Catalogue + recherche / filtres
2. Fiche via complète (infos + GPS + photos + notes)
3. Carte interactive
4. Authentification
5. Favoris
6. Road Trip (créer, ajouter vias, vue par jour, carte)
7. Carnet de bord

### Fonctionnalités secondaires (v2)
- Notation et commentaires
- Partage de road trip
- Upload de photos
- Navigation par département / pays
- Proposer une via

### Ce qui n'est PAS dans l'app (admin uniquement)
- Modération commentaires / photos
- Approbation des propositions
- Gestion des utilisateurs
- Dashboard admin
