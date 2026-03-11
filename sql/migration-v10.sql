-- Migration v10 : Ajouter le statut 'cancelled' aux live_sessions
-- Permet au prof de quitter un live quiz en notifiant les élèves instantanément

ALTER TABLE live_sessions
  MODIFY COLUMN status ENUM('waiting','active','paused','finished','cancelled') DEFAULT 'waiting';
