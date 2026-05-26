-- Migration: Support tenant recipients in the communications table
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE communications
  DROP FOREIGN KEY communications_ibfk_2;

ALTER TABLE communications
  ADD COLUMN IF NOT EXISTS recipient_type ENUM('user','tenant') NOT NULL DEFAULT 'user';

SET FOREIGN_KEY_CHECKS=1;
