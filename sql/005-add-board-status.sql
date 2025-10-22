-- 添加看板状态字段
-- 状态值：active(活跃), hidden(隐藏), archived(存档), deleted(已删除)

ALTER TABLE /*_*/kanban_boards 
ADD COLUMN board_status ENUM('active','hidden','archived','deleted') DEFAULT 'active' AFTER board_permissions,
ADD COLUMN status_changed_at TIMESTAMP NULL AFTER board_status,
ADD COLUMN status_changed_by INT UNSIGNED NULL AFTER status_changed_at;

-- 添加索引
ALTER TABLE /*_*/kanban_boards 
ADD INDEX idx_board_status (board_status),
ADD INDEX idx_status_changed (status_changed_at);

-- 添加外键约束
ALTER TABLE /*_*/kanban_boards 
ADD FOREIGN KEY (status_changed_by) REFERENCES /*_*/user(user_id) ON DELETE SET NULL;



