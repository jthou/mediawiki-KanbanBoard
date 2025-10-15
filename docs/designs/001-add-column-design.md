# 001-添加列功能设计文档

## 概述

本文档详细设计MediaWiki Kanban Board扩展中的"添加列"功能，包括前端交互、后端API、数据库设计和权限控制。

## 功能需求

### 基本需求
1. 用户可以在现有看板中添加新列
2. 列有名称、颜色、顺序等属性
3. 支持权限控制（只有有权限的用户才能添加列）
4. 支持实时更新（添加后立即显示）

### 高级需求
1. 支持拖拽调整列顺序
2. 支持列模板（预设常用列）
3. 支持列的最大数量限制
4. 支持列的默认设置

## 数据库设计

### 当前表结构分析

```sql
-- kanban_columns 表（已存在）
CREATE TABLE kanban_columns (
    column_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    board_id INT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_description TEXT,
    column_color VARCHAR(7) DEFAULT '#3498db',
    column_order INT UNSIGNED NOT NULL DEFAULT 0,
    column_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    column_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES kanban_boards(board_id) ON DELETE CASCADE
);
```

### 需要优化的字段

1. **column_order**: 当前是INT，需要确保顺序的连续性
2. **column_color**: 当前是VARCHAR(7)，需要添加颜色验证
3. **column_width**: 新增字段，支持自定义列宽
4. **column_max_cards**: 新增字段，限制列中最大卡片数
5. **column_is_collapsed**: 新增字段，支持列的折叠/展开状态

### 优化后的表结构

```sql
-- 优化后的 kanban_columns 表
ALTER TABLE kanban_columns 
ADD COLUMN column_width INT UNSIGNED DEFAULT 300 COMMENT '列宽度(像素)',
ADD COLUMN column_max_cards INT UNSIGNED DEFAULT 0 COMMENT '最大卡片数(0表示无限制)',
ADD COLUMN column_is_collapsed TINYINT(1) DEFAULT 0 COMMENT '是否折叠',
ADD COLUMN column_wip_limit INT UNSIGNED DEFAULT 0 COMMENT 'WIP限制',
ADD INDEX idx_board_order (board_id, column_order);
```

## API设计

### 端点设计

```
POST /api.php?action=kanban&kanban_action=addcolumn
```

### 请求参数

| 参数名 | 类型 | 必需 | 默认值 | 说明 |
|--------|------|------|--------|------|
| board_id | integer | 是 | - | 看板ID |
| name | string | 是 | - | 列名称 |
| description | string | 否 | '' | 列描述 |
| color | string | 否 | '#3498db' | 列颜色(HEX) |
| position | integer | 否 | -1 | 插入位置(-1表示末尾) |
| width | integer | 否 | 300 | 列宽度 |
| max_cards | integer | 否 | 0 | 最大卡片数 |
| wip_limit | integer | 否 | 0 | WIP限制 |

### 响应格式

#### 成功响应
```json
{
    "addcolumn": {
        "result": "success",
        "column": {
            "column_id": 123,
            "board_id": 1,
            "column_name": "新列",
            "column_description": "列描述",
            "column_color": "#e74c3c",
            "column_order": 3,
            "column_width": 300,
            "column_max_cards": 0,
            "column_wip_limit": 0,
            "column_is_collapsed": false,
            "column_created_at": "2024-01-15 10:30:00",
            "column_updated_at": "2024-01-15 10:30:00"
        }
    }
}
```

#### 错误响应
```json
{
    "error": {
        "code": "permissiondenied",
        "info": "You don't have permission to add columns to this board"
    }
}
```

## 权限设计

### 权限级别

1. **board_owner**: 看板所有者，拥有所有权限
2. **board_admin**: 看板管理员，可以添加/删除列
3. **board_editor**: 看板编辑者，可以添加列但不能删除
4. **board_viewer**: 看板查看者，只能查看

### 权限检查逻辑

```php
// 检查用户是否有添加列的权限
private function checkAddColumnPermission($userId, $boardId) {
    $permissions = $this->getUserBoardPermissions($userId, $boardId);
    
    return in_array($permissions, ['board_owner', 'board_admin', 'board_editor']);
}
```

## 前端设计

### 用户交互流程

1. **触发添加列**:
   - 点击"添加列"按钮
   - 显示添加列对话框

2. **填写列信息**:
   - 列名称（必填）
   - 列描述（可选）
   - 列颜色（颜色选择器）
   - 插入位置（拖拽或选择）

3. **提交和验证**:
   - 前端验证
   - 发送API请求
   - 处理响应

4. **更新界面**:
   - 成功：添加新列到看板
   - 失败：显示错误信息

### UI组件设计

#### 添加列对话框
```html
<div class="kanban-add-column-dialog">
    <h3>添加新列</h3>
    <form class="kanban-column-form">
        <div class="form-group">
            <label>列名称 *</label>
            <input type="text" name="name" required maxlength="255">
        </div>
        <div class="form-group">
            <label>列描述</label>
            <textarea name="description" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>列颜色</label>
            <div class="color-picker">
                <input type="color" name="color" value="#3498db">
                <div class="color-presets">
                    <span class="color-preset" data-color="#e74c3c"></span>
                    <span class="color-preset" data-color="#f39c12"></span>
                    <span class="color-preset" data-color="#27ae60"></span>
                    <span class="color-preset" data-color="#3498db"></span>
                    <span class="color-preset" data-color="#9b59b6"></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>插入位置</label>
            <select name="position">
                <option value="-1">末尾</option>
                <option value="0">开头</option>
                <!-- 动态生成其他列的位置选项 -->
            </select>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel">取消</button>
            <button type="submit" class="btn-primary">添加列</button>
        </div>
    </form>
</div>
```

