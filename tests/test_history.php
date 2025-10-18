<?php
/**
 * KanbanBoard历史记录功能测试脚本
 * 使用方法：php extensions/KanbanBoard/tests/test_history.php
 */

echo "=== KanbanBoard 历史记录功能测试 ===\n\n";

// 1. 检查数据库表是否存在
echo "1. 检查数据库表...\n";
$mysqli = new mysqli('localhost', 'bitnami', 'bitnami', 'bitnami_mediawiki');

if ($mysqli->connect_error) {
    echo "   ❌ 数据库连接失败: " . $mysqli->connect_error . "\n";
    exit(1);
}

// 检查表是否存在
$result = $mysqli->query("SHOW TABLES LIKE 'kanban_task_history'");
if ($result->num_rows > 0) {
    echo "   ✅ kanban_task_history 表存在\n";
} else {
    echo "   ❌ kanban_task_history 表不存在\n";
    exit(1);
}

// 2. 检查表结构
echo "\n2. 检查表结构...\n";
$result = $mysqli->query("DESCRIBE kanban_task_history");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$expectedColumns = [
    'history_id', 'task_id', 'field_name', 'old_value', 'new_value',
    'changed_by', 'changed_at', 'change_type', 'change_reason',
    'ip_address', 'user_agent'
];

foreach ($expectedColumns as $column) {
    if (in_array($column, $columns)) {
        echo "   ✅ 字段 $column 存在\n";
    } else {
        echo "   ❌ 字段 $column 不存在\n";
    }
}

// 3. 检查索引
echo "\n3. 检查索引...\n";
$result = $mysqli->query("SHOW INDEX FROM kanban_task_history");
$indexes = [];
while ($row = $result->fetch_assoc()) {
    $indexes[] = $row['Key_name'];
}

$expectedIndexes = ['PRIMARY', 'idx_task', 'idx_changed_at', 'idx_changed_by', 'idx_change_type', 'idx_task_field'];
foreach ($expectedIndexes as $index) {
    if (in_array($index, $indexes)) {
        echo "   ✅ 索引 $index 存在\n";
    } else {
        echo "   ❌ 索引 $index 不存在\n";
    }
}

// 4. 检查现有任务
echo "\n4. 检查现有任务...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM kanban_tasks WHERE deleted_at IS NULL");
$row = $result->fetch_assoc();
$taskCount = $row['count'];
echo "   现有任务数量: $taskCount\n";

if ($taskCount > 0) {
    // 5. 检查历史记录
    echo "\n5. 检查历史记录...\n";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM kanban_task_history");
    $row = $result->fetch_assoc();
    $historyCount = $row['count'];
    echo "   历史记录数量: $historyCount\n";
    
    if ($historyCount > 0) {
        echo "   ✅ 已有历史记录数据\n";
        
        // 显示最近的历史记录
        $result = $mysqli->query("SELECT * FROM kanban_task_history ORDER BY changed_at DESC LIMIT 3");
        echo "\n   最近的历史记录:\n";
        while ($row = $result->fetch_assoc()) {
            echo "   - {$row['change_type']}: {$row['field_name']} (任务ID: {$row['task_id']}, 时间: {$row['changed_at']})\n";
        }
    } else {
        echo "   ⚠️  暂无历史记录数据\n";
    }
}

$mysqli->close();

echo "\n=== 测试完成 ===\n";
echo "如果所有项目都显示 ✅，说明历史记录功能已正确安装！\n";
echo "\n下一步：\n";
echo "1. 访问包含看板的页面\n";
echo "2. 点击任意任务卡片\n";
echo "3. 在弹出的对话框中点击'历史记录'标签页\n";
echo "4. 查看任务的历史变更记录\n";

