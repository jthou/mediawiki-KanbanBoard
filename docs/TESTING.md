# KanbanBoard 测试指南

## 测试概述

本指南提供了KanbanBoard扩展的完整测试方案，包括功能测试、性能测试、安全测试和兼容性测试。

## 测试环境准备

### 1. 测试环境要求

```bash
# 基础环境
- MediaWiki 1.42+
- PHP 8.1+
- MySQL 5.7+ 或 MariaDB 10.3+
- 现代浏览器（Chrome 60+, Firefox 60+, Safari 12+, Edge 79+）

# 测试工具
- curl (API测试)
- ab (性能测试)
- Selenium WebDriver (自动化测试)
- PHPUnit (单元测试)
```

### 2. 测试数据准备

```sql
-- 创建测试用户
INSERT INTO user (user_name, user_password, user_email) VALUES 
('testuser1', 'password_hash', 'test1@example.com'),
('testuser2', 'password_hash', 'test2@example.com'),
('testadmin', 'password_hash', 'admin@example.com');

-- 创建测试看板
INSERT INTO kanban_boards (kanban_name, board_name, board_description, board_owner_id) VALUES 
('test-board-1', '测试看板1', '用于测试的看板', 1),
('test-board-2', '测试看板2', '另一个测试看板', 2);
```

## 功能测试

### 1. 看板管理测试

#### 创建看板测试

**测试步骤**:
1. 访问 `特殊:KanbanBoard`
2. 点击"创建新看板"
3. 填写看板信息：
   - 看板名称: "功能测试看板"
   - 描述: "用于功能测试的看板"
   - 可见性: "公开"
4. 点击"创建看板"

**预期结果**:
- 看板创建成功
- 显示成功消息
- 重定向到看板页面
- 数据库中创建对应记录

**验证SQL**:
```sql
SELECT * FROM kanban_boards WHERE kanban_name = '功能测试看板';
```

#### 编辑看板测试

**测试步骤**:
1. 在看板页面点击"设置"
2. 修改看板名称和描述
3. 保存更改

**预期结果**:
- 更改保存成功
- 页面显示更新后的信息
- 数据库记录更新

#### 删除看板测试

**测试步骤**:
1. 在看板设置中点击"删除看板"
2. 确认删除操作

**预期结果**:
- 看板删除成功
- 相关数据被清理
- 重定向到看板列表

### 2. 列管理测试

#### 添加列测试

**测试步骤**:
1. 在看板页面点击"添加列"
2. 输入列名称: "进行中"
3. 选择列颜色
4. 点击"添加"

**预期结果**:
- 新列添加到看板
- 列显示在正确位置
- 数据库创建对应记录

**验证SQL**:
```sql
SELECT * FROM kanban_statuses WHERE status_name = '进行中';
```

#### 重排序列测试

**测试步骤**:
1. 拖拽列标题到新位置
2. 释放鼠标

**预期结果**:
- 列顺序更新
- 数据库中的order字段更新
- 页面显示新的列顺序

#### 删除列测试

**测试步骤**:
1. 点击列菜单
2. 选择"删除列"
3. 选择处理列中任务的方式
4. 确认删除

**预期结果**:
- 列删除成功
- 任务按选择的方式处理
- 数据库记录更新

### 3. 任务管理测试

#### 创建任务测试

**测试步骤**:
1. 点击列中的"添加任务"
2. 填写任务信息：
   - 标题: "测试任务"
   - 描述: "这是一个测试任务"
   - 优先级: "高"
   - 截止日期: "2024-12-31"
3. 点击"创建任务"

**预期结果**:
- 任务创建成功
- 任务显示在对应列中
- 数据库创建对应记录
- 任务历史记录创建

**验证SQL**:
```sql
SELECT * FROM kanban_tasks WHERE title = '测试任务';
SELECT * FROM kanban_task_history WHERE change_type = 'create';
```

#### 编辑任务测试

**测试步骤**:
1. 点击任务卡片
2. 修改任务信息
3. 点击"保存"

**预期结果**:
- 任务信息更新
- 页面显示更新后的信息
- 数据库记录更新
- 历史记录创建

#### 拖拽任务测试

**测试步骤**:
1. 拖拽任务到不同列
2. 释放鼠标

**预期结果**:
- 任务移动到新列
- 数据库中的status_id更新
- 历史记录创建移动记录

#### 删除任务测试

**测试步骤**:
1. 点击任务卡片
2. 点击"删除任务"
3. 确认删除

**预期结果**:
- 任务删除成功
- 任务从页面消失
- 数据库记录软删除
- 历史记录创建删除记录

### 4. 权限测试

#### 查看权限测试

**测试步骤**:
1. 使用普通用户登录
2. 访问公开看板
3. 访问私有看板

**预期结果**:
- 可以查看有权限的看板
- 无法查看无权限的看板
- 显示适当的错误消息

#### 编辑权限测试

**测试步骤**:
1. 使用普通用户登录
2. 尝试编辑任务
3. 尝试删除任务

**预期结果**:
- 有权限的操作成功
- 无权限的操作被拒绝
- 显示权限错误消息

