-- Migration v8 : ajout d'un champ position pour le tri des cours au sein d'une matière
ALTER TABLE courses ADD COLUMN position INT DEFAULT 0;

-- Initialise la position selon l'ordre de création (plus récent = position plus haute)
UPDATE courses c1
SET c1.position = (
    SELECT COUNT(*) FROM (SELECT id, subject_id, created_at FROM courses) c2
    WHERE c2.subject_id = c1.subject_id
      AND c2.created_at < c1.created_at
)
WHERE 1=1;
