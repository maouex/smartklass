-- Migration v9 SAFE : Live Quiz v2 — Timer, scoring vitesse, mode équipe
-- Exécuter chaque bloc séparément dans phpMyAdmin
-- Les erreurs "déjà utilisé" sont normales et peuvent être ignorées

-- 1) Timer
ALTER TABLE live_sessions ADD COLUMN timer_seconds TINYINT UNSIGNED DEFAULT 20;

-- 2) Timestamp début de question
ALTER TABLE live_sessions ADD COLUMN question_started_at TIMESTAMP NULL DEFAULT NULL;

-- 3) Mode de jeu
ALTER TABLE live_sessions ADD COLUMN mode ENUM('individual','team') DEFAULT 'individual';

-- 4) Type d'activité
ALTER TABLE live_sessions ADD COLUMN activity_type VARCHAR(20) DEFAULT 'qcm';

-- 5) Temps de réponse
ALTER TABLE live_responses ADD COLUMN response_time_ms INT UNSIGNED DEFAULT NULL;

-- 6) Score par vitesse
ALTER TABLE live_responses ADD COLUMN score INT UNSIGNED DEFAULT 0;

-- 7) Table des équipes
CREATE TABLE IF NOT EXISTS live_teams (
    session_id VARCHAR(20) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    team ENUM('A','B') NOT NULL,
    PRIMARY KEY (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES live_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);