#### 管理权限测试

**测试步骤**:
1. 使用管理员用户登录
2. 尝试删除看板
3. 尝试修改看板设置

**预期结果**:
- 管理操作成功
- 权限检查通过

### 5. 搜索功能测试

#### 看板搜索测试

**测试步骤**:
1. 在MediaWiki搜索框中输入看板名称
2. 查看搜索结果

**预期结果**:
- 显示匹配的看板
- 搜索结果包含看板信息

#### 任务搜索测试

**测试步骤**:
1. 搜索任务标题
2. 搜索任务描述内容

**预期结果**:
- 显示匹配的任务
- 搜索结果包含任务信息

## API测试

### 1. 基础API测试

#### 获取看板数据

```bash
# 测试getboard API
curl -X GET "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json"

# 预期响应
{
  "board": {
    "board_id": 1,
    "board_name": "测试看板",
    "columns": [...]
  }
}
```

#### 创建任务

```bash
# 测试createtask API
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "board_id=1" \
  -d "column_id=1" \
  -d "title=API测试任务" \
  -d "description=通过API创建的任务" \
  -d "priority=medium" \
  -d "format=json"

# 预期响应
{
  "result": "success",
  "task_id": 123,
  "message": "Task created successfully"
}
```

#### 更新任务

```bash
# 测试updatetask API
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=updatetask" \
  -d "task_id=123" \
  -d "title=更新的任务标题" \
  -d "description=更新的任务描述" \
  -d "priority=high" \
  -d "format=json"
```

#### 获取任务历史

```bash
# 测试gethistory API
curl -X GET "http://your-wiki.com/api.php?action=kanban&kanban_action=gethistory&task_id=123&format=json"

# 预期响应
{
  "result": "success",
  "history": [
    {
      "history_id": 1,
      "field_name": "title",
      "old_value": "原标题",
      "new_value": "新标题",
      "changed_at": "2024-01-15 10:30:00",
      "change_type": "update"
    }
  ]
}
```

### 2. 错误处理测试

#### 无效参数测试

```bash
# 测试无效的board_id
curl -X GET "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=99999&format=json"

# 预期响应
{
  "error": {
    "code": "boardnotfound",
    "info": "Board not found"
  }
}
```

#### 权限错误测试

```bash
# 使用无权限用户测试
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "board_id=1" \
  -d "column_id=1" \
  -d "title=无权限任务" \
  -d "format=json"

# 预期响应
{
  "error": {
    "code": "permissiondenied",
    "info": "Permission denied"
  }
}
```

## 性能测试

### 1. 加载性能测试

#### 页面加载测试

```bash
# 使用ab工具测试页面加载性能
ab -n 100 -c 10 "http://your-wiki.com/wiki/特殊:KanbanBoard"

# 预期结果
- 平均响应时间 < 2秒
- 95%请求响应时间 < 5秒
- 错误率 < 1%
```

#### API性能测试

```bash
# 测试API响应性能
ab -n 50 -c 5 "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json"

# 预期结果
- 平均响应时间 < 500ms
- 95%请求响应时间 < 1秒
- 错误率 < 1%
```

### 2. 数据库性能测试

#### 查询性能测试

```sql
-- 测试看板数据查询性能
EXPLAIN SELECT * FROM kanban_tasks WHERE board_id = 1 ORDER BY task_order;

-- 预期结果
- 使用索引
- 查询时间 < 100ms
```

#### 大数据量测试

```sql
-- 创建大量测试数据
INSERT INTO kanban_tasks (board_id, status_id, title, description, priority, task_order)
SELECT 1, 1, CONCAT('任务', i), '测试任务描述', 'medium', i
FROM (
  SELECT @row := @row + 1 AS i
  FROM (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) t1,
       (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) t2,
       (SELECT @row := 0) r
) numbers
LIMIT 1000;

-- 测试大数据量查询性能
SELECT COUNT(*) FROM kanban_tasks WHERE board_id = 1;
```

### 3. 前端性能测试

#### JavaScript性能测试

```javascript
// 在浏览器控制台中测试
console.time('看板加载');
// 执行看板加载操作
console.timeEnd('看板加载');

// 预期结果
- 看板加载时间 < 1秒
- 拖拽响应时间 < 100ms
```

#### 内存使用测试

```javascript
// 测试内存使用
console.log('初始内存:', performance.memory.usedJSHeapSize);
// 执行操作
console.log('操作后内存:', performance.memory.usedJSHeapSize);

// 预期结果
- 内存使用合理
- 无明显内存泄漏
```

## 安全测试

### 1. 输入验证测试

#### SQL注入测试

```bash
# 测试SQL注入防护
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "board_id=1" \
  -d "column_id=1" \
  -d "title='; DROP TABLE kanban_tasks; --" \
  -d "format=json"

# 预期结果
- 输入被正确转义
- 数据库不受影响
```

#### XSS测试

```bash
# 测试XSS防护
curl -X POST "http://your-wiki.com/api.php" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "board_id=1" \
  -d "column_id=1" \
  -d "title=<script>alert('XSS')</script>" \
  -d "format=json"

# 预期结果
- 脚本标签被转义
- 不执行恶意代码
```

