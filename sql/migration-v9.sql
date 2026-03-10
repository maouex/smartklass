-- Migration v9 : Live Quiz v2 — Timer, scoring vitesse, mode équipe

-- Timer et timestamp de début de question
ALTER TABLE live_sessions ADD COLUMN timer_seconds TINYINT UNSIGNED DEFAULT 20;
ALTER TABLE live_sessions ADD COLUMN question_started_at TIMESTAMP NULL DEFAULT NULL;

-- Mode de jeu (individuel ou équipe)
ALTER TABLE live_sessions ADD COLUMN mode ENUM('individual','team') DEFAULT 'individual';

-- Type d'activité (pour supporter vrai/faux et matching)
ALTER TABLE live_sessions ADD COLUMN activity_type VARCHAR(20) DEFAULT 'qcm';

-- Temps de réponse et score par vitesse
ALTER TABLE live_responses ADD COLUMN response_time_ms INT UNSIGNED DEFAULT NULL;
ALTER TABLE live_responses ADD COLUMN score INT UNSIGNED DEFAULT 0;

-- Table des équipes pour le mode team
CREATE TABLE IF NOT EXISTS live_teams (
    session_id VARCHAR(20) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    team ENUM('A','B') NOT NULL,
    PRIMARY KEY (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES live_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);
