-- Run once for an existing database. schema.sql is idempotent for new tables.
USE duanmau_atgt;
SET @has_age_group := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'age_group');
SET @age_sql := IF(@has_age_group = 0, 'ALTER TABLE users ADD COLUMN age_group ENUM(\'6-8\',\'9-11\') NULL DEFAULT \'6-8\'', 'SELECT 1');
PREPARE age_stmt FROM @age_sql;
EXECUTE age_stmt;
DEALLOCATE PREPARE age_stmt;

-- Importing schema.sql after this file creates the learning and moderation tables
-- and inserts the first verified lesson/source records.
