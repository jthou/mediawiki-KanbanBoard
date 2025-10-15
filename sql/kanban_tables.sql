-- MediaWiki Kanban Board Extension Database Schema
-- 创建看板表
CREATE TABLE IF NOT EXISTS /*_*/kanban_boards (
    board_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_name VARCHAR(255) NOT NULL,
    board_description TEXT,
    board_owner_id INT UNSIGNED NOT NULL,
    board_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    board_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    board_permissions VARCHAR(50) DEFAULT 'private',
    INDEX idx_owner (board_owner_id),
    INDEX idx_created (board_created_at)
) /*$wgDBTableOptions*/;

-- 创建列表
CREATE TABLE IF NOT EXISTS /*_*/kanban_columns (
    column_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_description TEXT,
    column_order INT UNSIGNED NOT NULL DEFAULT 0,
    column_color VARCHAR(7) DEFAULT '#3498db',
    column_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    INDEX idx_board_order (board_id, column_order)
) /*$wgDBTableOptions*/;

-- 创建卡片表
CREATE TABLE IF NOT EXISTS /*_*/kanban_cards (
    card_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    column_id INT UNSIGNED NOT NULL,
    card_title VARCHAR(500) NOT NULL,
    card_description TEXT,
    card_assignee_id INT UNSIGNED,
    card_creator_id INT UNSIGNED NOT NULL,
    card_priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    card_color VARCHAR(7) DEFAULT '#ffffff',
    card_order INT UNSIGNED NOT NULL DEFAULT 0,
    card_due_date DATETIME NULL,
    card_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    card_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (column_id) REFERENCES /*_*/kanban_columns(column_id) ON DELETE CASCADE,
    FOREIGN KEY (card_assignee_id) REFERENCES /*_*/user(user_id) ON DELETE SET NULL,
    FOREIGN KEY (card_creator_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_column_order (column_id, card_order),
    INDEX idx_assignee (card_assignee_id),
    INDEX idx_creator (card_creator_id),
    INDEX idx_due_date (card_due_date)
) /*$wgDBTableOptions*/;

-- 创建看板权限表
CREATE TABLE IF NOT EXISTS /*_*/kanban_permissions (
    permission_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    permission_type ENUM('view', 'edit', 'admin') NOT NULL,
    granted_by INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_board (user_id, board_id),
    INDEX idx_board (board_id),
    INDEX idx_user (user_id)
) /*$wgDBTableOptions*/;

-- 创建卡片评论表
CREATE TABLE IF NOT EXISTS /*_*/kanban_comments (
    comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    comment_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comment_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES /*_*/kanban_cards(card_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_card (card_id),
    INDEX idx_user (user_id),
    INDEX idx_created (comment_created_at)
) /*$wgDBTableOptions*/;

-- 创建卡片附件表
CREATE TABLE IF NOT EXISTS /*_*/kanban_attachments (
    attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    file_type VARCHAR(100),
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES /*_*/kanban_cards(card_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_card (card_id),
    INDEX idx_uploader (uploaded_by)
) /*$wgDBTableOptions*/;
