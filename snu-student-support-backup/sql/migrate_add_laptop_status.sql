-- Run ONCE against an existing installation after taking a full backup.
-- Existing cases default to In Store until the administrator updates them.
USE snu_student_support;
ALTER TABLE support_records
  ADD COLUMN laptop_status ENUM('In Store','Collected') NOT NULL DEFAULT 'In Store' AFTER work_status;
