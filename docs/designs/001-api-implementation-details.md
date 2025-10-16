# 001-API实现详细设计

## API模块扩展

### 新增API方法

在 `ApiKanban.php` 中添加 `addcolumn` 方法：

```php
/**
 * 添加列到看板
 */
private function addColumn($params) {
    $boardId = (int)$params['board_id'];
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

### 权限检查方法

```php
/**
 * 检查用户是否有添加列的权限
 */
private function checkAddColumnPermission($userId, $boardId) {
    $db = $this->getDB();
    
    // 检查是否是看板所有者
    $isOwner = $db->selectField(
        'kanban_boards',
        'board_owner_id',
        ['board_id' => $boardId],
        __METHOD__
    ) == $userId;
    
    if ($isOwner) {
        return true;
    }
    
    // 检查用户权限
    $permission = $db->selectField(
        'kanban_permissions',
        'permission_level',
        [
            'board_id' => $boardId,
            'user_id' => $userId
        ],
        __METHOD__
    );
    
    return in_array($permission, ['board_admin', 'board_editor']);
}
```

### 输入验证方法

```php
/**
 * 验证列输入数据
 */
private function validateColumnInput($name, $color, $width, $maxCards, $wipLimit) {
    if (empty($name)) {
        $this->dieWithError('invalidname', 'addcolumn');
    }
    
    if (strlen($name) > 255) {
        $this->dieWithError('nametoolong', 'addcolumn');
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

### 数据库操作方法

```php
/**
 * 插入新列
 */
private function insertColumn($data) {
    $db = $this->getDB();
    
    $db->startAtomic(__METHOD__);
    
    try {
        $db->insert(
            'kanban_columns',
            $data,
            __METHOD__
        );
        
        $columnId = $db->insertId();
        
        $db->endAtomic(__METHOD__);
        
        return $columnId;
    } catch (Exception $e) {
        $db->rollback(__METHOD__);
        throw $e;
    }
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

### 列数限制检查

```php
/**
 * 检查列数限制
 */
private function checkColumnLimit($boardId) {
    $db = $this->getDB();
    
    // 获取看板的最大列数设置
    $maxColumns = $db->selectField(
        'kanban_boards',
        'board_max_columns',
        ['board_id' => $boardId],
        __METHOD__
    );
    
    if (!$maxColumns) {
        $maxColumns = 10; // 默认最大列数
    }
    
    // 获取当前列数
    $currentCount = $db->selectField(
        'kanban_columns',
        'COUNT(*)',
        ['board_id' => $boardId],
        __METHOD__
    );
    
    if ($currentCount >= $maxColumns) {
        $this->dieWithError('columnlimitexceeded', 'addcolumn');
    }
}
```

## 前端JavaScript实现

### 添加列对话框

```javascript
/**
 * 显示添加列对话框
 */
KanbanBoard.prototype.showAddColumnDialog = function() {
    var self = this;
    
    // 创建对话框HTML
    var dialogHtml = `
        <div class="kanban-add-column-dialog" id="addColumnDialog">
            <div class="dialog-overlay"></div>
            <div class="dialog-content">
                <div class="dialog-header">
                    <h3>添加新列</h3>
                    <button class="dialog-close">&times;</button>
                </div>
                <form class="kanban-column-form" id="addColumnForm">
                    <div class="form-group">
                        <label for="columnName">列名称 *</label>
                        <input type="text" id="columnName" name="name" required maxlength="255" placeholder="请输入列名称">
                    </div>
                    <div class="form-group">
                        <label for="columnDescription">列描述</label>
                        <textarea id="columnDescription" name="description" rows="3" placeholder="请输入列描述（可选）"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="columnColor">列颜色</label>
                        <div class="color-picker">
                            <input type="color" id="columnColor" name="color" value="#3498db">
                            <div class="color-presets">
                                <span class="color-preset" data-color="#e74c3c" title="红色"></span>
                                <span class="color-preset" data-color="#f39c12" title="橙色"></span>
                                <span class="color-preset" data-color="#f1c40f" title="黄色"></span>
                                <span class="color-preset" data-color="#27ae60" title="绿色"></span>
                                <span class="color-preset" data-color="#3498db" title="蓝色"></span>
                                <span class="color-preset" data-color="#9b59b6" title="紫色"></span>
                                <span class="color-preset" data-color="#e67e22" title="深橙色"></span>
                                <span class="color-preset" data-color="#95a5a6" title="灰色"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="columnPosition">插入位置</label>
                        <select id="columnPosition" name="position">
                            <option value="-1">末尾</option>
                            ${this.generatePositionOptions()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="columnWidth">列宽度</label>
                        <input type="number" id="columnWidth" name="width" min="200" max="800" value="300">
                        <span class="unit">像素</span>
                    </div>
                    <div class="form-group">
                        <label for="maxCards">最大卡片数</label>
                        <input type="number" id="maxCards" name="max_cards" min="0" max="1000" value="0">
                        <span class="help-text">0表示无限制</span>
                    </div>
                    <div class="form-group">
                        <label for="wipLimit">WIP限制</label>
                        <input type="number" id="wipLimit" name="wip_limit" min="0" max="100" value="0">
                        <span class="help-text">0表示无限制</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-cancel">取消</button>
                        <button type="submit" class="btn-primary">添加列</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // 添加到页面
    document.body.insertAdjacentHTML('beforeend', dialogHtml);
    
    // 绑定事件
    this.bindAddColumnEvents();
    
    // 显示对话框
    document.getElementById('addColumnDialog').style.display = 'block';
};

/**
 * 生成位置选项
 */
KanbanBoard.prototype.generatePositionOptions = function() {
    var options = '';
    this.columns.forEach(function(column, index) {
        options += `<option value="${index}">在"${column.data.column_name}"之前</option>`;
    });
    return options;
};

/**
 * 绑定添加列事件
 */
KanbanBoard.prototype.bindAddColumnEvents = function() {
    var self = this;
    var dialog = document.getElementById('addColumnDialog');
    var form = document.getElementById('addColumnForm');
    
    // 关闭对话框
    dialog.querySelector('.dialog-close').addEventListener('click', function() {
        self.hideAddColumnDialog();
    });
    
    dialog.querySelector('.dialog-overlay').addEventListener('click', function() {
        self.hideAddColumnDialog();
    });
    
    dialog.querySelector('.btn-cancel').addEventListener('click', function() {
        self.hideAddColumnDialog();
    });
    
    // 颜色预设点击
    dialog.querySelectorAll('.color-preset').forEach(function(preset) {
        preset.addEventListener('click', function() {
            var color = this.dataset.color;
            dialog.querySelector('#columnColor').value = color;
        });
    });
    
    // 表单提交
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        self.submitAddColumn(form);
    });
};

/**
 * 提交添加列表单
 */
KanbanBoard.prototype.submitAddColumn = function(form) {
    var self = this;
    var formData = new FormData(form);
    var params = {
        action: 'kanban',
        kanban_action: 'addcolumn',
        board_id: this.boardId
    };
    
    // 添加表单数据
    for (var [key, value] of formData.entries()) {
        params[key] = value;
    }
    
    // 显示加载状态
    var submitBtn = form.querySelector('.btn-primary');
    var originalText = submitBtn.textContent;
    submitBtn.textContent = '添加中...';
    submitBtn.disabled = true;
    
    // 发送API请求
    this.api.post(params).done(function(data) {
        self.hideAddColumnDialog();
        self.loadBoard(); // 重新加载看板
        self.showSuccessMessage('列添加成功！');
    }).fail(function(error) {
        self.showErrorMessage('添加列失败: ' + (error.error || '未知错误'));
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
};

/**
 * 隐藏添加列对话框
 */
KanbanBoard.prototype.hideAddColumnDialog = function() {
    var dialog = document.getElementById('addColumnDialog');
    if (dialog) {
        dialog.remove();
    }
};
```

## CSS样式

### 对话框样式

```css
/* 添加列对话框样式 */
.kanban-add-column-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: none;
}

.dialog-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.dialog-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.dialog-header h3 {
    margin: 0;
    color: #333;
}

.dialog-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dialog-close:hover {
    color: #333;
}

.kanban-column-form {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.color-picker {
    display: flex;
    align-items: center;
    gap: 10px;
}

.color-picker input[type="color"] {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.color-presets {
    display: flex;
    gap: 5px;
}

.color-preset {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}

.color-preset:hover {
    border-color: #333;
}

.help-text {
    font-size: 12px;
    color: #666;
    margin-left: 5px;
}

.unit {
    font-size: 12px;
    color: #666;
    margin-left: 5px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn-cancel,
.btn-primary {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

.btn-cancel {
    background: #f8f9fa;
    color: #666;
}

.btn-cancel:hover {
    background: #e9ecef;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-primary:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}
```

## 错误消息国际化

### 英文错误消息

```json
{
    "kanbanboard-error-permissiondenied": "You don't have permission to add columns to this board",
    "kanbanboard-error-boardnotfound": "Board not found",
    "kanbanboard-error-invalidname": "Column name cannot be empty",
    "kanbanboard-error-nametoolong": "Column name is too long (maximum 255 characters)",
    "kanbanboard-error-invalidcolor": "Invalid color format",
    "kanbanboard-error-invalidwidth": "Column width must be between 200 and 800 pixels",
    "kanbanboard-error-invalidmaxcards": "Maximum cards must be between 0 and 1000",
    "kanbanboard-error-invalidwiplimit": "WIP limit must be between 0 and 100",
    "kanbanboard-error-columnlimitexceeded": "Maximum number of columns exceeded",
    "kanbanboard-success-columnadded": "Column added successfully"
}
```

### 中文错误消息

```json
{
    "kanbanboard-error-permissiondenied": "您没有权限在此看板中添加列",
    "kanbanboard-error-boardnotfound": "看板不存在",
    "kanbanboard-error-invalidname": "列名称不能为空",
    "kanbanboard-error-nametoolong": "列名称过长（最多255个字符）",
    "kanbanboard-error-invalidcolor": "颜色格式无效",
    "kanbanboard-error-invalidwidth": "列宽度必须在200到800像素之间",
    "kanbanboard-error-invalidmaxcards": "最大卡片数必须在0到1000之间",
    "kanbanboard-error-invalidwiplimit": "WIP限制必须在0到100之间",
    "kanbanboard-error-columnlimitexceeded": "超出最大列数限制",
    "kanbanboard-success-columnadded": "列添加成功"
}
```

## 测试用例

### API测试用例

```php
// 测试正常添加列
public function testAddColumnSuccess() {
    $params = [
        'kanban_action' => 'addcolumn',
        'board_id' => 1,
        'name' => '测试列',
        'description' => '测试描述',
        'color' => '#e74c3c',
        'position' => -1
    ];
    
    $result = $this->doApiRequest($params);
    $this->assertEquals('success', $result['addcolumn']['result']);
    $this->assertArrayHasKey('column', $result['addcolumn']);
}

// 测试权限验证
public function testAddColumnPermissionDenied() {
    $params = [
        'kanban_action' => 'addcolumn',
        'board_id' => 1,
        'name' => '测试列'
    ];
    
    // 使用无权限用户
    $this->setUser('viewer');
    $result = $this->doApiRequest($params);
    $this->assertEquals('permissiondenied', $result['error']['code']);
}

// 测试输入验证
public function testAddColumnInvalidInput() {
    $params = [
        'kanban_action' => 'addcolumn',
        'board_id' => 1,
        'name' => '', // 空名称
        'color' => 'invalid-color'
    ];
    
    $result = $this->doApiRequest($params);
    $this->assertEquals('invalidname', $result['error']['code']);
}
```

## 总结

这个详细的API实现设计涵盖了：

1. **完整的API方法实现**
2. **权限检查和验证**
3. **数据库操作和事务处理**
4. **前端交互和用户体验**
5. **错误处理和国际化**
6. **测试用例和样式设计**

通过这个设计，可以实现一个功能完整、用户友好的添加列功能。


