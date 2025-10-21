# KanbanBoard 安装指南

## 快速安装

### 1. 系统要求检查

在开始安装之前，请确保您的系统满足以下要求：

```bash
# 检查PHP版本
php --version
# 需要 PHP 8.1 或更高版本

# 检查MediaWiki版本
php maintenance/version.php
# 需要 MediaWiki 1.42 或更高版本

# 检查数据库
mysql --version
# 需要 MySQL 5.7+ 或 MariaDB 10.3+
```

### 2. 下载扩展

#### 方法A: 使用Git（推荐）

```bash
cd /path/to/your/mediawiki/extensions/
git clone https://github.com/yourusername/KanbanBoard.git
cd KanbanBoard
```

#### 方法B: 下载ZIP文件

```bash
cd /path/to/your/mediawiki/extensions/
wget https://github.com/yourusername/KanbanBoard/archive/main.zip
unzip main.zip
mv KanbanBoard-main KanbanBoard
```

### 3. 配置MediaWiki

编辑 `LocalSettings.php` 文件：

```php
<?php
// 在文件末尾添加以下内容

// 加载KanbanBoard扩展
wfLoadExtension( 'KanbanBoard' );

// 基本配置
$wgKanbanBoardMaxColumns = 10;  // 最大列数
$wgKanbanBoardMaxCardsPerColumn = 100;  // 每列最大卡片数
$wgKanbanBoardAllowAnonymousEdit = false;  // 不允许匿名用户编辑

// 权限配置
$wgGroupPermissions['*']['kanbanboard-view'] = true;  // 所有人可查看
$wgGroupPermissions['user']['kanbanboard-edit'] = true;  // 注册用户可编辑
$wgGroupPermissions['sysop']['kanbanboard-admin'] = true;  // 管理员权限

// API配置
$wgEnableAPI = true;
$wgEnableWriteAPI = true;
```

### 4. 数据库更新

运行MediaWiki数据库更新脚本：

```bash
# 在MediaWiki根目录执行
php maintenance/update.php
```

如果遇到问题，可以尝试：

```bash
# 强制更新
php maintenance/update.php --force

# 跳过数据库检查
php maintenance/update.php --skip-external-dependencies
```

### 5. 清除缓存

```bash
# 清除本地化缓存
php maintenance/rebuildLocalisationCache.php

# 清除所有缓存
php maintenance/rebuildall.php

# 运行待处理任务
php maintenance/runJobs.php
```

### 6. 验证安装

访问以下URL验证安装：

```
http://your-wiki.com/wiki/特殊:KanbanBoard
http://your-wiki.com/wiki/特殊:KanbanSearch
```

## 详细安装步骤

### 环境准备

#### 1. 检查系统要求

```bash
# 检查PHP版本和扩展
php -m | grep -E "(mysqli|pdo_mysql|json|mbstring)"

# 检查Web服务器
apache2 -v  # 或 nginx -v

# 检查数据库
mysql -u root -p -e "SELECT VERSION();"
```

#### 2. 设置文件权限

```bash
# 设置正确的文件权限
chown -R www-data:www-data /path/to/mediawiki/extensions/KanbanBoard/
chmod -R 755 /path/to/mediawiki/extensions/KanbanBoard/

# 确保MediaWiki可以写入
chown -R www-data:www-data /path/to/mediawiki/images/
chmod -R 755 /path/to/mediawiki/images/
```

### 数据库配置

#### 1. 创建数据库用户（可选）

```sql
-- 为KanbanBoard创建专用数据库用户
CREATE USER 'kanbanboard'@'localhost' IDENTIFIED BY 'secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON your_wiki_db.* TO 'kanbanboard'@'localhost';
FLUSH PRIVILEGES;
```

#### 2. 检查数据库表

安装完成后，检查以下表是否创建成功：

```sql
USE your_wiki_db;

-- 检查看板相关表
SHOW TABLES LIKE 'kanban_%';

-- 应该看到以下表：
-- kanban_boards
-- kanban_statuses
-- kanban_tasks
-- kanban_permissions
-- kanban_task_history
-- kanban_labels
-- kanban_task_labels
-- kanban_comments
-- kanban_attachments
```

### 权限配置

#### 1. 用户权限设置

```php
// LocalSettings.php 中的权限配置示例

// 基础权限
$wgGroupPermissions['*']['kanbanboard-view'] = true;
$wgGroupPermissions['user']['kanbanboard-edit'] = true;
$wgGroupPermissions['sysop']['kanbanboard-admin'] = true;

// 高级权限配置
$wgGroupPermissions['bureaucrat']['kanbanboard-admin'] = true;
$wgGroupPermissions['interface-admin']['kanbanboard-admin'] = true;

// 自定义用户组权限
$wgGroupPermissions['project-manager']['kanbanboard-admin'] = true;
$wgGroupPermissions['project-member']['kanbanboard-edit'] = true;
```

#### 2. 命名空间权限

```php
// 设置看板命名空间权限
$wgNamespaceProtection[3000] = ['edit' => 'autoconfirmed'];
$wgNamespaceProtection[3001] = ['edit' => 'autoconfirmed'];
```

### 性能优化配置

#### 1. 缓存配置

```php
// LocalSettings.php 中的缓存配置

// 启用缓存
$wgMainCacheType = CACHE_MEMCACHED;
$wgMemCachedServers = ['127.0.0.1:11211'];

// 或者使用Redis
$wgMainCacheType = CACHE_REDIS;
$wgRedisServers = ['127.0.0.1:6379'];

// 启用对象缓存
$wgObjectCacheType = CACHE_MEMCACHED;
```

