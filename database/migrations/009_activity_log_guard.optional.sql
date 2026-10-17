-- 009 (optional) Database-level protection: the activity log cannot be edited or deleted.
-- Needs the TRIGGER privilege. If your hosting refuses it, the migration runner skips this file
-- and the log stays protected by the application (no update/delete code path) and by the HMAC hash chain,
-- which the Owner can verify from Activity Log > Verify integrity.

CREATE TRIGGER activity_log_no_update BEFORE UPDATE ON activity_log
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only';

CREATE TRIGGER activity_log_no_delete BEFORE DELETE ON activity_log
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only';
