-- Migration v5 — Historique des notifications push
-- À exécuter via phpMyAdmin

CREATE TABLE IF NOT EXISTS notification_history (
    id         VARCHAR(20) PRIMARY KEY,
    class_id   VARCHAR(20),
    title      VARCHAR(255) NOT NULL,
    body       TEXT,
    sent_count INT DEFAULT 0,
    sent_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class_id (class_id),
    INDEX idx_sent_at  (sent_at)
);
