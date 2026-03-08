# 📚 SmartKlass

Plateforme d'apprentissage interactive pour les élèves de 1ère STMG.

## Déploiement

Le site est déployé automatiquement sur O2switch à chaque push sur `main` grâce au fichier `.cpanel.yml`.

### Première installation

Voir `docs/guide-contenu-claude.md` pour le setup initial.

### Mettre à jour le site

```bash
# Avec Claude Code
claude  # ouvre Claude Code dans le dossier du projet
# Demande les modifications
# Claude Code fait le commit + push
# → Le site est mis à jour automatiquement sur O2switch
```

## Structure

```
public/index.html       ← Frontend React (app complète)
api/index.php           ← API backend PHP
api/config.example.php  ← Template config (copier en config.php sur le serveur)
sql/                    ← Schéma BDD et migrations
docs/                   ← Guides
.cpanel.yml             ← Script de déploiement auto O2switch
CLAUDE.md               ← Instructions pour Claude Code
```