### 2. 权限安全测试

#### 越权访问测试

```bash
# 测试访问其他用户的看板
curl -X GET "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=2&format=json"

# 预期结果
- 权限检查通过
- 无权限访问被拒绝
```

#### 会话安全测试

```bash
# 测试会话劫持防护
# 使用无效的会话token
curl -X POST "http://your-wiki.com/api.php" \
  -H "Cookie: invalid_session_token" \
  -d "action=kanban" \
  -d "kanban_action=createtask" \
  -d "format=json"

# 预期结果
- 无效会话被拒绝
- 需要重新认证
```

## 兼容性测试

### 1. 浏览器兼容性测试

#### Chrome测试

```bash
# 使用Chrome测试
google-chrome --headless --disable-gpu --dump-dom "http://your-wiki.com/wiki/特殊:KanbanBoard"
```

#### Firefox测试

```bash
# 使用Firefox测试
firefox --headless "http://your-wiki.com/wiki/特殊:KanbanBoard"
```

#### Safari测试

```bash
# 使用Safari测试
# 需要在macOS上手动测试
```

### 2. 移动端测试

#### 响应式设计测试

```javascript
// 测试不同屏幕尺寸
const viewports = [
  { width: 320, height: 568 },   // iPhone 5
  { width: 375, height: 667 },   // iPhone 6
  { width: 414, height: 736 },    // iPhone 6 Plus
  { width: 768, height: 1024 },   // iPad
  { width: 1024, height: 768 }    // Desktop
];

viewports.forEach(viewport => {
  // 设置视口大小
  // 测试界面布局
});
```

### 3. 数据库兼容性测试

#### MySQL测试

```sql
-- 测试MySQL特定功能
SELECT VERSION();
SHOW ENGINES;
```

#### MariaDB测试

```sql
-- 测试MariaDB特定功能
SELECT VERSION();
SHOW ENGINES;
```

## 自动化测试

### 1. PHPUnit单元测试

```php
<?php
// tests/phpunit/ApiKanbanTest.php

use PHPUnit\Framework\TestCase;

class ApiKanbanTest extends TestCase
{
    public function testGetBoard()
    {
        // 测试获取看板数据
        $api = new ApiKanban();
        $result = $api->getBoard(['board_id' => 1]);
        
        $this->assertArrayHasKey('board', $result);
        $this->assertEquals(1, $result['board']['board_id']);
    }
    
    public function testCreateTask()
    {
        // 测试创建任务
        $api = new ApiKanban();
        $result = $api->createTask([
            'board_id' => 1,
            'column_id' => 1,
            'title' => '测试任务',
            'description' => '测试描述'
        ]);
        
        $this->assertEquals('success', $result['result']);
        $this->assertArrayHasKey('task_id', $result);
    }
}
```

### 2. Selenium自动化测试

```python
# tests/selenium/test_kanban.py

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

def test_create_board():
    driver = webdriver.Chrome()
    driver.get("http://your-wiki.com/wiki/特殊:KanbanBoard")
    
    # 点击创建看板按钮
    create_button = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.ID, "create-board-btn"))
    )
    create_button.click()
    
    # 填写看板信息
    name_input = driver.find_element(By.ID, "board-name")
    name_input.send_keys("自动化测试看板")
    
    # 提交表单
    submit_button = driver.find_element(By.ID, "submit-board")
    submit_button.click()
    
    # 验证创建成功
    success_message = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CLASS_NAME, "success-message"))
    )
    assert "创建成功" in success_message.text
    
    driver.quit()
```

## 测试报告

### 1. 测试结果记录

```markdown
# KanbanBoard 测试报告

## 测试环境
- MediaWiki版本: 1.42.0
- PHP版本: 8.1.0
- 数据库: MySQL 8.0
- 浏览器: Chrome 120

## 测试结果

### 功能测试
- ✅ 看板管理: 通过
- ✅ 列管理: 通过
- ✅ 任务管理: 通过
- ✅ 权限控制: 通过
- ✅ 搜索功能: 通过

### 性能测试
- ✅ 页面加载: 平均1.2秒
- ✅ API响应: 平均300ms
- ✅ 数据库查询: 平均50ms

### 安全测试
- ✅ SQL注入防护: 通过
- ✅ XSS防护: 通过
- ✅ 权限控制: 通过

### 兼容性测试
- ✅ Chrome: 通过
- ✅ Firefox: 通过
- ✅ Safari: 通过
- ✅ 移动端: 通过

## 问题记录
- 无重大问题发现
- 建议优化大数据量查询性能
```

### 2. 持续集成测试

```yaml
# .github/workflows/test.yml

name: KanbanBoard Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
    
    - name: Install dependencies
      run: composer install
    
    - name: Run tests
      run: vendor/bin/phpunit tests/
    
    - name: Run linting
      run: vendor/bin/phpcs --standard=PSR12 includes/
```

---

**测试完成标准**:
- 所有功能测试通过
- 性能指标达标
- 安全测试通过
- 兼容性测试通过
- 无严重bug发现
