-- 添加任务完成时间字段
ALTER TABLE /*_*/kanban_tasks
    ADD COLUMN completed_at DATETIME NULL AFTER due_date;

-- 添加索引
CREATE INDEX idx_task_completed_at ON /*_*/kanban_tasks (completed_at);

-- 添加注释
ALTER TABLE /*_*/kanban_tasks 
    MODIFY COLUMN completed_at DATETIME NULL COMMENT '任务实际完成时间，当任务移动到终态列时自动设置';




