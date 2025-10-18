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
