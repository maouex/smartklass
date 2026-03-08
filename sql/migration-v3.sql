-- Migration v3 : Ajout du lien YouTube aux cours
ALTER TABLE courses ADD COLUMN youtube_url VARCHAR(500) NULL DEFAULT NULL;
