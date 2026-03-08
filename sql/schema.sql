-- ============================================================================
-- SmartKlass - Schéma de base de données MySQL
-- Version 2.0
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Table des matières
CREATE TABLE IF NOT EXISTS subjects (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(10) DEFAULT '#6C5CE7',
    icon VARCHAR(10) DEFAULT '📊',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des classes
CREATE TABLE IF NOT EXISTS classes (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    year VARCHAR(20) DEFAULT '2025-2026',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des élèves
CREATE TABLE IF NOT EXISTS students (
    id VARCHAR(20) PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    class_id VARCHAR(20) NOT NULL,
    identifier VARCHAR(50) UNIQUE NOT NULL,
    xp INT DEFAULT 0,
    streak INT DEFAULT 0,
    last_active TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des cours
CREATE TABLE IF NOT EXISTS courses (
    id VARCHAR(20) PRIMARY KEY,
    subject_id VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    chapters JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison cours <-> classes
CREATE TABLE IF NOT EXISTS course_classes (
    course_id VARCHAR(20) NOT NULL,
    class_id VARCHAR(20) NOT NULL,
    PRIMARY KEY (course_id, class_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des activités
CREATE TABLE IF NOT EXISTS activities (
    id VARCHAR(20) PRIMARY KEY,
    subject_id VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('qcm', 'flashcards', 'truefalse', 'fillblank', 'matching') NOT NULL,
    difficulty TINYINT DEFAULT 2,
    xp_reward INT DEFAULT 40,
    data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison activités <-> classes
CREATE TABLE IF NOT EXISTS activity_classes (
    activity_id VARCHAR(20) NOT NULL,
    class_id VARCHAR(20) NOT NULL,
    PRIMARY KEY (activity_id, class_id),
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des résultats
CREATE TABLE IF NOT EXISTS results (
    id VARCHAR(20) PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    activity_id VARCHAR(20) NOT NULL,
    score INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table configuration (mot de passe prof, etc.)
CREATE TABLE IF NOT EXISTS config (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Données initiales
-- ============================================================================

-- Mot de passe prof (hashé en PHP, ici en clair pour l'init - sera hashé au premier lancement)
INSERT INTO config (config_key, config_value) VALUES 
('teacher_password', 'smartklass2024'),
('teacher_name', 'Professeur');

-- Matières par défaut
INSERT INTO subjects (id, name, color, icon) VALUES 
('sdgn', 'SDGN', '#6C5CE7', '📊'),
('msdgn', 'MSDGN', '#00B894', '📈'),
('marketing', 'Marketing / Mercatique', '#E17055', '🎯');

-- Classes par défaut
INSERT INTO classes (id, name, year) VALUES 
('c1', '1ère STMG A', '2025-2026'),
('c2', '1ère STMG B', '2025-2026');
