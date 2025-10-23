-- KanbanBoard 性能优化索引
-- 添加日期：2024-01-XX
-- 目的：优化统计查询性能

-- 1. 为 completed_at 字段添加索引（用于时间范围查询）
ALTER TABLE kanban_tasks ADD INDEX idx_completed_at (completed_at);

-- 2. 为 board_id 和 completed_at 添加复合索引（用于按看板查询完成的任务）
ALTER TABLE kanban_tasks ADD INDEX idx_board_completed (board_id, completed_at);

-- 3. 为 created_at 字段添加索引（用于趋势数据查询）
ALTER TABLE kanban_tasks ADD INDEX idx_created_at (created_at);

-- 4. 为 board_id 和 created_at 添加复合索引（用于按看板查询创建的任务）
ALTER TABLE kanban_tasks ADD INDEX idx_board_created (board_id, created_at);

-- 5. 为 status_id 字段添加索引（用于状态查询）
ALTER TABLE kanban_tasks ADD INDEX idx_status_id (status_id);

-- 6. 为 board_id 和 status_id 添加复合索引（用于按看板和状态查询）
ALTER TABLE kanban_tasks ADD INDEX idx_board_status (board_id, status_id);

-- 7. 为 priority 字段添加索引（用于优先级排序）
ALTER TABLE kanban_tasks ADD INDEX idx_priority (priority);

-- 8. 为 task_order 字段添加索引（用于排序查询）
ALTER TABLE kanban_tasks ADD INDEX idx_task_order (task_order);

-- 9. 为 kanban_boards 表的 board_status 添加索引（用于状态筛选）
ALTER TABLE kanban_boards ADD INDEX idx_board_status (board_status);

-- 10. 为 kanban_boards 表的 visibility 添加索引（用于权限查询）
ALTER TABLE kanban_boards ADD INDEX idx_visibility (visibility);

-- 11. 为 kanban_boards 表的 board_owner_id 添加索引（用于所有者查询）
ALTER TABLE kanban_boards ADD INDEX idx_board_owner (board_owner_id);

-- 12. 为 kanban_statuses 表的 board_id 和 status_order 添加复合索引（用于列排序）
ALTER TABLE kanban_statuses ADD INDEX idx_board_order (board_id, status_order);

-- 显示索引创建结果
SHOW INDEX FROM kanban_tasks;
SHOW INDEX FROM kanban_boards;
SHOW INDEX FROM kanban_statuses;
