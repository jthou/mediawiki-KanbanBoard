-- 安全迁移脚本：添加缺失的字段和约束
-- 检查字段是否存在，避免重复添加

-- 添加board_max_columns字段（如果不存在）
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'kanban_boards' 
     AND COLUMN_NAME = 'board_max_columns') = 0,
    'ALTER TABLE kanban_boards ADD COLUMN board_max_columns INT UNSIGNED DEFAULT 10 COMMENT "最大列数" AFTER board_permissions',
    'SELECT "board_max_columns column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加creator索引（如果不存在）
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'kanban_columns' 
     AND INDEX_NAME = 'idx_creator') = 0,
    'ALTER TABLE kanban_columns ADD INDEX idx_creator (column_creator_id)',
    'SELECT "idx_creator index already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加外键约束（如果不存在）
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'kanban_columns' 
     AND CONSTRAINT_NAME = 'fk_column_creator') = 0,
    'ALTER TABLE kanban_columns ADD CONSTRAINT fk_column_creator FOREIGN KEY (column_creator_id) REFERENCES user(user_id) ON DELETE SET NULL',
    'SELECT "fk_column_creator constraint already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建列模板表（如果不存在）
CREATE TABLE IF NOT EXISTS kanban_column_templates (
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
    FOREIGN KEY (template_creator_id) REFERENCES user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认列模板（如果不存在）
INSERT IGNORE INTO kanban_column_templates 
(template_name, template_description, template_color, template_width, template_max_cards, template_wip_limit, template_creator_id) 
VALUES 
('待办', '待处理的任务', '#e74c3c', 300, 0, 0, 1),
('进行中', '正在进行的任务', '#f39c12', 300, 0, 5, 1),
('已完成', '已完成的任务', '#27ae60', 300, 0, 0, 1),
('阻塞', '被阻塞的任务', '#95a5a6', 300, 0, 0, 1),
('测试', '待测试的任务', '#9b59b6', 300, 0, 3, 1);

-- 更新现有列的数据（确保所有字段都有默认值）
UPDATE kanban_columns 
SET column_width = COALESCE(column_width, 300),
    column_max_cards = COALESCE(column_max_cards, 0),
    column_is_collapsed = COALESCE(column_is_collapsed, 0),
    column_wip_limit = COALESCE(column_wip_limit, 0),
    column_creator_id = COALESCE(column_creator_id, 1),
    column_color = COALESCE(column_color, '#3498db')
WHERE column_width IS NULL OR column_max_cards IS NULL OR column_is_collapsed IS NULL 
   OR column_wip_limit IS NULL OR column_creator_id IS NULL OR column_color IS NULL;





