-- Migration v10 : Avatar personnalisable pour les élèves
-- Stocke la configuration de l'avatar en JSON
-- Ex: {"skin":2,"hair":3,"hairColor":1,"eyes":4,"mouth":2,"accessory":0}

ALTER TABLE students ADD COLUMN avatar JSON DEFAULT NULL;
