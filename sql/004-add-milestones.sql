-- MediaWiki Kanban Board Extension - Milestones Support
-- 里程碑表
CREATE TABLE IF NOT EXISTS /*_*/kanban_milestones (
    milestone_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    target_date DATE NULL,
    completed_date DATE NULL,
    status ENUM('planned','in_progress','completed','cancelled') DEFAULT 'planned',
    color VARCHAR(7) DEFAULT '#9b59b6',
    milestone_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    INDEX idx_board_order (board_id, milestone_order),
    INDEX idx_target_date (target_date),
    INDEX idx_status (status)
) /*$wgDBTableOptions*/;

-- 里程碑历史记录表
CREATE TABLE IF NOT EXISTS /*_*/kanban_milestone_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(64) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_type ENUM('create','update','delete') NOT NULL,
    change_reason VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    FOREIGN KEY (milestone_id) REFERENCES /*_*/kanban_milestones(milestone_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_milestone (milestone_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_change_type (change_type)
) /*$wgDBTableOptions*/;




