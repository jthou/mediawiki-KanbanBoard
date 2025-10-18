-- 003-add-task-history.sql
-- 为KanbanBoard扩展添加任务历史记录功能

-- 创建任务历史记录表
CREATE TABLE IF NOT EXISTS /*_*/kanban_task_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(50) NOT NULL COMMENT '变更字段名',
    old_value TEXT NULL COMMENT '变更前的值',
    new_value TEXT NULL COMMENT '变更后的值',
    changed_by INT UNSIGNED NOT NULL COMMENT '变更用户ID',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '变更时间',
    change_type ENUM('create', 'update', 'delete', 'move') NOT NULL COMMENT '变更类型',
    change_reason VARCHAR(255) NULL COMMENT '变更原因',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    user_agent TEXT NULL COMMENT '用户代理',
    FOREIGN KEY (task_id) REFERENCES /*_*/kanban_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by),
    INDEX idx_change_type (change_type),
    INDEX idx_task_field (task_id, field_name)
) /*$wgDBTableOptions*/;

-- 创建看板历史记录表（可选，用于看板和列的变更）
CREATE TABLE IF NOT EXISTS /*_*/kanban_board_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    entity_type ENUM('board', 'status', 'permission') NOT NULL COMMENT '实体类型',
    entity_id INT UNSIGNED NULL COMMENT '实体ID（状态ID等）',
    field_name VARCHAR(50) NOT NULL COMMENT '变更字段名',
    old_value TEXT NULL COMMENT '变更前的值',
    new_value TEXT NULL COMMENT '变更后的值',
    changed_by INT UNSIGNED NOT NULL COMMENT '变更用户ID',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '变更时间',
    change_type ENUM('create', 'update', 'delete') NOT NULL COMMENT '变更类型',
    change_reason VARCHAR(255) NULL COMMENT '变更原因',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    user_agent TEXT NULL COMMENT '用户代理',
    FOREIGN KEY (board_id) REFERENCES /*_*/kanban_boards(board_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES /*_*/user(user_id) ON DELETE CASCADE,
    INDEX idx_board (board_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by),
    INDEX idx_change_type (change_type)
) /*$wgDBTableOptions*/;

-- 为现有任务创建初始历史记录（可选）
-- 注意：这会为所有现有任务创建"创建"历史记录
INSERT INTO /*_*/kanban_task_history (
    task_id, field_name, old_value, new_value, changed_by, 
    changed_at, change_type, change_reason
)
SELECT 
    t.task_id,
    'created' as field_name,
    NULL as old_value,
    CONCAT('Task created: ', t.title) as new_value,
    COALESCE(t.created_by, 1) as changed_by,
    t.created_at as changed_at,
    'create' as change_type,
    'Initial task creation' as change_reason
FROM /*_*/kanban_tasks t
WHERE t.deleted_at IS NULL
AND NOT EXISTS (
    SELECT 1 FROM /*_*/kanban_task_history h 
    WHERE h.task_id = t.task_id AND h.change_type = 'create'
);

-- 添加清理历史记录的存储过程（可选）
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanOldTaskHistory()
BEGIN
    -- 删除6个月前的历史记录（保留最近6个月）
    DELETE FROM /*_*/kanban_task_history 
    WHERE changed_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    -- 删除1年前的看板历史记录
    DELETE FROM /*_*/kanban_board_history 
    WHERE changed_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
    
    -- 返回清理的记录数
    SELECT ROW_COUNT() as deleted_records;
END //
DELIMITER ;

-- 创建清理任务的定时事件（可选，需要开启事件调度器）
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT IF NOT EXISTS CleanKanbanHistory
-- ON SCHEDULE EVERY 1 MONTH
-- STARTS CURRENT_TIMESTAMP
-- DO CALL CleanOldTaskHistory();
