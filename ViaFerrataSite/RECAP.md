# RECAP — ViaFerrata-Monde.fr

Dernière mise à jour : 2026-04-28

---

## 1. Projet

Site PHP de référencement de via ferrata (France + monde).  
URL production : **https://viaferrata-monde.fr**  
Dossier local : `C:\claude\ViaFerrataSite\`  
Upload via **FileZilla** vers le bon compte FTP (`viaferrata-monde.fr`, pas `v2`).

---

## 2. Stack technique

- PHP 8+ (pas de framework)
- MySQL
- Tailwind CSS v3 (CDN)
- Leaflet.js (cartes interactives)
- Front controller : `index.php` → routeur via `?url=segment`
- Pas de Composer, tout en natif

---

## 3. Architecture des fichiers

```
ViaFerrataSite/
├── index.php                  ← Routeur principal (front controller)
├── config/
│   ├── config.php             ← BASE_URL, ROOT_PATH, escape(), setFlash(), redirect(), Auth, Database
│   └── database.php
├── classes/
│   ├── Auth.class.php         ← isAdmin(), isModerator(), getUserId(), getUsername(), verifyCsrfToken()
│   ├── ViaFerrata.class.php   ← getBySlug() retourne v.* + dept + ratings
│   └── ...
├── views/
│   ├── home.php
│   ├── via_list.php           ← Liste publique avec filtre fermeture (rouge)
│   ├── via_detail.php         ← Fiche détaillée + bannière fermeture
│   ├── country_list.php
│   └── admin/
│       ├── _common.php        ← Auth, PDO, CSRF, ALTER TABLE, auto-status, badges nav
│       ├── _header.php        ← HTML minimal admin (PAS le header public)
│       ├── _footer.php        ← </body></html>
│       ├── _nav.php           ← Topbar + sidebar + nav mobile
│       ├── _nav_end.php       ← Fermeture du layout flex
│       ├── index.php          ← Dashboard
│       ├── vias.php           ← Gestion via ferrata (tableau complet)
│       ├── comments.php       ← Modération commentaires
│       ├── photos.php         ← Modération photos
│       ├── submissions.php    ← Propositions utilisateurs
│       └── users.php          ← Gestion utilisateurs (admin only)
├── includes/
│   ├── header.php             ← Header PUBLIC (ne pas utiliser dans admin)
│   └── footer.php
├── sql/
│   ├── new_vias_umap.sql
│   ├── new_vias_france.sql
│   ├── new_vias_franceviaferrata.sql
│   ├── new_vias_other_sources.sql
│   ├── all_vias_merged.sql    ← 188 vias fusionnées (avec doublons)
│   └── all_vias_final.sql     ← 110 vias à importer (sans doublons site existant)
├── cron_status.php            ← Endpoint cron mise à jour automatique statuts
├── flush.php                  ← Vider OPcache PHP (supprimer après usage)
├── check_duplicates.js        ← Script Node.js déduplication
└── merge_vias.js              ← Script Node.js fusion SQL
```

---

## 4. Routeur (index.php)

```
/              → views/home.php
/france        → views/country_list.php
/france/{slug} → views/via_detail.php
/via           → views/via_list.php
/via/{slug}    → views/via_detail.php
/monde         → views/monde.php
/admin         → views/admin/index.php
/admin/vias    → views/admin/vias.php
/admin/comments    → views/admin/comments.php
/admin/photos      → views/admin/photos.php
/admin/submissions → views/admin/submissions.php
/admin/users       → views/admin/users.php
/connexion     → views/login.php
/inscription   → views/register.php
/mon-espace    → views/dashboard.php
/proposer      → views/submit_via.php
```

---

## 5. Base de données — table `vias`

### Colonnes importantes
| Colonne | Type | Notes |
|---------|------|-------|
| `id` | INT AUTO_INCREMENT | |
| `name` | VARCHAR | Nom de la via |
| `slug` | VARCHAR UNIQUE | URL-friendly |
| `code_pays` | VARCHAR(2) | 'FR', 'ES', etc. |
| `department_id` | VARCHAR | '38', '2A', etc. |
| `location` | VARCHAR | Ville/lieu |
| `difficulty` | INT | 1-10 |
| `difficulty_rating` | INT | Alternative |
| `opening_status` | ENUM | `ouvert` / `ferme` / `ferme_definitif` |
| `opening_period` | VARCHAR | "Juin à octobre" |
| `closure_reason` | VARCHAR(500) | **Ajouté automatiquement** |
| `closure_end_date` | DATE | **Ajouté automatiquement** |
| `is_approved` | TINYINT(1) | 0=en attente, 1=publié |
| `is_active` | TINYINT(1) | **Ajouté automatiquement**, 0=masqué |
| `approved_by` | INT | **Ajouté automatiquement** |
| `approved_at` | DATETIME | **Ajouté automatiquement** |
| `parent_id` | INT | Pour multi-parcours |
| `part_number` | INT | Numéro de partie |
| `latitude` | DECIMAL | GPS |
| `longitude` | DECIMAL | GPS |

> Les colonnes `closure_reason`, `closure_end_date`, `approved_by`, `approved_at`, `is_active` sont ajoutées automatiquement par `_common.php` via `ALTER TABLE ... IF NOT EXISTS` (try/catch silencieux).

### Requête export noms en CSV
```sql
SELECT name FROM vias ORDER BY name ASC;
```
Puis Exporter → CSV dans phpMyAdmin.

---

## 6. Panel Admin

### Accès
- URL : `/admin`
- Rôles autorisés : `modo`, `admin`
- `users.php` : admin uniquement

### Architecture
Chaque page admin inclut dans cet ordre :
1. `_common.php` — auth + PDO + CSRF + colonnes DB + auto-status + badges
2. `_header.php` — DOCTYPE + Tailwind (PAS le header public)
3. `_nav.php` — topbar + sidebar + nav mobile
4. *Contenu de la page*
5. `_nav_end.php` — fermeture layout
6. `_footer.php` — `</body></html>`

### Pages et fonctionnalités

| Page | URL | Fonctionnalités |
|------|-----|-----------------|
| Dashboard | `/admin` | Stats, menu rapide, vias récentes, notif auto-status |
| Via ferrata | `/admin/vias` | Tableau 50/page, filtres, approbation, fermeture modale, suppression |
| Commentaires | `/admin/comments` | En attente/approuvés/tous, approuver, supprimer, tout approuver |
| Photos | `/admin/photos` | Grille, approuver, supprimer, tout approuver |
| Propositions | `/admin/submissions` | Groupées ou individuelles, publier, rejeter |
| Utilisateurs | `/admin/users` | Changer rôle, activer/désactiver, supprimer |

### Filtres page Vias
- `?filter=all` — toutes
- `?filter=pending` — en attente d'approbation
- `?filter=approved` — publiées
- `?filter=closed` — fermées
- `?filter=no_gps` — sans coordonnées GPS
- `?q=texte` — recherche nom/lieu/slug/dept

---

## 7. Gestion des fermetures

### Statuts
- `ouvert` — accessible
- `ferme` — fermée temporairement (avec raison + date de réouverture optionnelles)
- `ferme_definitif` — fermée définitivement

### Affichage public
- **`via_list.php`** : carte rouge/amber sur l'image + pill "Fermée temp./déf." dans les tags
- **`via_detail.php`** : bannière rouge (déf.) ou amber (temp.) avec raison et date

### Mise à jour automatique des statuts
Fichier : `views/admin/_common.php` + `cron_status.php`

Parsing des périodes reconnus :
- `"Juin à octobre"` → ouvert de juin à octobre
- `"Mi-juin à mi-octobre"` → idem
- `"Toute l'année"` / `"Toute l'année (selon météo)"` → toujours ouvert
- `"Juillet à septembre"` → ouvert juillet-septembre
- `"Avril à novembre"` → ouvert avril-novembre

**Règles** :
- Ne touche jamais `ferme_definitif`
- Ne touche pas les vias avec `closure_reason` renseignée (fermeture manuelle)
- S'exécute à chaque chargement du panel admin
- Affiche une notification bleue si des vias ont changé de statut

### Cron automatique
```
# Tous les jours à 6h
0 6 * * * php /chemin/vers/cron_status.php
```
Ou via URL (définir `CRON_TOKEN` dans config.php) :
```
https://viaferrata-monde.fr/cron_status.php?token=TON_TOKEN
```

---

## 8. Import SQL des nouvelles vias

### Contexte
- Site existant : ~110 vias approuvées
- Nouvelles vias préparées : 188 au total
- Après déduplication : **110 nouvelles vias** dans `all_vias_final.sql`

### Scripts Node.js
```bash
node merge_vias.js       # Fusionne les 4 sources SQL → all_vias_merged.sql
node check_duplicates.js # Déduplique vs site existant → all_vias_final.sql
```

### Caractéristiques du SQL généré
- `INSERT IGNORE INTO vias` (ignore si slug déjà présent)
- `is_approved = 0` (toutes à valider avant publication)
- Aucun commentaire SQL
- Apostrophes correctement échappées (`l''année`)

---

## 9. Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| `member` | Espace perso, noter, commenter, proposer |
| `modo` | Panel admin (toutes pages sauf users) |
| `admin` | Panel admin complet + supprimer + gérer utilisateurs |

---

## 10. Points d'attention

- **Ne jamais utiliser `header.php` du site public dans les pages admin** — utiliser `_header.php`
- **OPcache** : si les modifications ne s'affichent pas, uploader `flush.php` et visiter l'URL, puis supprimer
- **Upload FileZilla** : bien cibler le compte `viaferrata-monde.fr` (pas `v2.viaferrata`)
- **PRG pattern** : tous les POST redirigent via `setFlash()` + `redirect()` pour éviter la re-soumission
- **CSRF** : tous les formulaires POST incluent `csrf_token`
- Les `ALTER TABLE` dans `_common.php` s'exécutent à chaque chargement admin mais sont silencieux si la colonne existe déjà
