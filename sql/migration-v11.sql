-- Migration v11 : Live Quiz — Mute élèves + Malus points
-- À exécuter via phpMyAdmin

-- Le prof peut couper le son pour tous les élèves
ALTER TABLE live_sessions ADD COLUMN sound_disabled TINYINT(1) DEFAULT 0;

-- Permettre les scores négatifs (malus) dans les réponses
ALTER TABLE live_responses MODIFY COLUMN score INT DEFAULT 0;
