-- ============================================================================
-- SmartKlass — Migration v2
-- Exécuter dans phpMyAdmin → onglet SQL
-- ============================================================================

-- 1. Mot de passe élève (NULL = première connexion, doit choisir un mdp)
ALTER TABLE students ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER identifier;

-- 2. course_id sur les activités (si pas déjà fait)
-- Si tu as déjà exécuté la migration précédente, cette ligne donnera une erreur
-- "Duplicate column name" → c'est normal, ignore-la
ALTER TABLE activities ADD COLUMN course_id VARCHAR(20) DEFAULT NULL AFTER subject_id;
-- La foreign key aussi (ignorera si déjà existante)
-- ALTER TABLE activities ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;
