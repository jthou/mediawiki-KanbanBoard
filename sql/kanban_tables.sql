-- KanbanBoard 数据库表结构
-- 创建看板表
CREATE TABLE IF NOT EXISTS /*_*/kanban_boards (
    board_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    kanban_name VARCHAR(128) NOT NULL,
    kanban_slug VARCHAR(128) NULL,
    board_description TEXT,
    board_owner_id INT UNSIGNED NOT NULL,
    visibility ENUM('private','internal','public') DEFAULT 'private',
    board_permissions JSON,
    board_max_columns INT UNSIGNED DEFAULT 10 COMMENT '最大列数',
    board_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    board_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    board_deleted_at DATETIME NULL,
    UNIQUE KEY uniq_kanban_name (kanban_name),
    INDEX idx_slug (kanban_slug),
    INDEX idx_owner (board_owner_id),
    INDEX idx_visibility (visibility)
) /*$wgDBTableOptions*/;

-- 创建状态表（替代列）
CREATE TABLE IF NOT EXISTS /*_*/kanban_statuses (
    status_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    status_key VARCHAR(64) NOT NULL,
    status_name VARCHAR(255) NOT NULL,
    status_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_terminal TINYINT(1) DEFAULT 0,
    color VARCHAR(7) DEFAULT '#3498db',
    wip_limit INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_board_status_key (board_id, status_key),
    INDEX idx_board_order (board_id, status_order)
) /*$wgDBTableOptions*/;

-- 创建任务表（替代卡片）
CREATE TABLE IF NOT EXISTS /*_*/kanban_tasks (
    task_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    status_id INT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    description LONGTEXT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    color VARCHAR(7) DEFAULT '#ffffff',
    task_order INT UNSIGNED NOT NULL DEFAULT 0,
    due_date DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES /*_*/kanban_statuses(status_id) ON DELETE CASCADE,
    INDEX idx_board_status_order (board_id, status_id, task_order),
    INDEX idx_due_date (due_date),
    INDEX idx_board (board_id)
) /*$wgDBTableOptions*/;

-- 创建任务历史表
CREATE TABLE IF NOT EXISTS /*_*/kanban_task_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by INT UNSIGNED NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_changed_at (changed_at)
) /*$wgDBTableOptions*/;

-- 创建里程碑表
CREATE TABLE IF NOT EXISTS /*_*/kanban_milestones (
    milestone_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    color VARCHAR(7) DEFAULT '#3498db',
    is_completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    INDEX idx_board (board_id),
    INDEX idx_end_date (end_date)
) /*$wgDBTableOptions*/;

-- 创建任务里程碑关联表
CREATE TABLE IF NOT EXISTS /*_*/kanban_task_milestones (
    task_id INT UNSIGNED NOT NULL,
    milestone_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, milestone_id),
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES /*_*/kanban_milestones(milestone_id) ON DELETE CASCADE
) /*$wgDBTableOptions*/;

-- 创建列模板表
CREATE TABLE IF NOT EXISTS /*_*/kanban_column_templates (
    template_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    template_description TEXT,
    template_color VARCHAR(7) DEFAULT '#3498db',
    template_width INT UNSIGNED DEFAULT 300,
    template_max_cards INT UNSIGNED DEFAULT 0,
    template_wip_limit INT UNSIGNED DEFAULT 0,
    template_creator_id INT UNSIGNED NOT NULL,
    template_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    template_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_creator (template_creator_id),
    FOREIGN KEY (template_creator_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE
) /*$wgDBTableOptions*/;

-- 插入默认列模板
INSERT IGNORE INTO /*_*/kanban_column_templates 
(template_name, template_description, template_color, template_width, template_max_cards, template_wip_limit, template_creator_id) 
VALUES 
('待办', '待处理的任务', '#e74c3c', 300, 0, 0, 1),
('进行中', '正在进行的任务', '#f39c12', 300, 0, 5, 1),
('已完成', '已完成的任务', '#27ae60', 300, 0, 0, 1),
('阻塞', '被阻塞的任务', '#95a5a6', 300, 0, 0, 1),
('测试', '待测试的任务', '#9b59b6', 300, 0, 3, 1);