#### 2. 数据库优化

```sql
-- 为常用查询添加索引
ALTER TABLE kanban_tasks ADD INDEX idx_board_status_order (board_id, status_id, task_order);
ALTER TABLE kanban_task_history ADD INDEX idx_task_changed_at (task_id, changed_at);
ALTER TABLE kanban_permissions ADD INDEX idx_user_board (user_id, board_id);
```

## 测试安装

### 1. 功能测试

#### 创建测试看板

1. 访问 `http://your-wiki.com/wiki/特殊:KanbanBoard`
2. 点击"创建新看板"
3. 填写看板信息并保存
4. 验证看板是否创建成功

#### 测试任务管理

1. 在创建的看板中添加列
2. 创建任务
3. 测试拖拽功能
4. 编辑任务信息
5. 查看任务历史

#### 测试权限

1. 使用不同权限的用户登录
2. 测试查看、编辑、管理权限
3. 验证匿名用户访问限制

### 2. API测试

#### 基础API测试

```bash
# 测试API接口
curl -X GET "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json"

# 测试创建任务
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "board_id=1" \
  -d "column_id=1" \
  -d "title=测试任务" \
  -d "description=这是一个测试任务" \
  -d "priority=medium" \
  -d "format=json"
```

#### JavaScript API测试

```javascript
// 在浏览器控制台中测试
fetch('/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json')
  .then(response => response.json())
  .then(data => {
    console.log('API响应:', data);
    if (data.error) {
      console.error('API错误:', data.error);
    } else {
      console.log('看板数据:', data.board);
    }
  });
```

### 3. 性能测试

#### 加载测试

```bash
# 使用ab工具测试性能
ab -n 100 -c 10 "http://your-wiki.com/wiki/特殊:KanbanBoard"

# 测试API性能
ab -n 50 -c 5 "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json"
```

#### 数据库性能测试

```sql
-- 测试查询性能
EXPLAIN SELECT * FROM kanban_tasks WHERE board_id = 1 ORDER BY task_order;

-- 检查索引使用情况
SHOW INDEX FROM kanban_tasks;
```

## 故障排除

### 常见问题

#### 1. 扩展无法加载

**问题**: 访问特殊页面时出现"扩展未找到"错误

**解决方案**:
```bash
# 检查文件路径
ls -la /path/to/mediawiki/extensions/KanbanBoard/

# 检查extension.json语法
php -l /path/to/mediawiki/extensions/KanbanBoard/extension.json

# 检查LocalSettings.php配置
grep -n "KanbanBoard" /path/to/mediawiki/LocalSettings.php
```

#### 2. 数据库错误

**问题**: 数据库更新失败

**解决方案**:
```bash
# 检查数据库连接
php maintenance/checkDatabase.php

# 手动运行SQL脚本
mysql -u username -p database_name < extensions/KanbanBoard/sql/kanban_tables.sql

# 检查表结构
mysql -u username -p -e "DESCRIBE database_name.kanban_boards;"
```

#### 3. 权限问题

**问题**: 用户无法访问看板功能

**解决方案**:
```php
// 检查权限配置
$wgGroupPermissions['user']['kanbanboard-view'] = true;
$wgGroupPermissions['user']['kanbanboard-edit'] = true;

// 清除权限缓存
php maintenance/rebuildall.php
```

#### 4. JavaScript错误

**问题**: 前端功能不工作

**解决方案**:
1. 检查浏览器控制台错误
2. 验证资源文件加载
3. 检查API接口响应
4. 清除浏览器缓存

### 日志检查

#### MediaWiki日志

```bash
# 检查错误日志
tail -f /path/to/mediawiki/debug.log

# 检查访问日志
tail -f /var/log/apache2/access.log
```

#### 数据库日志

```sql
-- 检查慢查询日志
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- 检查错误日志
SHOW VARIABLES LIKE 'log_error';
```

## 卸载

如果需要卸载KanbanBoard扩展：

### 1. 备份数据

```bash
# 备份看板数据
mysqldump -u username -p database_name kanban_boards kanban_statuses kanban_tasks kanban_permissions > kanban_backup.sql
```

### 2. 移除配置

从 `LocalSettings.php` 中删除：

```php
// 删除这些行
wfLoadExtension( 'KanbanBoard' );
$wgKanbanBoardMaxColumns = 10;
$wgKanbanBoardMaxCardsPerColumn = 100;
$wgKanbanBoardAllowAnonymousEdit = false;
```

### 3. 删除文件

```bash
rm -rf /path/to/mediawiki/extensions/KanbanBoard/
```

### 4. 清理数据库（可选）

```sql
-- 删除看板相关表（注意：这会删除所有数据）
DROP TABLE IF EXISTS kanban_attachments;
DROP TABLE IF EXISTS kanban_comments;
DROP TABLE IF EXISTS kanban_task_labels;
DROP TABLE IF EXISTS kanban_labels;
DROP TABLE IF EXISTS kanban_task_history;
DROP TABLE IF EXISTS kanban_permissions;
DROP TABLE IF EXISTS kanban_tasks;
DROP TABLE IF EXISTS kanban_statuses;
DROP TABLE IF EXISTS kanban_boards;
```

### 5. 清除缓存

```bash
php maintenance/rebuildall.php
```

---

**注意**: 卸载前请务必备份重要数据！
