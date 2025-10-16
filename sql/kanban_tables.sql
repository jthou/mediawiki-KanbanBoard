-- MediaWiki Kanban Board Extension Database Schema (board/status/task 模型)
-- 看板表
CREATE TABLE IF NOT EXISTS /*_*/kanban_boards (
    board_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kanban_name VARCHAR(128) NOT NULL,
    kanban_slug VARCHAR(128) NULL,
    board_name VARCHAR(255) NOT NULL,
    board_description TEXT,
    board_owner_id INT UNSIGNED NOT NULL,
    visibility ENUM('private','internal','public') DEFAULT 'private',
    board_max_columns INT UNSIGNED DEFAULT 10,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_kanban_name (kanban_name),
    INDEX idx_owner (board_owner_id),
    INDEX idx_created (created_at),
    INDEX idx_slug (kanban_slug)
) /*$wgDBTableOptions*/;

-- 状态表（原 columns ）
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

-- 任务表（原 cards ）
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

-- 权限（成员角色）
CREATE TABLE IF NOT EXISTS /*_*/kanban_permissions (
    permission_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('view','edit','admin') NOT NULL,
    granted_by INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_board (user_id, board_id),
    INDEX idx_board (board_id),
    INDEX idx_user (user_id)
) /*$wgDBTableOptions*/;

-- 任务受理人（多对多）
CREATE TABLE IF NOT EXISTS /*_*/kanban_task_assignees (
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED NULL,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE
) /*$wgDBTableOptions*/;

-- 标签与任务标签
CREATE TABLE IF NOT EXISTS /*_*/kanban_labels (
    label_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    label_name VARCHAR(128) NOT NULL,
    label_color VARCHAR(7) DEFAULT '#888888',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    INDEX idx_board (board_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/kanban_task_labels (
    task_id INT UNSIGNED NOT NULL,
    label_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, label_id),
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES /*_*/kanban_labels(label_id) ON DELETE CASCADE
) /*$wgDBTableOptions*/;

-- 评论（挂任务）
CREATE TABLE IF NOT EXISTS /*_*/kanban_comments (
    comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) /*$wgDBTableOptions*/;

-- 附件（挂任务）
CREATE TABLE IF NOT EXISTS /*_*/kanban_attachments (
    attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    file_type VARCHAR(100),
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_uploader (uploaded_by)
) /*$wgDBTableOptions*/;
