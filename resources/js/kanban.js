/**
 * MediaWiki Kanban Board Extension - Enhanced JavaScript
 * 
 * @file
 * @ingroup Extensions
 */

( function () {
    'use strict';

    /**
     * 看板主类
     */
    function KanbanBoard( element ) {
        this.element = element;
        this.boardId = element.dataset.boardId;
        this.readOnly = element.dataset.readonly === 'true';
        this.columns = [];
        this.api = new mw.Api();
        
        this.init();
    }

    KanbanBoard.prototype.init = function() {
        this.loadBoard();
        this.bindEvents();
    };

    /**
     * 加载看板数据
     */
    KanbanBoard.prototype.loadBoard = function() {
        var self = this;
        
        this.showLoading();
        
        // 尝试从API加载数据，如果失败则使用静态数据
        this.api.post({
            action: 'kanban',
            kanban_action: 'getboard',
            board_id: this.boardId
        }).done(function(data) {
            if (data.board) {
                self.renderBoard(data.board);
            } else {
                self.showError('看板数据格式错误');
            }
            self.hideLoading();
        }).fail(function(error) {
            console.warn('API加载失败，使用静态数据:', error);
            // 使用静态测试数据
            self.loadStaticData();
        });
    };

    /**
     * 加载静态测试数据
     */
    KanbanBoard.prototype.loadStaticData = function() {
        var self = this;
        
        // 模拟API调用延迟
        setTimeout(function() {
            var boardData = {
                board_id: 1,
                board_name: '测试看板',
                board_description: '这是一个测试看板',
                board_owner_id: 1,
                board_permissions: 'public',
                columns: [
                    {
                        column_id: 1,
                        column_name: '待办',
                        column_color: '#e74c3c',
                        column_order: 1,
                        column_width: 300,
                        column_max_cards: 0,
                        column_wip_limit: 0,
                        column_is_collapsed: false,
                        cards: [
                            {
                                card_id: 1,
                                card_title: '任务1',
                                card_description: '这是第一个任务',
                                card_priority: 'medium',
                                card_order: 1
                            },
                            {
                                card_id: 2,
                                card_title: '任务2',
                                card_description: '这是第二个任务',
                                card_priority: 'high',
                                card_order: 2
                            }
                        ]
                    },
                    {
                        column_id: 2,
                        column_name: '进行中',
                        column_color: '#f39c12',
                        column_order: 2,
                        column_width: 300,
                        column_max_cards: 0,
                        column_wip_limit: 5,
                        column_is_collapsed: false,
                        cards: [
                            {
                                card_id: 3,
                                card_title: '进行中的任务',
                                card_description: '正在进行的任务',
                                card_priority: 'medium',
                                card_order: 1
                            }
                        ]
                    },
                    {
                        column_id: 3,
                        column_name: '已完成',
                        column_color: '#27ae60',
                        column_order: 3,
                        column_width: 300,
                        column_max_cards: 0,
                        column_wip_limit: 0,
                        column_is_collapsed: false,
                        cards: []
                    }
                ]
            };
            
            self.renderBoard(boardData);
            self.hideLoading();
        }, 500);
    };

    /**
     * 渲染看板
     */
    KanbanBoard.prototype.renderBoard = function(boardData) {
        var self = this;
        
        this.element.innerHTML = '';
        
        // 创建看板头部
        var header = this.createHeader(boardData);
        this.element.appendChild(header);
        
        // 创建列容器
        var columnsContainer = document.createElement('div');
        columnsContainer.className = 'kanban-columns';
        
        // 渲染每一列
        if (boardData.columns && boardData.columns.length > 0) {
            boardData.columns.forEach(function(columnData) {
                var column = new KanbanColumn(columnData, self);
                self.columns.push(column);
                columnsContainer.appendChild(column.element);
            });
        }
        
        // 如果不是只读模式，在列容器中添加新列按钮
        if (!this.readOnly) {
            var addColumnBtn = this.createAddColumnButton();
            columnsContainer.appendChild(addColumnBtn);
        } else if (!boardData.columns || boardData.columns.length === 0) {
            // 如果是只读模式且没有列，显示提示
            var noColumnsMsg = document.createElement('div');
            noColumnsMsg.className = 'kanban-no-columns';
            noColumnsMsg.textContent = '暂无列';
            columnsContainer.appendChild(noColumnsMsg);
        }
        
        this.element.appendChild(columnsContainer);
        
        // 绑定拖拽事件
        this.bindColumnDragEvents();
    };

    /**
     * 创建看板头部
     */
    KanbanBoard.prototype.createHeader = function(boardData) {
        var header = document.createElement('div');
        header.className = 'kanban-header';
        
        var title = document.createElement('h2');
        title.textContent = boardData.board_name || '未命名看板';
        header.appendChild(title);
        
        if (boardData.board_description) {
            var description = document.createElement('p');
            description.textContent = boardData.board_description;
            header.appendChild(description);
        }
        
        return header;
    };

    /**
     * 创建添加列按钮
     */
    KanbanBoard.prototype.createAddColumnButton = function() {
        var self = this;
        var button = document.createElement('button');
        button.className = 'kanban-add-column-btn';
        
        // 创建按钮内容
        var icon = document.createElement('div');
        icon.style.fontSize = '32px';
        icon.style.marginBottom = '8px';
        icon.textContent = '+';
        
        var text = document.createElement('div');
        text.textContent = '添加列';
        
        button.appendChild(icon);
        button.appendChild(text);
        
        button.addEventListener('click', function() {
            self.showAddColumnDialog();
        });
        
        return button;
    };

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
                        <!-- WIP限制字段已隐藏 -->
                        <!-- <div class="form-group">
                            <label for="wipLimit">WIP限制</label>
                            <input type="number" id="wipLimit" name="wip_limit" min="0" max="100" value="0">
                            <span class="help-text">0表示无限制</span>
                        </div> -->
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
            console.log('API添加列成功:', data);
            self.hideAddColumnDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('列添加成功！');
        }).fail(function(error) {
            console.warn('API添加列失败，使用前端模拟:', error);
            // 前端模拟添加列
            self.addColumnToFrontend(params);
            self.hideAddColumnDialog();
            self.showSuccessMessage('列添加成功！（前端模拟）');
        }).always(function() {
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

    /**
     * 前端模拟添加列
     */
    KanbanBoard.prototype.addColumnToFrontend = function(params) {
        var self = this;
        
        // 生成新的列ID
        var newColumnId = Date.now();
        
        // 计算插入位置
        var position = parseInt(params.position) || -1;
        var insertIndex = position === -1 ? this.columns.length : position;
        
        // 创建新列数据
        var newColumnData = {
            column_id: newColumnId,
            board_id: this.boardId,
            column_name: params.name,
            column_description: params.description || '',
            column_color: params.color || '#3498db',
            column_order: insertIndex + 1,
            column_width: parseInt(params.width) || 300,
            column_max_cards: parseInt(params.max_cards) || 0,
            column_wip_limit: 0,  // WIP限制已隐藏，固定为0
            column_is_collapsed: false,
            cards: []
        };
        
        // 调整其他列的顺序
        this.columns.forEach(function(column, index) {
            if (index >= insertIndex) {
                column.data.column_order = index + 2;
            }
        });
        
        // 创建新列对象
        var newColumn = new KanbanColumn(newColumnData, self);
        
        // 插入到正确位置
        if (insertIndex >= this.columns.length) {
            this.columns.push(newColumn);
        } else {
            this.columns.splice(insertIndex, 0, newColumn);
        }
        
        // 重新渲染看板
        this.renderBoardFromColumns();
    };
    
    /**
     * 从列数据重新渲染看板
     */
    KanbanBoard.prototype.renderBoardFromColumns = function() {
        var self = this;
        
        // 清空当前内容
        var columnsContainer = this.element.querySelector('.kanban-columns');
        if (columnsContainer) {
            columnsContainer.innerHTML = '';
            
            // 重新渲染所有列
            this.columns.forEach(function(column) {
                columnsContainer.appendChild(column.element);
            });
            
            // 如果没有列，显示提示
            if (this.columns.length === 0) {
                var noColumnsMsg = document.createElement('div');
                noColumnsMsg.className = 'kanban-no-columns';
                noColumnsMsg.textContent = '暂无列，请先创建列';
                columnsContainer.appendChild(noColumnsMsg);
            }
        }
    };
    
    /**
     * 显示成功消息
     */
    KanbanBoard.prototype.showSuccessMessage = function(message) {
        var self = this;
        var messageEl = document.createElement('div');
        messageEl.className = 'kanban-success-message';
        messageEl.textContent = message;
        
        this.element.appendChild(messageEl);
        
        setTimeout(function() {
            if (messageEl.parentNode) {
                messageEl.parentNode.removeChild(messageEl);
            }
        }, 3000);
    };
    
    /**
     * 显示错误消息
     */
    KanbanBoard.prototype.showErrorMessage = function(message) {
        var self = this;
        var messageEl = document.createElement('div');
        messageEl.className = 'kanban-error-message';
        messageEl.textContent = message;
        
        this.element.appendChild(messageEl);
        
        setTimeout(function() {
            if (messageEl.parentNode) {
                messageEl.parentNode.removeChild(messageEl);
            }
        }, 5000);
    };

    /**
     * 绑定事件
     */
    KanbanBoard.prototype.bindEvents = function() {
        var self = this;
        
        // 绑定列拖拽事件
        this.bindColumnDragEvents();
    };
    
    /**
     * 绑定列拖拽事件
     */
    KanbanBoard.prototype.bindColumnDragEvents = function() {
        var self = this;
        
        // 为每个列添加拖拽功能（避免重复绑定）
        this.columns.forEach(function(column) {
            if (!column.element.dataset.dragInitialized) {
                self.makeColumnDraggable(column);
                column.element.dataset.dragInitialized = 'true';
            }
        });
    };
    
    /**
     * 使列可拖拽
     */
    KanbanBoard.prototype.makeColumnDraggable = function(column) {
        var self = this;
        
        // 获取拖拽手柄
        var dragHandle = column.element.querySelector('.kanban-column-drag-handle');
        if (!dragHandle) {
            console.warn('拖拽手柄未找到');
            return;
        }
        
        // 只设置拖拽手柄为可拖拽
        dragHandle.draggable = true;
        dragHandle.style.cursor = 'move';
        
        // 拖拽开始 - 只在拖拽手柄上监听
        dragHandle.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', column.data.column_id);
            e.dataTransfer.effectAllowed = 'move';
            
            // 添加拖拽样式
            column.element.style.opacity = '0.5';
            column.element.style.transform = 'rotate(2deg)';
            
            // 记录拖拽的列
            self.draggedColumn = column;
            self.draggedColumnId = column.data.column_id;
            
            // 为所有其他列添加拖拽目标样式
            self.columns.forEach(function(col) {
                if (col !== column) {
                    col.element.classList.add('kanban-drop-target');
                }
            });
        });
        
        // 拖拽结束 - 只在拖拽手柄上监听
        dragHandle.addEventListener('dragend', function(e) {
            // 移除拖拽样式
            column.element.style.opacity = '1';
            column.element.style.transform = '';
            
            // 清理状态
            self.draggedColumn = null;
            self.draggedColumnId = null;
            
            // 移除所有拖拽指示器和样式
            self.removeDragIndicators();
            self.removeDropTargetStyles();
        });
        
        // 拖拽进入
        column.element.addEventListener('dragenter', function(e) {
            e.preventDefault();
            if (self.draggedColumn && self.draggedColumn !== column) {
                self.handleDragEnter(column, e);
            }
        });
        
        // 拖拽悬停
        column.element.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (self.draggedColumn && self.draggedColumn !== column) {
                self.handleDragOver(column, e);
            }
        });
        
        // 拖拽离开
        column.element.addEventListener('dragleave', function(e) {
            // 只有当鼠标真正离开元素时才移除指示器
            if (!column.element.contains(e.relatedTarget)) {
                self.removeDragIndicator(column);
            }
        });
        
        // 放置
        column.element.addEventListener('drop', function(e) {
            e.preventDefault();
            
            var draggedColumnId = e.dataTransfer.getData('text/plain');
            var draggedColumn = self.findColumnById(draggedColumnId);
            
            if (draggedColumn && draggedColumn !== column) {
                self.handleDrop(draggedColumn, column, e);
            }
            
            self.removeDragIndicators();
            self.removeDropTargetStyles();
        });
    };
    
    /**
     * 处理拖拽进入
     */
    KanbanBoard.prototype.handleDragEnter = function(targetColumn, e) {
        // 移除之前的指示器
        this.removeDragIndicators();
        
        // 计算放置位置
        var rect = targetColumn.element.getBoundingClientRect();
        var midPoint = rect.left + rect.width / 2;
        var position = e.clientX < midPoint ? 'before' : 'after';
        
        // 显示新的指示器
        this.showDragIndicator(targetColumn, position);
    };
    
    /**
     * 处理拖拽悬停
     */
    KanbanBoard.prototype.handleDragOver = function(targetColumn, e) {
        // 实时更新指示器位置
        var rect = targetColumn.element.getBoundingClientRect();
        var midPoint = rect.left + rect.width / 2;
        var position = e.clientX < midPoint ? 'before' : 'after';
        
        // 更新指示器位置
        this.updateDragIndicator(targetColumn, position);
    };
    
    /**
     * 处理放置
     */
    KanbanBoard.prototype.handleDrop = function(draggedColumn, targetColumn, e) {
        // 计算最终放置位置
        var rect = targetColumn.element.getBoundingClientRect();
        var midPoint = rect.left + rect.width / 2;
        var position = e.clientX < midPoint ? 'before' : 'after';
        
        // 执行移动
        this.moveColumnToPosition(draggedColumn, targetColumn, position);
    };
    
    /**
     * 显示拖拽指示器
     */
    KanbanBoard.prototype.showDragIndicator = function(targetColumn, position) {
        var indicator = document.createElement('div');
        indicator.className = 'kanban-drag-indicator';
        indicator.dataset.position = position;
        indicator.dataset.targetColumn = targetColumn.data.column_id;
        
        var parent = targetColumn.element.parentNode;
        if (position === 'before') {
            parent.insertBefore(indicator, targetColumn.element);
        } else {
            parent.insertBefore(indicator, targetColumn.element.nextSibling);
        }
    };
    
    /**
     * 更新拖拽指示器位置
     */
    KanbanBoard.prototype.updateDragIndicator = function(targetColumn, position) {
        var existingIndicator = document.querySelector('.kanban-drag-indicator[data-target-column="' + targetColumn.data.column_id + '"]');
        
        if (existingIndicator) {
            // 如果位置相同，不需要更新
            if (existingIndicator.dataset.position === position) {
                return;
            }
            
            // 移除旧指示器
            existingIndicator.remove();
        }
        
        // 显示新位置的指示器
        this.showDragIndicator(targetColumn, position);
    };
    
    /**
     * 移除拖拽指示器
     */
    KanbanBoard.prototype.removeDragIndicator = function(targetColumn) {
        var indicator = targetColumn.element.previousSibling;
        if (indicator && indicator.classList.contains('kanban-drag-indicator')) {
            indicator.remove();
        }
        
        indicator = targetColumn.element.nextSibling;
        if (indicator && indicator.classList.contains('kanban-drag-indicator')) {
            indicator.remove();
        }
    };
    
    /**
     * 移除所有拖拽指示器
     */
    KanbanBoard.prototype.removeDragIndicators = function() {
        var indicators = document.querySelectorAll('.kanban-drag-indicator');
        indicators.forEach(function(indicator) {
            indicator.remove();
        });
    };
    
    /**
     * 移除拖拽目标样式
     */
    KanbanBoard.prototype.removeDropTargetStyles = function() {
        this.columns.forEach(function(column) {
            column.element.classList.remove('kanban-drop-target');
        });
    };
    
    /**
     * 根据ID查找列
     */
    KanbanBoard.prototype.findColumnById = function(columnId) {
        for (var i = 0; i < this.columns.length; i++) {
            if (this.columns[i].data.column_id == columnId) {
                return this.columns[i];
            }
        }
        return null;
    };
    
    /**
     * 移动列到指定位置
     */
    KanbanBoard.prototype.moveColumnToPosition = function(draggedColumn, targetColumn, position) {
        var self = this;
        
        // 计算新的位置
        var draggedIndex = this.columns.indexOf(draggedColumn);
        var targetIndex = this.columns.indexOf(targetColumn);
        
        if (draggedIndex === -1 || targetIndex === -1) {
            return;
        }
        
        // 从数组中移除拖拽的列
        this.columns.splice(draggedIndex, 1);
        
        // 重新计算目标位置（因为数组已经改变）
        var insertIndex = targetIndex;
        if (draggedIndex < targetIndex) {
            insertIndex = targetIndex - 1;
        }
        
        // 根据位置调整插入索引
        if (position === 'after') {
            insertIndex = insertIndex + 1;
        }
        
        // 确保索引在有效范围内
        insertIndex = Math.max(0, Math.min(insertIndex, this.columns.length));
        
        // 插入到新位置
        this.columns.splice(insertIndex, 0, draggedColumn);
        
        // 重新渲染列
        this.renderColumns();
        
        // 发送API请求保存新顺序
        this.saveColumnOrder();
    };
    
    /**
     * 重新渲染列
     */
    KanbanBoard.prototype.renderColumns = function() {
        var columnsContainer = this.element.querySelector('.kanban-columns');
        if (!columnsContainer) return;
        
        // 移除添加列按钮（如果存在）
        var addColumnBtn = columnsContainer.querySelector('.kanban-add-column-btn');
        if (addColumnBtn) {
            addColumnBtn.remove();
        }
        
        // 重新排列列元素（不重新创建）
        this.columns.forEach(function(column) {
            columnsContainer.appendChild(column.element);
        });
        
        // 重新添加新列按钮
        if (!this.readOnly) {
            var newAddColumnBtn = this.createAddColumnButton();
            columnsContainer.appendChild(newAddColumnBtn);
        }
    };
    
    /**
     * 保存列顺序到服务器
     */
    KanbanBoard.prototype.saveColumnOrder = function() {
        var self = this;
        
        // 构建列顺序数据
        var columnOrders = this.columns.map(function(column, index) {
            return {
                column_id: column.data.column_id,
                order: index + 1
            };
        });
        
        console.log('发送列顺序数据:', columnOrders);
        
        // 发送API请求
        var params = {
            action: 'kanban',
            kanban_action: 'reordercolumns',
            board_id: this.boardId,
            column_orders: JSON.stringify(columnOrders)
        };
        
        console.log('API请求参数:', params);
        
        this.api.post(params).done(function(data) {
            console.log('列顺序保存成功:', data);
            self.showSuccessMessage('列顺序已更新');
        }).fail(function(error) {
            console.error('保存列顺序失败:', error);
            self.showErrorMessage('保存列顺序失败，请刷新页面重试');
            
            // 失败时重新加载看板
            self.loadBoard();
        });
    };

    /**
     * 显示加载状态
     */
    KanbanBoard.prototype.showLoading = function() {
        this.element.innerHTML = '<div class="kanban-loading">加载中...</div>';
    };

    /**
     * 隐藏加载状态
     */
    KanbanBoard.prototype.hideLoading = function() {
        // 加载完成后会被renderBoard替换
    };

    /**
     * 显示错误信息
     */
    KanbanBoard.prototype.showError = function(message) {
        this.element.innerHTML = '<div class="kanban-error">' + message + '</div>';
    };

    /**
     * 列类
     */
    function KanbanColumn(columnData, board) {
        this.data = columnData;
        this.board = board;
        this.cards = [];
        
        this.createElement();
        this.renderCards();
    }

    KanbanColumn.prototype.createElement = function() {
        var self = this;
        
        this.element = document.createElement('div');
        this.element.className = 'kanban-column';
        this.element.style.borderTopColor = this.data.column_color || '#3498db';
        this.element.style.width = (this.data.column_width || 250) + 'px';
        this.element.dataset.columnId = this.data.column_id;
        
        // 列头部
        var header = document.createElement('div');
        header.className = 'kanban-column-header';
        
        // 拖拽手柄
        var dragHandle = document.createElement('div');
        dragHandle.className = 'kanban-column-drag-handle';
        dragHandle.innerHTML = '⋮⋮';
        dragHandle.title = '拖拽移动列';
        header.appendChild(dragHandle);
        
        var title = document.createElement('h3');
        title.textContent = this.data.column_name || '未命名列';
        header.appendChild(title);
        
        // 显示WIP限制（已隐藏）
        // if (this.data.column_wip_limit > 0) {
        //     var wipInfo = document.createElement('span');
        //     wipInfo.className = 'kanban-wip-info';
        //     wipInfo.textContent = '(' + this.data.cards.length + '/' + this.data.column_wip_limit + ')';
        //     header.appendChild(wipInfo);
        // }
        
        // 如果不是只读模式，添加操作按钮
        if (!this.board.readOnly) {
            var actionsContainer = document.createElement('div');
            actionsContainer.className = 'kanban-column-actions';
            
            // 添加卡片按钮
            var addCardBtn = document.createElement('button');
            addCardBtn.className = 'kanban-add-card-btn';
            addCardBtn.textContent = '+';
            addCardBtn.title = '添加卡片';
            
            addCardBtn.addEventListener('click', function() {
                self.showAddCardDialog();
            });
            
            // 菜单按钮容器（相对定位）
            var menuContainer = document.createElement('div');
            menuContainer.className = 'kanban-column-menu-container';
            
            // 菜单按钮
            var menuBtn = document.createElement('button');
            menuBtn.className = 'kanban-column-menu-btn';
            menuBtn.innerHTML = '⋮';
            menuBtn.title = '列操作菜单';
            
            // 创建下拉菜单
            var dropdownMenu = document.createElement('div');
            dropdownMenu.className = 'kanban-column-dropdown-menu';
            dropdownMenu.innerHTML = `
                <div class="menu-item" data-action="edit">
                    <span class="menu-icon">✎</span>
                    <span class="menu-text">编辑列</span>
                </div>
                <div class="menu-item" data-action="delete">
                    <span class="menu-icon">×</span>
                    <span class="menu-text">删除列</span>
                </div>
            `;
            
            // 菜单按钮点击事件
            menuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.toggleColumnMenu(dropdownMenu);
            });
            
            // 菜单项点击事件
            dropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
                var action = e.target.closest('.menu-item').dataset.action;
                self.handleColumnMenuAction(action);
                self.hideColumnMenu(dropdownMenu);
            });
            
            // 将菜单按钮和下拉菜单添加到菜单容器
            menuContainer.appendChild(menuBtn);
            menuContainer.appendChild(dropdownMenu);
            
            actionsContainer.appendChild(addCardBtn);
            actionsContainer.appendChild(menuContainer);
            header.appendChild(actionsContainer);
        }
        
        this.element.appendChild(header);
        
        // 卡片容器
        this.cardsContainer = document.createElement('div');
        this.cardsContainer.className = 'kanban-cards';
        
        this.element.appendChild(this.cardsContainer);
        
        // 添加全局点击事件来关闭菜单
        this.addGlobalMenuCloseListener();
    };

    /**
     * 渲染卡片
     */
    KanbanColumn.prototype.renderCards = function() {
        var self = this;
        
        this.cardsContainer.innerHTML = '';
        this.cards = [];
        
        if (this.data.cards && this.data.cards.length > 0) {
            this.data.cards.forEach(function(cardData) {
                var card = new KanbanCard(cardData, self);
                self.cards.push(card);
                self.cardsContainer.appendChild(card.element);
            });
        } else {
            // 如果没有卡片，显示提示
            var noCardsMsg = document.createElement('div');
            noCardsMsg.className = 'kanban-no-cards';
            noCardsMsg.textContent = '暂无卡片';
            this.cardsContainer.appendChild(noCardsMsg);
        }
    };

    /**
     * 添加全局菜单关闭监听器
     */
    KanbanColumn.prototype.addGlobalMenuCloseListener = function() {
        var self = this;
        
        // 只在第一次添加全局监听器
        if (!window.kanbanMenuCloseListenerAdded) {
            window.kanbanMenuCloseListenerAdded = true;
            
            document.addEventListener('click', function(e) {
                // 如果点击的不是菜单相关元素，关闭所有菜单
                if (!e.target.closest('.kanban-column-menu-btn') && 
                    !e.target.closest('.kanban-column-dropdown-menu')) {
                    var allMenus = document.querySelectorAll('.kanban-column-dropdown-menu');
                    allMenus.forEach(function(menu) {
                        menu.classList.remove('show');
                    });
                }
            });
        }
    };

    /**
     * 切换列菜单显示状态
     */
    KanbanColumn.prototype.toggleColumnMenu = function(dropdownMenu) {
        // 隐藏其他所有菜单
        var allMenus = document.querySelectorAll('.kanban-column-dropdown-menu');
        allMenus.forEach(function(menu) {
            if (menu !== dropdownMenu) {
                menu.classList.remove('show');
            }
        });
        
        // 切换当前菜单
        dropdownMenu.classList.toggle('show');
    };

    /**
     * 隐藏列菜单
     */
    KanbanColumn.prototype.hideColumnMenu = function(dropdownMenu) {
        dropdownMenu.classList.remove('show');
    };

    /**
     * 处理列菜单动作
     */
    KanbanColumn.prototype.handleColumnMenuAction = function(action) {
        switch (action) {
            case 'edit':
                this.showEditColumnDialog();
                break;
            case 'delete':
                this.showDeleteColumnDialog();
                break;
            default:
                console.warn('未知的菜单动作:', action);
        }
    };

    /**
     * 显示添加卡片对话框
     */
    KanbanColumn.prototype.showAddCardDialog = function() {
        var self = this;
        this.createAddTaskDialog();
    };

    /**
     * 创建添加任务对话框
     */
    KanbanColumn.prototype.createAddTaskDialog = function() {
        var self = this;
        
        // 创建对话框容器
        var dialog = document.createElement('div');
        dialog.className = 'kanban-task-edit-dialog';
        dialog.innerHTML = `
            <div class="dialog-overlay"></div>
            <div class="dialog-content">
                <div class="dialog-header">
                    <h3>创建新任务</h3>
                    <button class="dialog-close" type="button">&times;</button>
                </div>
                <form class="kanban-task-form">
                    <div class="form-group">
                        <label for="task-title">任务标题 *</label>
                        <input type="text" id="task-title" name="title" 
                               maxlength="500" required placeholder="请输入任务标题">
                    </div>
                    
                    <div class="form-group">
                        <label for="task-description">任务描述</label>
                        <textarea id="task-description" name="description" rows="4" 
                                  placeholder="请输入任务详细描述"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="task-priority">优先级</label>
                            <select id="task-priority" name="priority">
                                <option value="low">低</option>
                                <option value="medium" selected>中</option>
                                <option value="high">高</option>
                                <option value="urgent">紧急</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="task-color">颜色</label>
                            <input type="color" id="task-color" name="color" value="#ffffff">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="task-due-date">截止日期</label>
                        <input type="datetime-local" id="task-due-date" name="due_date">
                    </div>
                    
                    <div class="dialog-footer">
                        <button type="button" class="btn btn-secondary cancel-btn">取消</button>
                        <button type="submit" class="btn btn-primary save-btn">创建任务</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(dialog);
        
        // 绑定事件
        this.bindAddTaskDialogEvents(dialog);
        
        // 显示对话框
        dialog.style.display = 'block';
        
        // 聚焦到标题输入框
        setTimeout(function() {
            dialog.querySelector('#task-title').focus();
        }, 100);
    };

    /**
     * 绑定添加任务对话框事件
     */
    KanbanColumn.prototype.bindAddTaskDialogEvents = function(dialog) {
        var self = this;
        var form = dialog.querySelector('.kanban-task-form');
        var cancelBtn = dialog.querySelector('.cancel-btn');
        var closeBtn = dialog.querySelector('.dialog-close');
        var overlay = dialog.querySelector('.dialog-overlay');
        
        // 关闭对话框
        function closeDialog() {
            dialog.remove();
        }
        
        // 取消按钮
        cancelBtn.addEventListener('click', closeDialog);
        
        // 关闭按钮
        closeBtn.addEventListener('click', closeDialog);
        
        // 点击遮罩层关闭
        overlay.addEventListener('click', closeDialog);
        
        // ESC键关闭
        dialog.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDialog();
            }
        });
        
        // 表单提交
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            self.createTask(form);
            closeDialog();
        });
    };

    /**
     * 创建新任务
     */
    KanbanColumn.prototype.createTask = function(form) {
        var self = this;
        var formData = new FormData(form);
        
        var taskData = {
            column_id: this.data.column_id,
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            priority: formData.get('priority'),
            color: formData.get('color'),
            due_date: formData.get('due_date') || null
        };
        
        // 验证数据
        if (!taskData.title) {
            alert('任务标题不能为空');
            return;
        }
        
        if (taskData.title.length > 500) {
            alert('任务标题不能超过500个字符');
            return;
        }
        
        // 显示加载状态
        var saveBtn = form.querySelector('.save-btn');
        var originalText = saveBtn.textContent;
        saveBtn.textContent = '创建中...';
        saveBtn.disabled = true;
        
        // 调用API创建任务
        this.createTaskAPI(taskData)
            .then(function(response) {
                if (response.result === 'success') {
                    // 创建新的卡片元素
                    var newCardData = {
                        card_id: response.task_id,
                        column_id: self.data.column_id,
                        card_title: taskData.title,
                        card_description: taskData.description,
                        card_priority: taskData.priority,
                        card_color: taskData.color,
                        card_due_date: taskData.due_date,
                        card_order: self.cards.length,
                        card_created_at: new Date().toISOString()
                    };
                    
                    // 创建卡片对象并添加到列中
                    var newCard = new KanbanCard(newCardData, self);
                    self.cards.push(newCard);
                    
                    // 移除"暂无卡片"提示
                    var noCardsMsg = self.cardsContainer.querySelector('.kanban-no-cards');
                    if (noCardsMsg) {
                        noCardsMsg.remove();
                    }
                    
                    // 添加新卡片到DOM
                    self.cardsContainer.appendChild(newCard.element);
                    
                    // 显示成功消息
                    self.showSuccessMessage('任务创建成功');
                } else {
                    alert('创建失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                console.error('创建任务失败:', error);
                alert('创建失败，请稍后重试');
            })
            .finally(function() {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
    };

    /**
     * 调用创建任务API
     */
    KanbanColumn.prototype.createTaskAPI = function(taskData) {
        var self = this;
        return new Promise(function(resolve, reject) {
            var params = new URLSearchParams({
                action: 'kanban',
                format: 'json',
                kanban_action: 'createtask',
                board_id: self.board.boardId,
                column_id: taskData.column_id,
                title: taskData.title,
                description: taskData.description,
                priority: taskData.priority,
                color: taskData.color,
                due_date: taskData.due_date || ''
            });
            
            // 添加用户认证
            if (mw.user.isAnon()) {
                reject(new Error('需要登录才能创建任务'));
                return;
            }
            
            fetch(mw.config.get('wgScriptPath') + '/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.error) {
                    reject(new Error(data.error.info || 'API error'));
                } else {
                    resolve(data);
                }
            })
            .catch(function(error) {
                reject(error);
            });
        });
    };

    /**
     * 显示成功消息
     */
    KanbanColumn.prototype.showSuccessMessage = function(message) {
        var messageEl = document.createElement('div');
        messageEl.className = 'kanban-success-message';
        messageEl.textContent = message;
        
        document.body.appendChild(messageEl);
        
        setTimeout(function() {
            messageEl.remove();
        }, 3000);
    };
    
    /**
     * 显示编辑列对话框
     */
    KanbanColumn.prototype.showEditColumnDialog = function() {
        var self = this;
        this.createEditColumnDialog();
    };
    
    /**
     * 创建编辑列对话框
     */
    KanbanColumn.prototype.createEditColumnDialog = function() {
        var self = this;
        
        // 移除现有对话框
        var existingDialog = document.querySelector('.kanban-column-edit-dialog');
        if (existingDialog) {
            existingDialog.remove();
        }
        
        // 创建对话框
        var dialog = document.createElement('div');
        dialog.className = 'kanban-column-edit-dialog';
        dialog.innerHTML = `
            <div class="dialog-content">
                <div class="dialog-header">
                    <h3>编辑列</h3>
                    <button class="dialog-close-btn" type="button">×</button>
                </div>
                <form class="kanban-column-form">
                    <div class="form-group">
                        <label for="column-name">列名称 *</label>
                        <input type="text" id="column-name" name="name" value="${this.data.column_name || ''}" 
                               maxlength="255" required placeholder="请输入列名称">
                    </div>
                    
                    <div class="form-group">
                        <label for="column-color">列颜色</label>
                        <div class="color-picker">
                            <input type="color" id="column-color" name="color" value="${this.data.column_color || '#3498db'}">
                        </div>
                    </div>
                    
                    <!-- WIP限制字段已隐藏 -->
                    <!-- <div class="form-group">
                        <label for="column-wip-limit">WIP限制</label>
                        <input type="number" id="column-wip-limit" name="wip_limit" 
                               value="${this.data.column_wip_limit || 0}" min="0" max="999" 
                               placeholder="0表示无限制">
                    </div> -->
                    
                    <div class="dialog-footer">
                        <button type="button" class="btn btn-secondary cancel-btn">取消</button>
                        <button type="submit" class="btn btn-primary save-btn">保存更改</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(dialog);
        this.bindEditColumnDialogEvents(dialog);
    };
    
    /**
     * 绑定编辑列对话框事件
     */
    KanbanColumn.prototype.bindEditColumnDialogEvents = function(dialog) {
        var self = this;
        var form = dialog.querySelector('.kanban-column-form');
        var closeBtn = dialog.querySelector('.dialog-close-btn');
        var cancelBtn = dialog.querySelector('.cancel-btn');
        
        // 关闭按钮
        closeBtn.addEventListener('click', function() {
            dialog.remove();
        });
        
        // 取消按钮
        cancelBtn.addEventListener('click', function() {
            dialog.remove();
        });
        
        // 点击背景关闭
        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) {
                dialog.remove();
            }
        });
        
        // 表单提交
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            self.updateColumn(form);
        });
    };
    
    /**
     * 更新列信息
     */
    KanbanColumn.prototype.updateColumn = function(form) {
        var self = this;
        var formData = new FormData(form);
        
        var columnData = {
            column_id: this.data.column_id,
            name: formData.get('name').trim(),
            color: formData.get('color'),
            wip_limit: 0  // WIP限制已隐藏，固定为0
        };
        
        // 验证数据
        if (!columnData.name) {
            alert('列名称不能为空');
            return;
        }
        
        // 显示保存状态
        var saveBtn = form.querySelector('.save-btn');
        var originalText = saveBtn.textContent;
        saveBtn.textContent = '保存中...';
        saveBtn.disabled = true;
        
        // 调用API更新列
        this.updateColumnAPI(columnData)
            .then(function(response) {
                if (response.result === 'success') {
                    // 更新本地数据
                    self.data.column_name = columnData.name;
                    self.data.column_color = columnData.color;
                    self.data.column_wip_limit = 0;  // WIP限制已隐藏，固定为0
                    
                    // 重新渲染列
                    self.updateColumnDisplay();
                    
                    // 关闭对话框
                    var dialog = document.querySelector('.kanban-column-edit-dialog');
                    if (dialog) {
                        dialog.remove();
                    }
                    
                    // 显示成功消息
                    self.showSuccessMessage('列已更新');
                } else {
                    alert('更新失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                console.error('更新列失败:', error);
                alert('更新失败，请稍后重试');
            })
            .finally(function() {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
    };
    
    /**
     * 调用更新列API
     */
    KanbanColumn.prototype.updateColumnAPI = function(columnData) {
        var self = this;
        return new Promise(function(resolve, reject) {
            var params = new URLSearchParams({
                action: 'kanban',
                format: 'json',
                kanban_action: 'updatecolumn',
                column_id: columnData.column_id,
                name: columnData.name,
                color: columnData.color,
                wip_limit: 0  // WIP限制已隐藏，固定为0
            });
            
            // 添加用户认证
            if (mw.user.isAnon()) {
                reject(new Error('需要登录才能更新列'));
                return;
            }
            
            fetch(mw.config.get('wgScriptPath') + '/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params.toString()
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(function(data) {
                resolve(data);
            })
            .catch(function(error) {
                reject(error);
            });
        });
    };
    
    /**
     * 更新列显示
     */
    KanbanColumn.prototype.updateColumnDisplay = function() {
        // 更新列标题
        var title = this.element.querySelector('.kanban-column-header h3');
        if (title) {
            title.textContent = this.data.column_name || '未命名列';
        }
        
        // 更新列颜色
        this.element.style.borderTopColor = this.data.column_color || '#3498db';
        
        // 更新WIP信息（已隐藏）
        // var wipInfo = this.element.querySelector('.kanban-wip-info');
        // if (this.data.column_wip_limit > 0) {
        //     if (!wipInfo) {
        //         wipInfo = document.createElement('span');
        //         wipInfo.className = 'kanban-wip-info';
        //         var header = this.element.querySelector('.kanban-column-header');
        //         if (header) {
        //             header.appendChild(wipInfo);
        //         }
        //     }
        //     wipInfo.textContent = '(' + this.cards.length + '/' + this.data.column_wip_limit + ')';
        // } else if (wipInfo) {
        //     wipInfo.remove();
        // }
    };
    
    /**
     * 显示删除列确认对话框
     */
    KanbanColumn.prototype.showDeleteColumnDialog = function() {
        var self = this;
        
        // 检查是否是最小列数
        if (this.board.columns.length <= 1) {
            alert('无法删除最后一列！');
            return;
        }
        
        // 创建确认对话框
        var dialogHtml = `
            <div class="kanban-delete-column-dialog" id="deleteColumnDialog">
                <div class="dialog-overlay"></div>
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3>删除列确认</h3>
                        <button class="dialog-close">&times;</button>
                    </div>
                    <div class="dialog-body">
                        <p>确定要删除列 "<strong>${this.data.column_name}</strong>" 吗？</p>
                        <p class="warning-text">此操作不可撤销！</p>
                        
                        <div class="form-group">
                            <label>列中有 ${this.data.cards.length} 个卡片，请选择处理方式：</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="cardAction" value="move" checked>
                                    移动到其他列
                                </label>
                                <label>
                                    <input type="radio" name="cardAction" value="delete">
                                    删除所有卡片
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="targetColumnGroup">
                            <label for="targetColumn">选择目标列：</label>
                            <select id="targetColumn" name="move_cards_to">
                                ${this.getTargetColumnsOptions()}
                            </select>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="btn btn-secondary" id="cancelDelete">取消</button>
                        <button type="button" class="btn btn-danger" id="confirmDelete">确认删除</button>
                    </div>
                </div>
            </div>
        `;
        
        // 添加到页面
        document.body.insertAdjacentHTML('beforeend', dialogHtml);
        
        var dialog = document.getElementById('deleteColumnDialog');
        var cardActionRadios = dialog.querySelectorAll('input[name="cardAction"]');
        var targetColumnGroup = dialog.querySelector('#targetColumnGroup');
        var targetColumnSelect = dialog.querySelector('#targetColumn');
        
        // 切换卡片处理方式
        cardActionRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'move') {
                    targetColumnGroup.style.display = 'block';
                } else {
                    targetColumnGroup.style.display = 'none';
                }
            });
        });
        
        // 关闭对话框
        dialog.querySelector('.dialog-close').addEventListener('click', function() {
            dialog.remove();
        });
        
        dialog.querySelector('#cancelDelete').addEventListener('click', function() {
            dialog.remove();
        });
        
        // 确认删除
        dialog.querySelector('#confirmDelete').addEventListener('click', function() {
            var cardAction = dialog.querySelector('input[name="cardAction"]:checked').value;
            var moveCardsTo = cardAction === 'move' ? targetColumnSelect.value : 0;
            
            self.deleteColumn(moveCardsTo);
            dialog.remove();
        });
        
        // 点击遮罩关闭
        dialog.querySelector('.dialog-overlay').addEventListener('click', function() {
            dialog.remove();
        });
    };
    
    /**
     * 获取目标列选项HTML
     */
    KanbanColumn.prototype.getTargetColumnsOptions = function() {
        var options = '';
        for (var i = 0; i < this.board.columns.length; i++) {
            var column = this.board.columns[i];
            if (column.data.column_id !== this.data.column_id) {
                options += `<option value="${column.data.column_id}">${column.data.column_name}</option>`;
            }
        }
        return options;
    };
    
    /**
     * 删除列
     */
    KanbanColumn.prototype.deleteColumn = function(moveCardsTo) {
        var self = this;
        
        var params = {
            action: 'kanban',
            kanban_action: 'deletecolumn',
            board_id: this.board.boardId,
            column_id: this.data.column_id,
            move_cards_to: moveCardsTo
        };
        
        // 显示加载状态
        this.element.style.opacity = '0.5';
        
        // 发送API请求
        this.board.api.post(params).done(function(data) {
            console.log('API删除列成功:', data);
            self.board.loadBoard(); // 重新加载看板
            self.board.showSuccessMessage('列删除成功！');
        }).fail(function(error) {
            console.warn('API删除列失败，使用前端模拟:', error);
            // 前端模拟删除列
            self.deleteColumnFromFrontend();
            self.board.showSuccessMessage('列删除成功！（前端模拟）');
        }).always(function() {
            self.element.style.opacity = '1';
        });
    };
    
    /**
     * 前端模拟删除列
     */
    KanbanColumn.prototype.deleteColumnFromFrontend = function() {
        // 从看板中移除列
        var columnIndex = this.board.columns.indexOf(this);
        if (columnIndex > -1) {
            this.board.columns.splice(columnIndex, 1);
        }
        
        // 从DOM中移除
        this.element.remove();
    };

    /**
     * 卡片类
     */
    function KanbanCard(cardData, column) {
        this.data = cardData;
        this.column = column;
        
        this.createElement();
    }

    KanbanCard.prototype.createElement = function() {
        var self = this;
        
        this.element = document.createElement('div');
        this.element.className = 'kanban-card';
        this.element.dataset.cardId = this.data.card_id;
        
        // 根据优先级设置颜色
        if (this.data.card_priority === 'high' || this.data.card_priority === 'urgent') {
            this.element.classList.add('priority-high');
        }
        
        var title = document.createElement('div');
        title.className = 'kanban-card-title';
        title.textContent = this.data.card_title || '无标题';
        this.element.appendChild(title);
        
        if (this.data.card_description) {
            var description = document.createElement('div');
            description.className = 'kanban-card-description';
            description.textContent = this.data.card_description;
            this.element.appendChild(description);
        }
        
        // 如果不是只读模式，添加拖拽手柄
        if (!this.column.board.readOnly) {
            // 拖拽手柄
            var dragHandle = document.createElement('div');
            dragHandle.className = 'kanban-card-drag-handle';
            dragHandle.innerHTML = '⋮⋮';
            dragHandle.title = '拖拽移动卡片';
            this.element.appendChild(dragHandle);
        }
        
        // 添加点击事件 - 直接打开编辑对话框
        this.element.addEventListener('click', function(e) {
            // 如果点击的是拖拽手柄，不触发卡片点击事件
            if (e.target.classList.contains('kanban-card-drag-handle')) {
                return;
            }
                self.showEditDialog();
            });
            
        // 如果不是只读模式，添加拖拽功能
        if (!this.column.board.readOnly) {
            this.makeCardDraggable();
        }
    };

    /**
     * 使卡片可拖拽
     */
    KanbanCard.prototype.makeCardDraggable = function() {
        var self = this;
        
        // 获取拖拽手柄
        var dragHandle = this.element.querySelector('.kanban-card-drag-handle');
        if (!dragHandle) {
            console.warn('卡片拖拽手柄未找到');
                return;
            }
        
        // 只设置拖拽手柄为可拖拽
        dragHandle.draggable = true;
        dragHandle.style.cursor = 'move';
        
        // 拖拽开始 - 只在拖拽手柄上监听
        dragHandle.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', self.data.card_id);
            e.dataTransfer.effectAllowed = 'move';
            
            // 添加拖拽样式
            self.element.style.opacity = '0.5';
            self.element.style.transform = 'rotate(2deg)';
            
            // 记录拖拽的卡片
            var board = self.column ? self.column.board : null;
            if (board) {
                board.draggedCard = self;
                board.draggedCardId = self.data.card_id;
                
                // 为所有其他卡片添加拖拽目标样式
                self.addDropTargetStyles();
            }
        });
        
        // 拖拽结束 - 只在拖拽手柄上监听
        dragHandle.addEventListener('dragend', function(e) {
            // 移除拖拽样式
            self.element.style.opacity = '1';
            self.element.style.transform = '';
            
            // 清理状态
            var board = self.column ? self.column.board : null;
            if (board) {
                board.draggedCard = null;
                board.draggedCardId = null;
                
                // 移除所有拖拽指示器和样式
                self.removeCardDragIndicators();
                self.removeDropTargetStyles();
            }
        });
        
        // 为卡片元素添加拖拽事件监听
        this.bindCardDragEvents();
    };

    /**
     * 绑定卡片拖拽事件
     */
    KanbanCard.prototype.bindCardDragEvents = function() {
        var self = this;
        
        // 拖拽进入
        this.element.addEventListener('dragenter', function(e) {
            e.preventDefault();
            var board = self.column ? self.column.board : null;
            if (board && board.draggedCard && board.draggedCard !== self) {
                self.handleCardDragEnter(e);
            }
        });
        
        // 拖拽悬停
        this.element.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            var board = self.column ? self.column.board : null;
            if (board && board.draggedCard && board.draggedCard !== self) {
                self.handleCardDragOver(e);
            }
        });
        
        // 拖拽离开
        this.element.addEventListener('dragleave', function(e) {
            // 只有当鼠标真正离开元素时才移除指示器
            if (!self.element.contains(e.relatedTarget)) {
                self.removeCardDragIndicator();
            }
        });
        
        // 放置
        this.element.addEventListener('drop', function(e) {
            e.preventDefault();
            
            var draggedCardId = e.dataTransfer.getData('text/plain');
            var board = self.column ? self.column.board : null;
            if (board && board.draggedCard) {
                self.handleCardDrop(board.draggedCard, e);
            }
            
            self.removeCardDragIndicators();
            self.removeDropTargetStyles();
        });
    };

    /**
     * 处理卡片拖拽进入
     */
    KanbanCard.prototype.handleCardDragEnter = function(e) {
        // 移除之前的指示器
        this.removeCardDragIndicator();
        
        // 计算放置位置
        var rect = this.element.getBoundingClientRect();
        var midPoint = rect.top + rect.height / 2;
        var position = e.clientY < midPoint ? 'before' : 'after';
        
        // 显示新的指示器
        this.updateCardDragIndicator(position);
    };

    /**
     * 处理卡片拖拽悬停
     */
    KanbanCard.prototype.handleCardDragOver = function(e) {
        // 根据鼠标位置更新指示器
        var rect = this.element.getBoundingClientRect();
        var midPoint = rect.top + rect.height / 2;
        var position = e.clientY < midPoint ? 'before' : 'after';
        
        this.updateCardDragIndicator(position);
    };

    /**
     * 处理卡片放置
     */
    KanbanCard.prototype.handleCardDrop = function(draggedCard, e) {
        var self = this;
        
        // 计算最终放置位置
        var rect = this.element.getBoundingClientRect();
        var midPoint = rect.top + rect.height / 2;
        var position = e.clientY < midPoint ? 'before' : 'after';
        
        // 移动卡片到新位置
        this.moveCardToPosition(draggedCard, position);
    };

    /**
     * 更新卡片拖拽指示器
     */
    KanbanCard.prototype.updateCardDragIndicator = function(position) {
        // 移除现有指示器
        this.removeCardDragIndicator();
        
        // 创建新指示器
        var indicator = document.createElement('div');
        indicator.className = 'kanban-card-drag-indicator';
        indicator.dataset.position = position;
        
        // 插入到正确位置
        if (position === 'before') {
            this.element.parentNode.insertBefore(indicator, this.element);
        } else {
            this.element.parentNode.insertBefore(indicator, this.element.nextSibling);
        }
    };

    /**
     * 移除卡片拖拽指示器
     */
    KanbanCard.prototype.removeCardDragIndicator = function() {
        var indicator = this.element.parentNode.querySelector('.kanban-card-drag-indicator');
        if (indicator) {
            indicator.remove();
        }
    };

    /**
     * 移除所有卡片拖拽指示器
     */
    KanbanCard.prototype.removeCardDragIndicators = function() {
        var board = this.column ? this.column.board : null;
        if (board) {
            var indicators = document.querySelectorAll('.kanban-card-drag-indicator');
            indicators.forEach(function(indicator) {
                indicator.remove();
            });
        }
    };

    /**
     * 添加拖拽目标样式
     */
    KanbanCard.prototype.addDropTargetStyles = function() {
        var board = this.column ? this.column.board : null;
        if (board && board.columns) {
            board.columns.forEach(function(column) {
                if (column.data.cards) {
                    column.data.cards.forEach(function(card) {
                        if (card.element && card !== this) {
                            card.element.classList.add('kanban-card-drop-target');
                        }
                    }.bind(this));
                }
            }.bind(this));
        }
    };

    /**
     * 移除拖拽目标样式
     */
    KanbanCard.prototype.removeDropTargetStyles = function() {
        var dropTargets = document.querySelectorAll('.kanban-card-drop-target');
        dropTargets.forEach(function(target) {
            target.classList.remove('kanban-card-drop-target');
        });
    };

    /**
     * 移动卡片到指定位置
     */
    KanbanCard.prototype.moveCardToPosition = function(draggedCard, position) {
        var self = this;
        var board = this.column ? this.column.board : null;
        if (!board) return;
        
        var sourceColumn = draggedCard.column;
        var targetColumn = self.column;
        
        // 检查是否是跨列拖拽
        var isCrossColumn = sourceColumn.data.column_id !== targetColumn.data.column_id;
        
        if (isCrossColumn) {
            // 跨列拖拽
            this.moveCardCrossColumn(draggedCard, targetColumn, position);
        } else {
            // 同列拖拽
            this.moveCardSameColumn(draggedCard, position);
        }
    };

    /**
     * 同列内移动卡片
     */
    KanbanCard.prototype.moveCardSameColumn = function(draggedCard, position) {
        var self = this;
        var board = this.column ? this.column.board : null;
        if (!board) return;
        
        // 获取当前列的所有卡片
        var currentColumn = draggedCard.column;
        var cards = currentColumn.data.cards || [];
        
        // 找到拖拽卡片和目标卡片的索引
        var draggedIndex = cards.findIndex(function(card) {
            return card.card_id === draggedCard.data.card_id;
        });
        var targetIndex = cards.findIndex(function(card) {
            return card.card_id === self.data.card_id;
        });
        
        if (draggedIndex === -1 || targetIndex === -1) {
            console.error('无法找到卡片索引');
            return;
        }
        
        // 计算插入位置
        var insertIndex = targetIndex;
        if (draggedIndex < targetIndex) {
            insertIndex = targetIndex - 1;
        }
        if (position === 'after') {
            insertIndex = insertIndex + 1;
        }
        
        // 从原位置移除
        cards.splice(draggedIndex, 1);
        
        // 插入到新位置
        cards.splice(insertIndex, 0, draggedCard.data);
        
        // 重新渲染列
        currentColumn.renderCards();
        
        // 发送API请求保存新顺序
        this.saveCardOrder(board);
    };

    /**
     * 跨列移动卡片
     */
    KanbanCard.prototype.moveCardCrossColumn = function(draggedCard, targetColumn, position) {
        var self = this;
        var board = this.column ? this.column.board : null;
        if (!board) return;
        
        var sourceColumn = draggedCard.column;
        var sourceCards = sourceColumn.data.cards || [];
        var targetCards = targetColumn.data.cards || [];
        
        // 找到拖拽卡片在源列中的索引
        var draggedIndex = sourceCards.findIndex(function(card) {
            return card.card_id === draggedCard.data.card_id;
        });
        
        if (draggedIndex === -1) {
            console.error('无法找到拖拽卡片索引');
            return;
        }
        
        // 计算在目标列中的插入位置
        var insertIndex = 0;
        if (position === 'after') {
            insertIndex = targetCards.length;
        } else {
            // 找到目标卡片在目标列中的索引
            var targetIndex = targetCards.findIndex(function(card) {
                return card.card_id === self.data.card_id;
            });
            if (targetIndex !== -1) {
                insertIndex = targetIndex;
            }
        }
        
        // 从源列移除卡片
        var cardData = sourceCards.splice(draggedIndex, 1)[0];
        
        // 更新卡片的列ID
        cardData.column_id = targetColumn.data.column_id;
        cardData.status_name = targetColumn.data.column_name;
        
        // 插入到目标列
        targetCards.splice(insertIndex, 0, cardData);
        
        // 更新卡片的列引用
        draggedCard.column = targetColumn;
        
        // 重新渲染两个列
        sourceColumn.renderCards();
        targetColumn.renderCards();
        
        // 发送API请求保存新顺序和状态
        this.saveCardOrder(board);
    };

    /**
     * 保存卡片顺序
     */
    KanbanCard.prototype.saveCardOrder = function(board) {
        var self = this;
        
        // 准备卡片顺序数据
        var cardOrders = [];
        if (board && board.columns) {
            board.columns.forEach(function(column) {
                if (column.data.cards) {
                    column.data.cards.forEach(function(card, index) {
                        cardOrders.push({
                            card_id: card.card_id,
                            order: index + 1,
                            status_id: column.data.column_id
                        });
                    });
                }
            });
        }
        
        // 发送API请求
        var params = {
            action: 'kanban',
            kanban_action: 'reordercards',
            board_id: board.boardId,
            card_orders: JSON.stringify(cardOrders)
        };
        
        console.log('API请求参数:', params);
        
        board.api.post(params).done(function(data) {
            console.log('API保存卡片顺序成功:', data);
            self.showSuccessMessage('卡片顺序已保存');
        }).fail(function(error) {
            console.error('保存卡片顺序失败:', error);
            self.showErrorMessage('保存卡片顺序失败，请刷新页面重试');
            
            // 失败时重新加载看板
            board.loadBoard();
        });
    };

    /**
     * 更新任务状态API
     */
    KanbanCard.prototype.updateTaskStatusAPI = function(cardId, newStatusId) {
        var self = this;
        var board = this.column ? this.column.board : null;
        if (!board) return;
        
        var params = {
            action: 'kanban',
            kanban_action: 'updatetask',
            task_id: cardId,
            status_id: newStatusId
        };
        
        console.log('API更新任务状态参数:', params);
        
        board.api.post(params).done(function(data) {
            console.log('API更新任务状态成功:', data);
        }).fail(function(error) {
            console.error('更新任务状态失败:', error);
            self.showErrorMessage('更新任务状态失败，请刷新页面重试');
            
            // 失败时重新加载看板
            board.loadBoard();
        });
    };

    /**
     * 移动任务到新状态
     */
    KanbanCard.prototype.moveToNewStatus = function(newStatusId, taskData) {
        var self = this;
        
        // 找到目标列
        var targetColumn = null;
        var board = self.column ? self.column.board : null;
        if (board && board.columns) {
            board.columns.forEach(function(column) {
                if (column.data.column_id == newStatusId) {
                    targetColumn = column;
                }
            });
        }
        
        if (!targetColumn) {
            console.error('目标状态不存在:', newStatusId);
            return;
        }
        
        // 更新任务数据
        self.data.card_title = taskData.title;
        self.data.card_description = taskData.description;
        self.data.card_priority = taskData.priority;
        self.data.card_color = taskData.color;
        self.data.card_due_date = taskData.due_date;
        self.data.column_id = newStatusId;
        self.data.status_name = targetColumn.data.column_name;
        
        // 从当前列移除卡片
        var currentColumn = self.column;
        if (currentColumn && currentColumn.element) {
            currentColumn.element.removeChild(self.element);
        }
        
        // 添加到目标列
        var targetColumnElement = document.querySelector('[data-column-id="' + newStatusId + '"] .kanban-column-cards');
        if (targetColumnElement) {
            targetColumnElement.appendChild(self.element);
            self.column = targetColumn;
        }
        
        // 重新渲染卡片
        self.updateCardDisplay();
    };

    /**
     * 获取状态选项HTML
     */
    KanbanCard.prototype.getStatusOptions = function() {
        var self = this;
        var options = '';
        
        // 通过column访问board
        var board = self.column ? self.column.board : null;
        
        // 调试信息
        console.log('getStatusOptions - column:', self.column);
        console.log('getStatusOptions - board:', board);
        console.log('getStatusOptions - board.columns:', board ? board.columns : 'no board');
        console.log('getStatusOptions - self.data:', self.data);
        
        // 从看板数据中获取所有列（状态）
        if (board && board.columns) {
            console.log('getStatusOptions - columns length:', board.columns.length);
            board.columns.forEach(function(column, index) {
                console.log('getStatusOptions - column[' + index + ']:', column);
                console.log('getStatusOptions - column[' + index + '].data:', column.data);
                var selected = column.data.column_id == self.data.column_id ? 'selected' : '';
                options += `<option value="${column.data.column_id}" ${selected}>${column.data.column_name}</option>`;
            });
        } else {
            console.log('getStatusOptions - no columns available');
        }
        
        console.log('getStatusOptions - final options:', options);
        return options;
    };

    /**
     * 显示编辑对话框
     */
    KanbanCard.prototype.showEditDialog = function() {
        var self = this;
        this.createEditDialog();
    };

    /**
     * 创建任务编辑对话框
     */
    KanbanCard.prototype.createEditDialog = function() {
        var self = this;
        
        // 创建对话框容器
        var dialog = document.createElement('div');
        dialog.className = 'kanban-task-edit-dialog';
        dialog.innerHTML = `
            <div class="dialog-overlay"></div>
            <div class="dialog-content">
                <div class="dialog-header">
                    <h3>任务详情</h3>
                    <button class="dialog-close" type="button">&times;</button>
                </div>
                
                <!-- 标签页导航 -->
                <div class="dialog-tabs">
                    <button class="tab-btn active" data-tab="details">任务详情</button>
                    <button class="tab-btn" data-tab="history">历史记录</button>
                </div>
                
                <div class="dialog-body">
                    <!-- 任务详情标签页 -->
                    <div class="tab-content active" id="tab-details">
                        <form class="kanban-task-form">
                            <div class="form-group">
                                <label for="task-title">任务标题 *</label>
                                <input type="text" id="task-title" name="title" value="${this.data.card_title || ''}" 
                                       maxlength="500" required placeholder="请输入任务标题">
                            </div>
                            
                            <div class="form-group">
                                <label for="task-description">任务描述</label>
                                <textarea id="task-description" name="description" rows="8" 
                                          placeholder="请输入任务详细描述">${this.data.card_description || ''}</textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="task-status">任务状态</label>
                                    <select id="task-status" name="status_id">
                                        ${this.getStatusOptions()}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="task-priority">优先级</label>
                                    <select id="task-priority" name="priority">
                                        <option value="low" ${this.data.card_priority === 'low' ? 'selected' : ''}>低</option>
                                        <option value="medium" ${this.data.card_priority === 'medium' ? 'selected' : ''}>中</option>
                                        <option value="high" ${this.data.card_priority === 'high' ? 'selected' : ''}>高</option>
                                        <option value="urgent" ${this.data.card_priority === 'urgent' ? 'selected' : ''}>紧急</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="task-color">颜色</label>
                                    <input type="color" id="task-color" name="color" 
                                           value="${this.data.card_color || '#ffffff'}">
                                </div>
                                
                                <div class="form-group">
                                    <label for="task-due-date">截止日期</label>
                                    <input type="datetime-local" id="task-due-date" name="due_date" 
                                           value="${this.formatDateTimeForInput(this.data.card_due_date)}">
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 历史记录标签页 -->
                    <div class="tab-content" id="tab-history">
                        <div class="history-container">
                            <div class="history-loading">加载历史记录中...</div>
                            <div class="history-list" style="display: none;"></div>
                            <div class="history-empty" style="display: none;">
                                <p>暂无历史记录</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dialog-footer">
                    <div class="btn-group-left">
                        <button type="button" class="btn btn-danger delete-btn">删除任务</button>
                    </div>
                    <div class="btn-group-right">
                        <button type="button" class="btn btn-secondary cancel-btn">关闭</button>
                        <button type="submit" class="btn btn-primary save-btn">保存更改</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        
        // 绑定事件
        this.bindEditDialogEvents(dialog);
        
        // 显示对话框
        dialog.style.display = 'block';
        
        // 聚焦到标题输入框
        setTimeout(function() {
            dialog.querySelector('#task-title').focus();
        }, 100);
    };

    /**
     * 绑定编辑对话框事件
     */
    KanbanCard.prototype.bindEditDialogEvents = function(dialog) {
        var self = this;
        var form = dialog.querySelector('.kanban-task-form');
        var deleteBtn = dialog.querySelector('.delete-btn');
        var closeBtn = dialog.querySelector('.dialog-close');
        var overlay = dialog.querySelector('.dialog-overlay');
        
        // 关闭对话框
        function closeDialog() {
            dialog.remove();
        }
        
        // 关闭按钮
        closeBtn.addEventListener('click', closeDialog);
        
        // 点击遮罩层关闭
        overlay.addEventListener('click', closeDialog);
        
        // ESC键关闭
        dialog.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDialog();
            }
        });
        
        // 删除任务
        deleteBtn.addEventListener('click', function() {
            if (confirm('确定要删除这个任务吗？此操作不可撤销。')) {
                self.deleteTask();
                closeDialog();
            }
        });
        
        // 表单提交
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            self.saveTask(form);
            closeDialog();
        });
        
        // 标签页切换
        var tabBtns = dialog.querySelectorAll('.tab-btn');
        var tabContents = dialog.querySelectorAll('.tab-content');
        
        tabBtns.forEach(function(tabBtn) {
            tabBtn.addEventListener('click', function() {
                var targetTab = this.dataset.tab;
                
                // 更新标签页按钮状态
                tabBtns.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // 更新标签页内容
                tabContents.forEach(function(content) {
                    content.classList.remove('active');
                });
                dialog.querySelector('#tab-' + targetTab).classList.add('active');
                
                // 如果切换到历史记录标签页，加载历史记录
                if (targetTab === 'history') {
                    self.loadTaskHistory(dialog);
                }
            });
        });
    };

    /**
     * 保存任务
     */
    KanbanCard.prototype.saveTask = function(form) {
        var self = this;
        var formData = new FormData(form);
        
        var taskData = {
            card_id: this.data.card_id,
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            priority: formData.get('priority'),
            color: formData.get('color'),
            due_date: formData.get('due_date') || null,
            status_id: formData.get('status_id')
        };
        
        // 验证数据
        if (!taskData.title) {
            alert('任务标题不能为空');
            return;
        }
        
        if (taskData.title.length > 500) {
            alert('任务标题不能超过500个字符');
            return;
        }
        
        // 显示加载状态
        var saveBtn = form.querySelector('.save-btn');
        var originalText = saveBtn.textContent;
        saveBtn.textContent = '保存中...';
        saveBtn.disabled = true;
        
        // 调用API更新任务
        this.updateTaskAPI(taskData)
            .then(function(response) {
                if (response.result === 'success') {
                    // 检查状态是否发生变化
                    var oldStatusId = self.data.column_id;
                    var newStatusId = parseInt(taskData.status_id);
                    
                    if (oldStatusId != newStatusId) {
                        // 状态发生变化，需要移动卡片
                        self.moveToNewStatus(newStatusId, taskData);
                    } else {
                        // 状态未变化，只更新本地数据
                        self.data.card_title = taskData.title;
                        self.data.card_description = taskData.description;
                        self.data.card_priority = taskData.priority;
                        self.data.card_color = taskData.color;
                        self.data.card_due_date = taskData.due_date;
                        
                        // 重新渲染卡片
                        self.updateCardDisplay();
                    }
                    
                    // 显示成功消息
                    self.showSuccessMessage('任务已保存');
                } else {
                    alert('保存失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                console.error('保存任务失败:', error);
                alert('保存失败，请稍后重试');
            })
            .finally(function() {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
    };

    /**
     * 删除任务
     */
    KanbanCard.prototype.deleteTask = function() {
        var self = this;
        
        // 显示加载状态
        this.showSuccessMessage('正在删除...');
        
        // 调用API删除任务
        this.deleteTaskAPI()
            .then(function(response) {
                if (response.result === 'success') {
                    // 从DOM中移除卡片
                    self.element.remove();
                    
                    // 显示成功消息
                    self.showSuccessMessage('任务已删除');
                } else {
                    alert('删除失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                console.error('删除任务失败:', error);
                alert('删除失败，请稍后重试');
            });
    };

    /**
     * 更新卡片显示
     */
    KanbanCard.prototype.updateCardDisplay = function() {
        var titleElement = this.element.querySelector('.kanban-card-title');
        var descriptionElement = this.element.querySelector('.kanban-card-description');
        
        // 更新标题
        titleElement.textContent = this.data.card_title || '无标题';
        
        // 更新描述
        if (this.data.card_description) {
            if (!descriptionElement) {
                descriptionElement = document.createElement('div');
                descriptionElement.className = 'kanban-card-description';
                titleElement.parentNode.insertBefore(descriptionElement, titleElement.nextSibling);
            }
            descriptionElement.textContent = this.data.card_description;
        } else if (descriptionElement) {
            descriptionElement.remove();
        }
        
        // 更新优先级样式
        this.element.classList.remove('priority-high');
        if (this.data.card_priority === 'high' || this.data.card_priority === 'urgent') {
            this.element.classList.add('priority-high');
        }
        
        // 更新颜色
        if (this.data.card_color && this.data.card_color !== '#ffffff') {
            this.element.style.backgroundColor = this.data.card_color;
        } else {
            this.element.style.backgroundColor = '';
        }
    };

    /**
     * 调用更新任务API
     */
    KanbanCard.prototype.updateTaskAPI = function(taskData) {
        var self = this;
        return new Promise(function(resolve, reject) {
            var params = new URLSearchParams({
                action: 'kanban',
                format: 'json',
                kanban_action: 'updatetask',
                task_id: taskData.card_id,
                title: taskData.title,
                description: taskData.description,
                priority: taskData.priority,
                color: taskData.color,
                due_date: taskData.due_date || '',
                status_id: taskData.status_id
            });
            
            // 添加用户认证
            if (mw.user.isAnon()) {
                reject(new Error('需要登录才能保存任务'));
                return;
            }
            
            fetch(mw.config.get('wgScriptPath') + '/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.error) {
                    reject(new Error(data.error.info || 'API error'));
                } else {
                    resolve(data);
                }
            })
            .catch(function(error) {
                reject(error);
            });
        });
    };

    /**
     * 调用删除任务API
     */
    KanbanCard.prototype.deleteTaskAPI = function() {
        var self = this;
        return new Promise(function(resolve, reject) {
            var params = new URLSearchParams({
                action: 'kanban',
                format: 'json',
                kanban_action: 'deletetask',
                task_id: self.data.card_id
            });
            
            // 添加用户认证
            if (mw.user.isAnon()) {
                reject(new Error('需要登录才能删除任务'));
                return;
            }
            
            fetch(mw.config.get('wgScriptPath') + '/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.error) {
                    reject(new Error(data.error.info || 'API error'));
                } else {
                    resolve(data);
                }
            })
            .catch(function(error) {
                reject(error);
            });
        });
    };

    /**
     * 格式化日期时间用于输入框
     */
    KanbanCard.prototype.formatDateTimeForInput = function(dateString) {
        if (!dateString) return '';
        
        try {
            var date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            
            // 转换为本地时间格式 YYYY-MM-DDTHH:MM
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            
            return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
        } catch (e) {
            return '';
        }
    };
    
    /**
     * 加载任务历史记录
     */
    KanbanCard.prototype.loadTaskHistory = function(dialog) {
        var self = this;
        var historyContainer = dialog.querySelector('.history-container');
        var historyLoading = dialog.querySelector('.history-loading');
        var historyList = dialog.querySelector('.history-list');
        var historyEmpty = dialog.querySelector('.history-empty');
        
        // 显示加载状态
        historyLoading.style.display = 'block';
        historyList.style.display = 'none';
        historyEmpty.style.display = 'none';
        
        // 调用API获取历史记录
        var params = new URLSearchParams({
            action: 'kanban',
            format: 'json',
            kanban_action: 'gethistory',
            task_id: self.data.card_id,
            limit: 50
        });
        
        fetch(mw.config.get('wgScriptPath') + '/api.php?' + params.toString())
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            historyLoading.style.display = 'none';
            
            if (data.error) {
                console.error('获取历史记录失败:', data.error);
                historyEmpty.style.display = 'block';
                historyEmpty.innerHTML = '<p>加载历史记录失败</p>';
                return;
            }
            
            if (data.history && data.history.length > 0) {
                self.renderTaskHistory(data.history, historyList);
                historyList.style.display = 'block';
            } else {
                historyEmpty.style.display = 'block';
            }
        })
        .catch(function(error) {
            console.error('获取历史记录失败:', error);
            historyLoading.style.display = 'none';
            historyEmpty.style.display = 'block';
            historyEmpty.innerHTML = '<p>加载历史记录失败</p>';
        });
    };
    
    /**
     * 渲染任务历史记录
     */
    KanbanCard.prototype.renderTaskHistory = function(history, container) {
        var self = this;
        container.innerHTML = '';
        
        history.forEach(function(record) {
            var historyItem = document.createElement('div');
            historyItem.className = 'history-item';
            
            var changeText = self.formatHistoryChange(record);
            var timeText = self.formatHistoryTime(record.changed_at);
            
            historyItem.innerHTML = `
                <div class="history-header">
                    <span class="history-action">${changeText}</span>
                    <span class="history-time">${timeText}</span>
                </div>
                <div class="history-details">
                    <span class="history-user">${record.changed_by || '未知用户'}</span>
                    ${record.change_reason ? '<span class="history-reason">' + record.change_reason + '</span>' : ''}
                </div>
            `;
            
            container.appendChild(historyItem);
        });
    };
    
    /**
     * 格式化历史记录变更
     */
    KanbanCard.prototype.formatHistoryChange = function(record) {
        var fieldNames = {
            'title': '标题',
            'description': '描述',
            'priority': '优先级',
            'color': '颜色',
            'status_id': '状态',
            'due_date': '截止日期',
            'deleted': '删除状态',
            'created': '创建'
        };
        
        var fieldName = fieldNames[record.field_name] || record.field_name;
        var changeType = record.change_type;
        
        if (changeType === 'create') {
            return '创建了任务';
        } else if (changeType === 'delete') {
            return '删除了任务';
        } else if (changeType === 'update') {
            if (record.field_name === 'status_id') {
                return '移动了任务状态';
            } else {
                return '修改了' + fieldName;
            }
        }
        
        return '变更了' + fieldName;
    };
    
    /**
     * 格式化历史记录时间
     */
    KanbanCard.prototype.formatHistoryTime = function(timeString) {
        try {
            var date = new Date(timeString);
            var now = new Date();
            var diff = now - date;
            
            // 小于1分钟
            if (diff < 60000) {
                return '刚刚';
            }
            // 小于1小时
            else if (diff < 3600000) {
                return Math.floor(diff / 60000) + '分钟前';
            }
            // 小于1天
            else if (diff < 86400000) {
                return Math.floor(diff / 3600000) + '小时前';
            }
            // 小于1周
            else if (diff < 604800000) {
                return Math.floor(diff / 86400000) + '天前';
            }
            // 超过1周，显示具体日期
            else {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            }
        } catch (e) {
            return timeString;
        }
    };


    /**
     * 显示成功消息
     */
    KanbanCard.prototype.showSuccessMessage = function(message) {
        var messageEl = document.createElement('div');
        messageEl.className = 'kanban-success-message';
        messageEl.textContent = message;
        
        document.body.appendChild(messageEl);
        
        setTimeout(function() {
            messageEl.remove();
        }, 3000);
    };


    // 初始化所有看板
    mw.hook('wikipage.content').add(function() {
        document.querySelectorAll('.kanban-board').forEach(function(element) {
            if (!element.kanbanInitialized) {
                new KanbanBoard(element);
                element.kanbanInitialized = true;
            }
        });
    });

    // 兼容旧版本
    $(document).ready(function() {
        $('.kanban-board').each(function() {
            if (!this.kanbanInitialized) {
                new KanbanBoard(this);
                this.kanbanInitialized = true;
            }
        });
    });

    // 复制看板引用代码功能
    window.copyKanbanCode = function(button) {
        const codeElement = button.parentElement.querySelector('.kanban-reference-code');
        const code = codeElement.getAttribute('data-code');
        
        // 使用现代浏览器的 Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(function() {
                showCopySuccess(button);
            }).catch(function(err) {
                console.error('复制失败:', err);
                fallbackCopy(code, button);
            });
        } else {
            // 降级方案
            fallbackCopy(code, button);
        }
    };
    
    // 降级复制方案
    function fallbackCopy(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(button);
            } else {
                console.error('复制失败');
            }
        } catch (err) {
            console.error('复制失败:', err);
        }
        
        document.body.removeChild(textArea);
    }
    
    // 显示复制成功状态
    function showCopySuccess(button) {
        const originalText = button.textContent;
        button.textContent = '已复制';
        button.classList.add('copied');
        
        setTimeout(function() {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
    }

}() );