## 后端实现

### API模块实现

```php
/**
 * 添加列到看板
 */
private function addColumn($params) {
    $boardId = $params['board_id'];
    $name = trim($params['name']);
    $description = trim($params['description'] ?? '');
    $color = $params['color'] ?? '#3498db';
    $position = (int)($params['position'] ?? -1);
    $width = (int)($params['width'] ?? 300);
    $maxCards = (int)($params['max_cards'] ?? 0);
    $wipLimit = (int)($params['wip_limit'] ?? 0);
    
    // 验证输入
    $this->validateColumnInput($name, $color, $width, $maxCards, $wipLimit);
    
    // 检查权限
    $user = $this->getUser();
    if (!$this->checkAddColumnPermission($user->getId(), $boardId)) {
        $this->dieWithError('permissiondenied', 'addcolumn');
    }
    
    // 检查看板是否存在
    $board = $this->getBoard($boardId);
    if (!$board) {
        $this->dieWithError('boardnotfound', 'addcolumn');
    }
    
    // 检查列数限制
    $this->checkColumnLimit($boardId);
    
    // 计算插入位置
    $order = $this->calculateColumnOrder($boardId, $position);
    
    // 插入新列
    $columnId = $this->insertColumn([
        'board_id' => $boardId,
        'column_name' => $name,
        'column_description' => $description,
        'column_color' => $color,
        'column_order' => $order,
        'column_width' => $width,
        'column_max_cards' => $maxCards,
        'column_wip_limit' => $wipLimit,
        'column_creator_id' => $user->getId()
    ]);
    
    // 返回新列信息
    $column = $this->getColumn($columnId);
    $this->getResult()->addValue(null, 'column', $column);
}
```

### 数据库操作

```php
/**
 * 插入新列
 */
private function insertColumn($data) {
    $db = $this->getDB();
    
    $db->insert(
        'kanban_columns',
        $data,
        __METHOD__
    );
    
    return $db->insertId();
}

/**
 * 计算列的顺序
 */
private function calculateColumnOrder($boardId, $position) {
    $db = $this->getDB();
    
    if ($position === -1) {
        // 插入到末尾
        $maxOrder = $db->selectField(
            'kanban_columns',
            'MAX(column_order)',
            ['board_id' => $boardId],
            __METHOD__
        );
        return ($maxOrder ?? 0) + 1;
    } else {
        // 插入到指定位置，需要调整其他列的顺序
        $this->adjustColumnOrders($boardId, $position);
        return $position;
    }
}

/**
 * 调整列顺序
 */
private function adjustColumnOrders($boardId, $insertPosition) {
    $db = $this->getDB();
    
    // 将插入位置及之后的列顺序+1
    $db->update(
        'kanban_columns',
        ['column_order = column_order + 1'],
        [
            'board_id' => $boardId,
            'column_order >= ' . $insertPosition
        ],
        __METHOD__
    );
}
```

## 错误处理

### 常见错误情况

1. **权限不足**: `permissiondenied`
2. **看板不存在**: `boardnotfound`
3. **列名称为空**: `invalidname`
4. **颜色格式错误**: `invalidcolor`
5. **超出列数限制**: `columnlimitexceeded`
6. **数据库错误**: `databaseerror`

### 错误处理策略

```php
private function validateColumnInput($name, $color, $width, $maxCards, $wipLimit) {
    if (empty($name)) {
        $this->dieWithError('invalidname', 'addcolumn');
    }
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        $this->dieWithError('invalidcolor', 'addcolumn');
    }
    
    if ($width < 200 || $width > 800) {
        $this->dieWithError('invalidwidth', 'addcolumn');
    }
    
    if ($maxCards < 0 || $maxCards > 1000) {
        $this->dieWithError('invalidmaxcards', 'addcolumn');
    }
    
    if ($wipLimit < 0 || $wipLimit > 100) {
        $this->dieWithError('invalidwiplimit', 'addcolumn');
    }
}
```

## 测试计划

### 单元测试

1. **API测试**:
   - 正常添加列
   - 权限验证
   - 输入验证
   - 错误处理

2. **数据库测试**:
   - 列插入
   - 顺序调整
   - 约束检查

### 集成测试

1. **前端集成**:
   - 对话框显示
   - 表单提交
   - 界面更新

2. **权限集成**:
   - 不同用户权限
   - 权限变更

## 性能考虑

### 数据库优化

1. **索引优化**:
   ```sql
   CREATE INDEX idx_board_order ON kanban_columns(board_id, column_order);
   ```

2. **查询优化**:
   - 使用事务处理顺序调整
   - 批量更新减少数据库调用

### 前端优化

1. **懒加载**: 列内容按需加载
2. **缓存**: 列模板缓存
3. **防抖**: 防止重复提交

## 扩展性考虑

### 未来功能

1. **列模板**: 预设常用列配置
2. **列复制**: 复制现有列
3. **列导入**: 从其他看板导入列
4. **列统计**: 列的使用统计

### 插件化设计

```php
// 列创建钩子
$this->getHookRunner()->onKanbanColumnCreate($columnId, $columnData);

// 列更新钩子  
$this->getHookRunner()->onKanbanColumnUpdate($columnId, $oldData, $newData);
```

## 总结

添加列功能的设计涵盖了前端交互、后端API、数据库设计、权限控制、错误处理和性能优化等各个方面。通过模块化的设计和完善的错误处理，确保功能的稳定性和可扩展性。

### 实现优先级

1. **P0**: 基本添加列功能
2. **P1**: 权限控制和输入验证
3. **P2**: 位置调整和顺序管理
4. **P3**: 高级功能（WIP限制、列宽等）
5. **P4**: 扩展功能（模板、复制等）
