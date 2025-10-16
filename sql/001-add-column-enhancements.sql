-- 001-add-column-enhancements.sql
-- 数据库迁移脚本：为添加列功能增强数据库表结构

-- 为 kanban_columns 表添加新字段
ALTER TABLE /*_*/kanban_columns 
ADD COLUMN column_width INT UNSIGNED DEFAULT 300 COMMENT '列宽度(像素)' AFTER column_order,
ADD COLUMN column_max_cards INT UNSIGNED DEFAULT 0 COMMENT '最大卡片数(0表示无限制)' AFTER column_width,
ADD COLUMN column_is_collapsed TINYINT(1) DEFAULT 0 COMMENT '是否折叠' AFTER column_max_cards,
ADD COLUMN column_wip_limit INT UNSIGNED DEFAULT 0 COMMENT 'WIP限制' AFTER column_is_collapsed,
ADD COLUMN column_creator_id INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '列创建者ID' AFTER column_wip_limit;

-- 添加索引优化查询性能
ALTER TABLE /*_*/kanban_columns 
ADD INDEX idx_board_order (board_id, column_order),
ADD INDEX idx_creator (column_creator_id);

-- 添加外键约束
ALTER TABLE /*_*/kanban_columns 
ADD CONSTRAINT fk_column_creator 
FOREIGN KEY (column_creator_id) REFERENCES /*_*/user(user_id) ON DELETE SET NULL;

-- 为 kanban_boards 表添加列数限制字段
ALTER TABLE /*_*/kanban_boards 
ADD COLUMN board_max_columns INT UNSIGNED DEFAULT 10 COMMENT '最大列数' AFTER board_permissions;

-- 创建列模板表（可选功能）
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入一些默认列模板
INSERT INTO /*_*/kanban_column_templates 
(template_name, template_description, template_color, template_width, template_max_cards, template_wip_limit, template_creator_id) 
VALUES 
('待办', '待处理的任务', '#e74c3c', 300, 0, 0, 1),
('进行中', '正在进行的任务', '#f39c12', 300, 0, 5, 1),
('已完成', '已完成的任务', '#27ae60', 300, 0, 0, 1),
('阻塞', '被阻塞的任务', '#95a5a6', 300, 0, 0, 1),
('测试', '待测试的任务', '#9b59b6', 300, 0, 3, 1);

-- 更新现有列的数据
UPDATE /*_*/kanban_columns 
SET column_width = 300, 
    column_max_cards = 0, 
    column_is_collapsed = 0, 
    column_wip_limit = 0,
    column_creator_id = 1
WHERE column_width IS NULL;


