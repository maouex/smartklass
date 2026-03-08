-- Migration v7 : suivi des élèves ayant rejoint une session live
-- À exécuter via phpMyAdmin

CREATE TABLE IF NOT EXISTS live_joined (
    session_id  VARCHAR(20) NOT NULL,
    student_id  VARCHAR(20) NOT NULL,
    joined_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
