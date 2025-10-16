-- Safe migration from columns/cards to statuses/tasks (procedure-based for compatibility)
DELIMITER //
CREATE PROCEDURE kb_migrate_status_task()
BEGIN
  START TRANSACTION;

  -- 1) Boards: add columns if missing
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='kanban_boards' AND COLUMN_NAME='kanban_name'
  ) THEN
    SET @s = 'ALTER TABLE kanban_boards ADD COLUMN kanban_name VARCHAR(128) NOT NULL AFTER board_id';
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='kanban_boards' AND COLUMN_NAME='kanban_slug'
  ) THEN
    SET @s = 'ALTER TABLE kanban_boards ADD COLUMN kanban_slug VARCHAR(128) NULL AFTER kanban_name';
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='kanban_boards' AND COLUMN_NAME='visibility'
  ) THEN
    SET @s = 'ALTER TABLE kanban_boards ADD COLUMN visibility ENUM(\'private\',\'internal\',\'public\') DEFAULT \'private\' AFTER board_owner_id';
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;

  -- Keys if missing
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='kanban_boards' AND INDEX_NAME='uniq_kanban_name'
  ) THEN
    SET @s = 'ALTER TABLE kanban_boards ADD UNIQUE KEY uniq_kanban_name (kanban_name)';
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='kanban_boards' AND INDEX_NAME='idx_slug'
  ) THEN
    SET @s = 'ALTER TABLE kanban_boards ADD INDEX idx_slug (kanban_slug)';
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;

  -- Backfill
  UPDATE kanban_boards b
  SET b.kanban_name = COALESCE(b.kanban_name, b.board_name),
      b.kanban_slug = COALESCE(b.kanban_slug, LOWER(REPLACE(b.board_name, ' ', '-')))
  WHERE b.kanban_name IS NULL OR b.kanban_slug IS NULL;

  -- 2) Create tables if not exists
  SET @s = 'CREATE TABLE IF NOT EXISTS kanban_statuses (
    status_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    status_key VARCHAR(64) NOT NULL,
    status_name VARCHAR(255) NOT NULL,
    status_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_terminal TINYINT(1) DEFAULT 0,
    color VARCHAR(7) DEFAULT ''#3498db'',
    wip_limit INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES kanban_boards(board_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_board_status_key (board_id, status_key),
    INDEX idx_board_order (board_id, status_order)
  ) ENGINE=InnoDB';
  PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

  SET @s = 'CREATE TABLE IF NOT EXISTS kanban_tasks (
    task_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    status_id INT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    description LONGTEXT NULL,
    priority ENUM(''low'',''medium'',''high'',''urgent'') DEFAULT ''medium'',
    color VARCHAR(7) DEFAULT ''#ffffff'',
    task_order INT UNSIGNED NOT NULL DEFAULT 0,
    due_date DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES kanban_boards(board_id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES kanban_statuses(status_id) ON DELETE CASCADE,
    INDEX idx_board_status_order (board_id, status_id, task_order),
    INDEX idx_due_date (due_date),
    INDEX idx_board (board_id)
  ) ENGINE=InnoDB';
  PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

  -- 3) Migrate columns -> statuses
  INSERT INTO kanban_statuses (board_id, status_key, status_name, status_order, color, wip_limit)
  SELECT c.board_id,
         CONCAT('col_', c.column_id),
         c.column_name,
         c.column_order,
         COALESCE(c.column_color, '#3498db'),
         COALESCE(c.column_wip_limit, 0)
  FROM kanban_columns c
  LEFT JOIN kanban_statuses s
    ON s.board_id = c.board_id AND s.status_key = CONCAT('col_', c.column_id)
  WHERE s.status_id IS NULL;

  -- 4) Migrate cards -> tasks
  INSERT INTO kanban_tasks (board_id, status_id, title, description, priority, color, task_order, due_date, created_by, created_at)
  SELECT b.board_id,
         s.status_id,
         ca.card_title,
         ca.card_description,
         ca.card_priority,
         ca.card_color,
         ca.card_order,
         ca.card_due_date,
         ca.card_creator_id,
         ca.card_created_at
  FROM kanban_cards ca
  JOIN kanban_columns c ON c.column_id = ca.column_id
  JOIN kanban_boards b ON b.board_id = c.board_id
  JOIN kanban_statuses s ON s.board_id = c.board_id AND s.status_key = CONCAT('col_', c.column_id)
  LEFT JOIN kanban_tasks t ON t.board_id = b.board_id AND t.title = ca.card_title AND t.created_at = ca.card_created_at
  WHERE t.task_id IS NULL;

  COMMIT;
END //
DELIMITER ;

CALL kb_migrate_status_task();
DROP PROCEDURE kb_migrate_status_task;

