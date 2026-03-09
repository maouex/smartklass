# Plan : SmartKlass v2 — Expansion Multi-École / Multi-Professeur

> **Statut** : En attente — à reprendre quand PC perso disponible (pas de droits admin sur le poste actuel)
> **Date de rédaction** : 2026-03-09

---

## Problème à résoudre

SmartKlass est actuellement mono-professeur / mono-école :
- Un seul mot de passe partagé pour le prof (stocké en clair dans `config`)
- Aucune isolation des données entre utilisateurs
- Impossible d'ouvrir la plateforme à d'autres profs

L'objectif est de transformer SmartKlass en plateforme multi-prof / multi-école où **chaque professeur gère son espace de façon totalement autonome**.

---

## Décisions d'architecture (déjà prises)

| Question | Choix retenu |
|---|---|
| Modèle écoles | **Professeur autonome** — l'école = champ texte libre, pas d'entité BDD |
| Login élèves | **2 étapes** : code classe (join code) → identifiant habituel |
| Super-admin | **Non** — inscription libre, pas de validation centrale |
| Auth | **JWT HS256** natif PHP (sans librairie), **bcrypt** pour les mots de passe |
| Stockage token | `sessionStorage` prof, `localStorage` élève |

---

## Plan d'implémentation en 5 phases

### Phase 1 — Base de données (`sql/migration-v9.sql` à créer)

**Nouvelle table `teachers` :**
```sql
CREATE TABLE teachers (
    id               VARCHAR(20)  PRIMARY KEY,
    email            VARCHAR(255) UNIQUE NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    name             VARCHAR(100) NOT NULL,
    school_name      VARCHAR(150) DEFAULT '',
    join_code        VARCHAR(12)  UNIQUE NOT NULL,  -- ex: "DURAND-A7F2", distribué aux élèves
    vapid_public_key  TEXT DEFAULT NULL,
    vapid_private_key TEXT DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Colonnes `teacher_id` à ajouter sur :** `subjects`, `classes`, `courses`, `activities`, `notification_history`, `live_sessions`, `push_subscriptions`

**Identifiants élèves — unicité composite :**
```sql
ALTER TABLE students DROP INDEX identifier;
ALTER TABLE students ADD COLUMN teacher_id VARCHAR(20) NOT NULL AFTER class_id;
ALTER TABLE students ADD UNIQUE KEY uq_student_ident_teacher (identifier, teacher_id);
ALTER TABLE students ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER password;
```

---

### Phase 2 — API (`api/index.php` + `api/config.example.php`)

**Fonctions JWT à ajouter dans `config.php` :**
```php
define('JWT_SECRET', 'clé-256-bits-à-générer');

function jwtEncode(array $payload): string { /* base64url + HMAC-SHA256 */ }
function jwtDecode(string $token): ?array  { /* validation sig + exp */ }
```

**Middleware auth (en haut du routeur) :**
```php
$teacherId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($authHeader, 'Bearer ')) {
    $payload = jwtDecode(substr($authHeader, 7));
    if ($payload) $teacherId = $payload['teacherId'];
}

function requireTeacher(): string {
    global $teacherId;
    if (!$teacherId) jsonResponse(['error' => 'Non authentifié'], 401);
    return $teacherId;
}
```

**Nouveaux endpoints :**

| Méthode | Route | Rôle |
|---|---|---|
| `POST` | `/auth/register` | Inscription prof → retourne JWT + join_code |
| `POST` | `/auth` type=`teacher` | Login prof email+mdp → JWT |
| `POST` | `/auth` type=`resolve-join-code` | Résoudre un join_code → nom prof/école |
| `POST` | `/auth` type=`student` | Login élève (joinCode + identifier + password) |
| `GET` | `/teachers/me` | Profil du prof connecté |
| `PUT` | `/teachers/me` | Modifier nom, école, mot de passe |
| `GET` | `/student-data` | Données scoped élève (remplace GET /data pour les élèves) |

**Endpoints existants :** ajouter `requireTeacher()` + filtrage `AND teacher_id = $tid` partout.

---

### Phase 3 — Frontend (`public/index.html`)

**Objet `api` — injection du token :**
```js
const api = {
  _headers() {
    const token = sessionStorage.getItem('sk_teacher_token');
    const h = { 'Content-Type': 'application/json' };
    if (token) h['Authorization'] = `Bearer ${token}`;
    return h;
  },
  // get/post/put/del utilisent tous this._headers()
};
```

**Nouveaux composants / écrans :**
- `TeacherRegisterScreen` : nom, école (optionnel), email, mot de passe → affiche le join code après inscription
- Login prof : email + mot de passe (au lieu du mot de passe unique actuel)
- Login élève en 2 étapes :
  1. Saisir le join code → affiche "Espace de M. Durand — Lycée X"
  2. Saisir l'identifiant habituel
  3. Saisir le mot de passe
- Section "Mon compte" dans le dashboard prof : affiche le join code, permet de modifier nom/école/mdp

---

### Phase 4 — Migration des données existantes (`api/migrate-v9.php` à créer)

Script PHP one-shot, accessible via navigateur, qui :
1. Lit `teacher_name` et `teacher_password` depuis `config`
2. Crée un enregistrement dans `teachers` (hash bcrypt du mdp, génère un join_code)
3. Met à jour toutes les tables (`subjects`, `classes`, `courses`, etc.) avec ce `teacher_id`
4. Affiche les nouvelles credentials du prof
5. **Se supprime lui-même** après exécution

Les mots de passe élèves sont migrés vers bcrypt **à la première connexion** (check plaintext d'abord, puis hash et stockage dans `password_hash`).

---

### Phase 5 — Nettoyage (post-déploiement, après stabilisation)

- Supprimer la colonne `password` (plaintext) de `students`
- Supprimer `teacher_password`, `teacher_name`, `vapid_public_key/private_key` de `config`
- Supprimer `api/migrate-v9.php` du serveur

---

## Environnement de test (à faire avant de coder)

Pas de droits admin sur le poste actuel → **impossible d'installer XAMPP localement**.

**Solution retenue : staging sur O2switch**
1. Créer une BDD `smartklass_v2` dans cPanel
2. Créer un sous-domaine `beta.smartklass.fr` pointant vers `/beta.smartklass.fr/`
3. Configurer un déploiement git qui tire la branche `feature/v2` vers ce dossier
4. Créer un `api/config.php` sur le staging qui pointe vers `smartklass_v2`

Cycle de travail : coder → `git push` → O2switch déploie sur `beta.smartklass.fr` → tester dans le navigateur.

---

## Fichiers à créer / modifier

| Fichier | Action |
|---|---|
| `sql/migration-v9.sql` | **Créer** |
| `api/migrate-v9.php` | **Créer** (script one-shot, supprimer après usage) |
| `api/config.example.php` | **Modifier** (ajouter JWT_SECRET + fonctions JWT) |
| `api/index.php` | **Modifier** (middleware auth, nouveaux endpoints, filtrage teacher_id) |
| `public/index.html` | **Modifier** (RegisterScreen, login email/mdp, login élève 2 étapes, token) |
