-- ============================================================================
-- SmartKlass — Migration v4 : Notifications Push (Web Push API)
-- Exécuter dans phpMyAdmin → onglet SQL
-- ============================================================================

-- Table des souscriptions push (une ligne par appareil abonné)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id VARCHAR(20) PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    auth VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_endpoint (endpoint(200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les clés VAPID (vapid_public_key, vapid_private_key) sont générées
-- automatiquement par l'API au premier appel de /api/vapid-key
-- et stockées dans la table config existante.
