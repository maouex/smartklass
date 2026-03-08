-- Migration v6 — Live Quiz (style Kahoot)
-- À exécuter via phpMyAdmin

CREATE TABLE IF NOT EXISTS live_sessions (
    id          VARCHAR(20) PRIMARY KEY,
    activity_id VARCHAR(20) NOT NULL,
    class_id    VARCHAR(20) NOT NULL,
    status      ENUM('waiting','active','paused','finished') DEFAULT 'waiting',
    current_q   TINYINT UNSIGNED DEFAULT 0,
    started_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)    REFERENCES classes(id)   ON DELETE CASCADE,
    INDEX idx_class_status (class_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_responses (
    id           VARCHAR(20) PRIMARY KEY,
    session_id   VARCHAR(20) NOT NULL,
    student_id   VARCHAR(20) NOT NULL,
    question_idx TINYINT UNSIGNED NOT NULL,
    answer_idx   TINYINT UNSIGNED NOT NULL,
    is_correct   TINYINT(1) NOT NULL DEFAULT 0,
    answered_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_student_q (session_id, student_id, question_idx),
    FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id)      ON DELETE CASCADE,
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
