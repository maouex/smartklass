# CLAUDE.md — Instructions pour Claude Code

## Contexte

SmartKlass est une plateforme d'apprentissage interactive pour des élèves de 1ère STMG, déployée sur O2switch (PHP/MySQL).

## Architecture

```
public/index.html  → Frontend React complet (Babel in-browser, fichier unique)
api/index.php      → API REST PHP (toutes les routes)
api/config.php     → Config BDD (PAS dans git, déjà sur le serveur)
api/.htaccess      → Réécriture URL pour l'API
sql/               → Schémas et migrations SQL
docs/              → Documentation
```

## Stack

- **Frontend** : React 18 via CDN + Babel standalone. Tout est dans un seul fichier HTML. Pas de build, pas de npm.
- **Backend** : PHP 8, PDO MySQL. Un seul fichier `api/index.php` qui route tout.
- **BDD** : MySQL (MariaDB sur O2switch). Tables : subjects, classes, students, courses, course_classes, activities, activity_classes, results, config.
- **Déploiement** : Git push → cPanel tire automatiquement via `.cpanel.yml`.

## Conventions

- L'API retourne du JSON. Les noms de champs JS sont en camelCase (firstName, classId, xpReward). Les noms SQL sont en snake_case (first_name, class_id, xp_reward). La conversion se fait dans `formatStudent()` et dans le endpoint `/data`.
- Les IDs sont des chaînes aléatoires de 9 caractères générées par `generateId()`.
- Le frontend communique avec l'API via `const API_BASE = '/api'` et l'objet `api` (get/post/put/del).
- Le fichier `api/config.php` n'est PAS versionné (contient les identifiants BDD). Un template `config.example.php` est fourni.

## Déploiement

Le fichier `.cpanel.yml` copie automatiquement :
- `public/` → `smartklass.fr/` (le frontend)
- `api/` → `smartklass.fr/api/` (le backend, SAUF config.php)

⚠️ Ne JAMAIS modifier `.cpanel.yml` sans comprendre l'impact.
⚠️ Le `DEPLOYPATH` dans `.cpanel.yml` doit correspondre au vrai chemin O2switch de l'utilisateur.

## Migrations SQL

Les migrations sont dans `sql/`. Elles doivent être exécutées manuellement via phpMyAdmin. Si tu crées une nouvelle migration, nomme-la `migration-v3.sql`, `migration-v4.sql`, etc.

## Types d'activités

5 types : `qcm`, `flashcards`, `truefalse`, `fillblank`, `matching`. Le champ `data` (JSON) contient la structure spécifique à chaque type.

## Matières par défaut

- `sdgn` → SDGN
- `msdgn` → MSDGN
- `marketing` → Marketing / Mercatique

Mais le prof peut en créer de nouvelles avec des IDs générés dynamiquement.

## Points d'attention

- L'élève choisit son mot de passe à la première connexion (champ `password` dans `students`, NULL = première connexion).
- Le prof peut réinitialiser un mdp élève (remet password à NULL).
- Les activités peuvent être liées à un cours via `course_id` (optionnel).
- L'import JSON d'activités passe par une étape 2 où le prof choisit matière + classes + cours.
