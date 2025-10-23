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
        console.log('KanbanBoard constructor called with element:', element);
        this.element = element;
        this.boardId = element.dataset.boardId;
        this.readOnly = element.dataset.readonly === 'true';
        this.columns = [];
        this.api = new mw.Api();
        
        console.log('KanbanBoard initialized with boardId:', this.boardId, 'readOnly:', this.readOnly);
        this.init();
    }

    KanbanBoard.prototype.init = function() {
        console.log('KanbanBoard.init() called');
        this.loadBoard();
        this.bindEvents();
    };

    /**
     * 加载看板数据
     */
    KanbanBoard.prototype.loadBoard = function() {
        var self = this;
        
        console.log('KanbanBoard.loadBoard called, boardId:', this.boardId);
        this.showLoading();
        
        console.log('Making API call...');
        // 尝试从API加载数据，如果失败则使用静态数据
        this.api.post({
            action: 'kanban',
            kanban_action: 'getboard',
            board_id: this.boardId
        }).done(function(data) {
            console.log('API Response:', data); // 调试信息
            if (data.board) {
                console.log('Board milestones:', data.board.milestones); // 调试里程碑数据
                self.renderBoard(data.board);
            } else {
                console.error('看板数据格式错误，缺少board字段');
                self.showError('看板数据格式错误');
            }
            self.hideLoading();
        }).fail(function(error) {
            // API加载失败，显示错误信息
            console.error('API Error:', error);
            self.showError('无法加载看板数据：' + (error.error || '网络错误'));
            self.hideLoading();
        });
    };


    /**
     * 渲染看板
     */
    KanbanBoard.prototype.renderBoard = function(boardData) {
        var self = this;
        
        console.log('KanbanBoard.renderBoard called with data:', boardData);
        
        this.element.innerHTML = '';
        
        // 创建看板头部
        var header = this.createHeader(boardData);
        this.element.appendChild(header);
        
        // 创建列容器
        var columnsContainer = document.createElement('div');
        columnsContainer.className = 'kanban-columns';
        
        // 渲染每一列
        if (boardData.columns && boardData.columns.length > 0) {
            console.log('Rendering', boardData.columns.length, 'columns');
            boardData.columns.forEach(function(columnData) {
                console.log('Rendering column:', columnData);
                var column = new KanbanColumn(columnData, self);
                self.columns.push(column);
                columnsContainer.appendChild(column.element);
            });
        } else {
            console.log('No columns to render');
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
        
        // 创建里程碑容器（始终显示）
        console.log('Checking milestones:', boardData.milestones); // 调试信息
        var milestonesContainer = this.createMilestonesContainer(boardData.milestones || []);
        console.log('Milestones container created:', milestonesContainer);
        this.element.appendChild(milestonesContainer);
        console.log('Milestones container added to DOM');
        
        // 绑定拖拽事件
        this.bindColumnDragEvents();
    };

    /**
     * 创建看板头部
     */
    KanbanBoard.prototype.createHeader = function(boardData) {
        var header = document.createElement('div');
        header.className = 'kanban-header';
        
        var title = document.createElement('h3');
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
            // API添加列成功
            self.hideAddColumnDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('列添加成功！');
        }).fail(function(error) {
            // API添加列失败，使用前端模拟
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
            // 拖拽手柄未找到
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
        
        // 发送列顺序数据
        
        // 发送API请求
        var params = {
            action: 'kanban',
            kanban_action: 'reordercolumns',
            board_id: this.boardId,
            column_orders: JSON.stringify(columnOrders)
        };
        
        // API请求参数
        
        this.api.post(params).done(function(data) {
            // 列顺序保存成功
            self.showSuccessMessage('列顺序已更新');
        }).fail(function(error) {
            // 保存列顺序失败
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
        
        // 绑定卡片拖拽事件
        this.bindColumnCardDragEvents();
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
                // 未知的菜单动作
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
                <div class="dialog-body">
                <form class="kanban-task-form">
                    <div class="form-group">
                        <label for="task-title">任务标题 *</label>
                        <input type="text" id="task-title" name="title" 
                               maxlength="500" required placeholder="请输入任务标题">
                    </div>
                    
                    <div class="form-group">
                        <label for="task-description">任务描述</label>
                            <textarea id="task-description" name="description" rows="12" 
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
                    
                    <div class="form-group">
                        <label for="task-due-date">截止日期</label>
                        <input type="datetime-local" id="task-due-date" name="due_date">
                    </div>
                        </div>
                    </form>
                </div>
                    <div class="dialog-footer">
                    <div class="btn-group-left">
                        <button type="button" class="btn btn-secondary cancel-btn">取消</button>
                    </div>
                    <div class="btn-group-right">
                        <button type="submit" class="btn btn-primary save-btn">创建任务</button>
                    </div>
                </div>
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
            self.createTask(form, dialog);
        });
        
        // 创建任务按钮点击事件
        var saveBtn = dialog.querySelector('.save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.createTask(form, dialog);
            });
        }
    };

    /**
     * 创建新任务
     */
    KanbanColumn.prototype.createTask = function(form, dialog) {
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
        var saveBtn = dialog.querySelector('.save-btn');
        var originalText = saveBtn.textContent;
        saveBtn.textContent = '创建中...';
        saveBtn.disabled = true;
        
        // 调用API创建任务
        this.createTaskAPI(taskData)
            .then(function(response) {
                console.log('创建任务API响应:', response);
                if (response.result === 'success') {
                    // 检查task_id是否存在
                    if (!response.task_id) {
                        console.error('API响应中缺少task_id:', response);
                        alert('创建失败：服务器响应格式错误');
                        return;
                    }
                    
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
                    
                    console.log('创建新卡片数据:', newCardData);
                    
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
                    
                    // 关闭对话框
                    dialog.remove();
                } else {
                    alert('创建失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                // 创建任务失败
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
                // 更新列失败
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
            // API删除列成功
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
     * 防抖函数
     */
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * 卡片类
     */
    function KanbanCard(cardData, column) {
        this.data = cardData;
        this.column = column;
        
        // 缓存DOM元素引用
        this.cachedElements = {};
        
        // 调试信息
        console.log('创建KanbanCard对象:', this.data);
        if (!this.data.card_id) {
            console.warn('卡片数据缺少 card_id:', this.data);
        }
        
        this.createElement();
    }

    /**
     * 缓存DOM元素查询
     */
    KanbanCard.prototype.getCachedElement = function(selector, cacheKey) {
        if (!this.cachedElements[cacheKey]) {
            this.cachedElements[cacheKey] = this.element.querySelector(selector);
        }
        return this.cachedElements[cacheKey];
    };
    
    /**
     * 清除缓存
     */
    KanbanCard.prototype.clearCache = function() {
        this.cachedElements = {};
    };

    KanbanCard.prototype.createElement = function() {
        var self = this;
        
        this.element = document.createElement('div');
        this.element.className = 'kanban-card';
        this.element.dataset.cardId = this.data.card_id;
        
        console.log('设置卡片DOM元素cardId:', this.data.card_id, '实际设置的值:', this.element.dataset.cardId);
        
        // 根据优先级设置颜色
        if (this.data.card_priority === 'high' || this.data.card_priority === 'urgent') {
            this.element.classList.add('priority-high');
        }
        
        var title = document.createElement('div');
        title.className = 'kanban-card-title';
        title.textContent = this.data.card_title || '无标题';
        this.element.appendChild(title);
        
        // 添加任务号显示
        var taskNumber = document.createElement('div');
        taskNumber.className = 'kanban-card-task-number';
        taskNumber.textContent = this.data.card_id;
        this.element.appendChild(taskNumber);
        
        if (this.data.card_description) {
            var description = document.createElement('div');
            description.className = 'kanban-card-description';
            description.textContent = this.data.card_description;
            this.element.appendChild(description);
        }
        
        // 添加完成时间显示
        if (this.isTaskCompleted()) {
            var completedAt = document.createElement('div');
            completedAt.className = 'kanban-card-completed-at';
            
            if (this.data.card_completed_at) {
                var completedDate = new Date(this.data.card_completed_at);
                completedAt.textContent = '✅ 完成于 ' + completedDate.toLocaleDateString('zh-CN') + ' ' + completedDate.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            } else {
                completedAt.textContent = '✅ 已完成';
            }
            
            this.element.appendChild(completedAt);
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
            e.preventDefault();
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
        
        // 重新渲染两个列（但不更新完成状态）
        sourceColumn.renderCards();
        targetColumn.renderCards();
        
        // 发送API请求保存新顺序和状态
        this.saveCardOrder(board).then(function() {
            // API成功后，根据目标列的状态更新完成时间
            if (targetColumn.data.is_terminal && !cardData.card_completed_at) {
                cardData.card_completed_at = new Date().toISOString();
            } else if (!targetColumn.data.is_terminal && cardData.card_completed_at) {
                cardData.card_completed_at = null;
            }
            
            // 重新渲染卡片以显示正确的完成状态
            sourceColumn.renderCards();
            targetColumn.renderCards();
        }).catch(function(error) {
            console.error('保存卡片顺序失败:', error);
            // API失败时，恢复原始状态
            board.loadBoard();
        });
    };

    /**
     * 保存卡片顺序
     */
    KanbanCard.prototype.saveCardOrder = function(board) {
        var self = this;
        
        return new Promise(function(resolve, reject) {
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
            
            board.api.post(params).done(function(data) {
                console.log('API保存卡片顺序成功:', data);
                self.showSuccessMessage('卡片顺序已保存');
                resolve(data);
            }).fail(function(error) {
                console.error('保存卡片顺序失败:', error);
                self.showErrorMessage('保存卡片顺序失败，请刷新页面重试');
                reject(error);
            });
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
        
        console.log('moveToNewStatus called:', {
            newStatusId: newStatusId,
            currentColumnId: self.data.column_id,
            taskData: taskData
        });
        
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
        
        console.log('targetColumn found:', targetColumn);
        
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
        console.log('Removing card from current column:', {
            currentColumn: currentColumn,
            cardsContainer: currentColumn ? currentColumn.cardsContainer : null,
            element: self.element,
            parentNode: self.element.parentNode
        });
        
        if (currentColumn && currentColumn.cardsContainer && self.element.parentNode) {
            currentColumn.cardsContainer.removeChild(self.element);
            console.log('Card removed from current column');
        }
        
        // 添加到目标列
        console.log('Adding card to target column:', {
            targetColumn: targetColumn,
            cardsContainer: targetColumn ? targetColumn.cardsContainer : null
        });
        
        if (targetColumn && targetColumn.cardsContainer) {
            targetColumn.cardsContainer.appendChild(self.element);
            self.column = targetColumn;
            console.log('Card added to target column');
            
            // 从当前列的卡片数组中移除
            if (currentColumn && currentColumn.cards) {
                var cardIndex = currentColumn.cards.indexOf(self);
                if (cardIndex > -1) {
                    currentColumn.cards.splice(cardIndex, 1);
                    console.log('Card removed from current column cards array');
                }
            }
            
            // 添加到目标列的卡片数组中
            if (targetColumn.cards) {
                targetColumn.cards.push(self);
                console.log('Card added to target column cards array');
            }
            
            // 移除目标列的"暂无卡片"提示
            var noCardsMsg = targetColumn.cardsContainer.querySelector('.kanban-no-cards');
            if (noCardsMsg) {
                noCardsMsg.remove();
                console.log('Removed no-cards message from target column');
            }
            
            // 如果当前列没有卡片了，显示"暂无卡片"提示
            if (currentColumn && currentColumn.cards && currentColumn.cards.length === 0) {
                var noCardsMsg = document.createElement('div');
                noCardsMsg.className = 'kanban-no-cards';
                noCardsMsg.textContent = '暂无卡片';
                currentColumn.cardsContainer.appendChild(noCardsMsg);
                console.log('Added no-cards message to current column');
            }
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
                    <h3>任务详情 ${this.data.card_id}</h3>
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
                        <label for="task-due-date">${this.isTaskCompleted() ? '实际完成日期' : '截止日期'}</label>
                        <input type="datetime-local" id="task-due-date" name="due_date" 
                               value="${this.isTaskCompleted() ? this.formatDateTimeForInput(this.data.card_completed_at) : this.formatDateTimeForInput(this.data.card_due_date)}">
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
            self.saveTask(form, dialog);
        });
        
        // 保存按钮点击事件
        var saveBtn = dialog.querySelector('.save-btn');
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            self.saveTask(form, dialog);
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
    KanbanCard.prototype.saveTask = function(form, dialog) {
        var self = this;
        var formData = new FormData(form);
        
        var taskData = {
            card_id: this.data.card_id || this.element.dataset.cardId,
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            priority: formData.get('priority'),
            color: formData.get('color'),
            due_date: formData.get('due_date') || null,
            status_id: formData.get('status_id')
        };
        
        // 验证任务ID
        if (!taskData.card_id || taskData.card_id === '') {
            // 任务ID为空，无法保存任务
            console.error('任务ID为空，无法保存任务');
            console.error('this.data:', this.data);
            console.error('taskData:', taskData);
            alert('任务ID无效，无法保存任务。请刷新页面后重试。');
            return;
        }
        
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
        var saveBtn = dialog.querySelector('.save-btn');
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
                        
                        // 使用API返回的更新后的任务数据
                        if (response.task) {
                            self.data.card_completed_at = response.task.card_completed_at;
                            self.data.card_updated_at = response.task.card_updated_at;
                        }
                        
                        // 重新渲染卡片
                        self.updateCardDisplay();
                    }
                    
                    // 显示成功消息
                    self.showSuccessMessage('任务已保存');
                    
                    // 关闭对话框
                    dialog.remove();
                } else {
                    alert('保存失败：' + (response.message || '未知错误'));
                }
            })
            .catch(function(error) {
                // 保存任务失败
                console.error('保存任务失败:', error);
                self.showErrorMessage('保存失败，请稍后重试');
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
        var titleElement = this.getCachedElement('.kanban-card-title', 'title');
        var descriptionElement = this.getCachedElement('.kanban-card-description', 'description');
        
        // 更新标题
        if (titleElement) {
            titleElement.textContent = this.data.card_title || '无标题';
        }
        
        // 更新描述
        if (this.data.card_description) {
            if (!descriptionElement) {
                descriptionElement = document.createElement('div');
                descriptionElement.className = 'kanban-card-description';
                titleElement.parentNode.insertBefore(descriptionElement, titleElement.nextSibling);
                // 更新缓存
                this.cachedElements.description = descriptionElement;
            }
            descriptionElement.textContent = this.data.card_description;
        } else if (descriptionElement) {
            descriptionElement.remove();
            // 清除缓存
            this.cachedElements.description = null;
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
        
        // 更新完成时间显示
        this.updateCompletedAtDisplay();
    };
    
    /**
     * 更新完成时间显示
     */
    KanbanCard.prototype.updateCompletedAtDisplay = function() {
        var completedAtElement = this.getCachedElement('.kanban-card-completed-at', 'completedAt');
        
        if (this.isTaskCompleted()) {
            if (!completedAtElement) {
                // 创建完成时间显示元素
                completedAtElement = document.createElement('div');
                completedAtElement.className = 'kanban-card-completed-at';
                this.element.appendChild(completedAtElement);
                // 更新缓存
                this.cachedElements.completedAt = completedAtElement;
            }
            
            if (this.data.card_completed_at) {
                var completedDate = new Date(this.data.card_completed_at);
                completedAtElement.textContent = '✅ 完成于 ' + completedDate.toLocaleDateString('zh-CN') + ' ' + completedDate.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            } else {
                completedAtElement.textContent = '✅ 已完成';
            }
        } else if (completedAtElement) {
            // 任务未完成，移除完成时间显示
            completedAtElement.remove();
            // 清除缓存
            this.cachedElements.completedAt = null;
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
     * 格式化完成时间显示
     */
    KanbanCard.prototype.formatCompletedTime = function(dateString) {
        if (!dateString) return '';
        
        try {
            var date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            
            var now = new Date();
            var diffMs = now.getTime() - date.getTime();
            var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) {
                return '今天 ' + date.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            } else if (diffDays === 1) {
                return '昨天 ' + date.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            } else if (diffDays < 7) {
                return diffDays + '天前 ' + date.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            } else {
                return date.toLocaleDateString('zh-CN') + ' ' + date.toLocaleTimeString('zh-CN', {hour: '2-digit', minute: '2-digit'});
            }
        } catch (e) {
            return '';
        }
    };
    
    /**
     * 判断任务是否已完成
     */
    KanbanCard.prototype.isTaskCompleted = function() {
        // 首先检查card_id是否存在，如果不存在说明卡片数据有问题
        if (!this.data.card_id) {
            console.warn('卡片缺少card_id，无法判断完成状态:', this.data);
            return false;
        }
        
        // 检查column和board是否存在
        if (!this.column || !this.column.board || !this.column.board.columns) {
            console.warn('卡片缺少必要的列或看板信息，无法判断完成状态');
            return false;
        }
        
        // 检查当前状态是否为终态
        var currentColumn = this.column.board.columns.find(function(col) {
            return col.data.column_id == this.data.column_id;
        }.bind(this));
        
        if (currentColumn && currentColumn.data.is_terminal) {
            // 只有在有明确的completed_at字段时才显示为已完成
            return this.data.card_completed_at !== null && this.data.card_completed_at !== undefined && this.data.card_completed_at !== '';
        }
        
        // 非终态列不应该显示为已完成
        return false;
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
        console.log('wikipage.content hook triggered');
        document.querySelectorAll('.kanban-board').forEach(function(element) {
            console.log('Found kanban-board element:', element);
            if (!element.kanbanInitialized) {
                console.log('Initializing KanbanBoard for element:', element);
                new KanbanBoard(element);
                element.kanbanInitialized = true;
            }
        });
    });

    // 兼容旧版本
    $(document).ready(function() {
        console.log('Document ready, looking for kanban-board elements');
        $('.kanban-board').each(function() {
            console.log('Found kanban-board element in jQuery:', this);
            if (!this.kanbanInitialized) {
                console.log('Initializing KanbanBoard for element:', this);
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

    /**
     * 绑定列的卡片拖拽事件
     */
    KanbanColumn.prototype.bindColumnCardDragEvents = function() {
        var self = this;
        
        // 拖拽进入
        this.cardsContainer.addEventListener('dragenter', function(e) {
            e.preventDefault();
            var board = self.board;
            if (board && board.draggedCard && board.draggedCard.column !== self) {
                self.handleCardDragEnter(e);
            }
        });
        
        // 拖拽悬停
        this.cardsContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            var board = self.board;
            if (board && board.draggedCard && board.draggedCard.column !== self) {
                self.handleCardDragOver(e);
            }
        });
        
        // 拖拽离开
        this.cardsContainer.addEventListener('dragleave', function(e) {
            // 只有当鼠标真正离开容器时才移除指示器
            if (!self.cardsContainer.contains(e.relatedTarget)) {
                self.removeCardDragIndicator();
            }
        });
        
        // 放置
        this.cardsContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            
            var draggedCardId = e.dataTransfer.getData('text/plain');
            var board = self.board;
            if (board && board.draggedCard) {
                var draggedCard = board.draggedCard;
                self.handleCardDrop(draggedCard, e);
                
                // 清理拖拽状态
                board.draggedCard = null;
                board.draggedCardId = null;
                
                // 移除所有拖拽指示器和样式
                draggedCard.removeCardDragIndicators();
                draggedCard.removeDropTargetStyles();
            }
        });
    };
    
    /**
     * 处理卡片拖拽进入
     */
    KanbanColumn.prototype.handleCardDragEnter = function(e) {
        // 如果列是空的，显示拖拽指示器
        if (this.cards.length === 0) {
            this.showEmptyColumnIndicator();
        }
    };
    
    /**
     * 处理卡片拖拽悬停
     */
    KanbanColumn.prototype.handleCardDragOver = function(e) {
        // 如果列是空的，保持指示器显示
        if (this.cards.length === 0) {
            this.showEmptyColumnIndicator();
        }
    };
    
    /**
     * 处理卡片放置
     */
    KanbanColumn.prototype.handleCardDrop = function(draggedCard, e) {
        var self = this;
        
        // 移除指示器
        this.removeCardDragIndicator();
        
        // 如果拖拽到空列，直接添加到列的开头
        if (this.cards.length === 0) {
            this.moveCardToEmptyColumn(draggedCard);
        }
    };
    
    /**
     * 显示空列拖拽指示器
     */
    KanbanColumn.prototype.showEmptyColumnIndicator = function() {
        // 移除现有指示器
        this.removeCardDragIndicator();
        
        // 创建指示器
        var indicator = document.createElement('div');
        indicator.className = 'kanban-card-drag-indicator empty-column';
        indicator.textContent = '拖拽到此处';
        indicator.style.textAlign = 'center';
        indicator.style.padding = '20px';
        indicator.style.color = '#666';
        indicator.style.border = '2px dashed #ccc';
        indicator.style.borderRadius = '8px';
        indicator.style.margin = '10px';
        
        this.cardsContainer.appendChild(indicator);
    };
    
    /**
     * 移除卡片拖拽指示器
     */
    KanbanColumn.prototype.removeCardDragIndicator = function() {
        var indicator = this.cardsContainer.querySelector('.kanban-card-drag-indicator');
        if (indicator) {
            indicator.remove();
        }
    };
    
    /**
     * 移动卡片到空列
     */
    KanbanColumn.prototype.moveCardToEmptyColumn = function(draggedCard) {
        var self = this;
        var board = this.board;
        if (!board) return;
        
        var sourceColumn = draggedCard.column;
        var sourceCards = sourceColumn.data.cards || [];
        var targetCards = this.data.cards || [];
        
        // 找到拖拽卡片在源列中的索引
        var draggedIndex = sourceCards.findIndex(function(card) {
            return card.card_id === draggedCard.data.card_id;
        });
        
        if (draggedIndex === -1) {
            console.error('无法找到拖拽卡片索引');
            return;
        }
        
        // 从源列移除卡片
        var cardData = sourceCards.splice(draggedIndex, 1)[0];
        
        // 更新卡片的列ID和状态
        cardData.column_id = self.data.column_id;
        cardData.status_id = self.data.column_id;
        cardData.status_name = self.data.column_name;
        cardData.task_order = 0;
        
        // 插入到目标列的开头
        targetCards.splice(0, 0, cardData);
        
        // 更新卡片的列引用
        draggedCard.column = self;
        
        // 重新渲染两个列
        sourceColumn.renderCards();
        self.renderCards();
        
        // 发送API请求保存新顺序和状态
        draggedCard.saveCardOrder(board);
    };
    
    /**
     * 移除卡片
     */
    KanbanColumn.prototype.removeCard = function(card) {
        var index = this.cards.indexOf(card);
        if (index > -1) {
            this.cards.splice(index, 1);
            card.element.remove();
        }
    };

    /**
     * 创建里程碑容器
     */
    KanbanBoard.prototype.createMilestonesContainer = function(milestones) {
        var self = this;
        var container = document.createElement('div');
        container.className = 'kanban-milestones-container';
        
        // 创建里程碑标题
        var title = document.createElement('h4');
        title.className = 'kanban-milestones-title';
        title.textContent = '里程碑';
        container.appendChild(title);
        
        // 创建里程碑时间轴
        var timeline = document.createElement('div');
        timeline.className = 'kanban-milestones-timeline';
        
        // 按时间顺序排序里程碑
        var sortedMilestones = milestones.slice().sort(function(a, b) {
            var dateA = new Date(a.target_date || '9999-12-31');
            var dateB = new Date(b.target_date || '9999-12-31');
            return dateA - dateB;
        });
        
        // 渲染每个里程碑
        if (sortedMilestones.length > 0) {
            sortedMilestones.forEach(function(milestoneData, index) {
                var milestone = self.createMilestoneCard(milestoneData);
                timeline.appendChild(milestone);
                
                // 如果不是最后一个里程碑，添加箭头连接
                if (index < sortedMilestones.length - 1) {
                    var arrow = self.createTimelineArrow();
                    timeline.appendChild(arrow);
                }
            });
        } else {
            // 没有里程碑时显示提示信息
            var noMilestonesMsg = document.createElement('div');
            noMilestonesMsg.className = 'kanban-no-milestones';
            noMilestonesMsg.textContent = '暂无里程碑';
            noMilestonesMsg.style.textAlign = 'center';
            noMilestonesMsg.style.color = '#6c757d';
            noMilestonesMsg.style.fontStyle = 'italic';
            noMilestonesMsg.style.padding = '20px';
            timeline.appendChild(noMilestonesMsg);
        }
        
        // 如果不是只读模式，在时间轴末尾添加添加里程碑按钮
        if (!this.readOnly) {
            var addMilestoneBtn = this.createAddMilestoneButton();
            timeline.appendChild(addMilestoneBtn);
        }
        
        container.appendChild(timeline);
        
        return container;
    };
    
    /**
     * 创建里程碑卡片
     */
    KanbanBoard.prototype.createMilestoneCard = function(milestoneData) {
        var self = this;
        var card = document.createElement('div');
        card.className = 'kanban-milestone-card';
        card.dataset.milestoneId = milestoneData.milestone_id;
        
        // 设置卡片样式（任务卡片的一半大小）
        card.style.width = '150px';
        card.style.height = '53px';
        card.style.backgroundColor = milestoneData.color || '#9b59b6';
        card.style.borderRadius = '8px';
        card.style.padding = '6px';
        card.style.margin = '4px';
        card.style.position = 'relative';
        card.style.cursor = 'pointer';
        card.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        card.style.transition = 'transform 0.2s ease';
        
        // 根据背景色亮度自动调整文字颜色
        var textColor = self.getContrastColor(milestoneData.color || '#9b59b6');
        
        // 鼠标悬停效果
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
        
        // 创建里程碑内容
        var title = document.createElement('div');
        title.className = 'milestone-title';
        title.textContent = milestoneData.title;
        title.style.fontSize = '10px';
        title.style.fontWeight = 'bold';
        title.style.color = textColor;
        title.style.marginBottom = '2px';
        title.style.overflow = 'hidden';
        title.style.textOverflow = 'ellipsis';
        title.style.whiteSpace = 'nowrap';
        
        var date = document.createElement('div');
        date.className = 'milestone-date';
        if (milestoneData.target_date) {
            var targetDate = new Date(milestoneData.target_date);
            date.textContent = targetDate.toLocaleDateString();
        }
        date.style.fontSize = '8px';
        date.style.color = textColor;
        date.style.opacity = '0.8';
        
        var status = document.createElement('div');
        status.className = 'milestone-status';
        status.textContent = this.getMilestoneStatusText(milestoneData.status);
        status.style.fontSize = '8px';
        status.style.color = textColor;
        status.style.opacity = '0.9';
        status.style.position = 'absolute';
        status.style.top = '2px';
        status.style.right = '2px';
        status.style.background = textColor === '#ffffff' ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.2)';
        status.style.padding = '1px 3px';
        status.style.borderRadius = '2px';
        
        card.appendChild(title);
        card.appendChild(date);
        card.appendChild(status);
        
        // 点击事件
        card.addEventListener('click', function() {
            self.showMilestoneDialog(milestoneData);
        });
        
        return card;
    };
    
    /**
     * 创建时间轴箭头
     */
    KanbanBoard.prototype.createTimelineArrow = function() {
        var arrow = document.createElement('div');
        arrow.className = 'kanban-timeline-arrow';
        arrow.innerHTML = '→';
        arrow.style.fontSize = '20px';
        arrow.style.color = '#666';
        arrow.style.margin = '0 8px';
        arrow.style.alignSelf = 'center';
        return arrow;
    };
    
    /**
     * 创建添加里程碑按钮
     */
    KanbanBoard.prototype.createAddMilestoneButton = function() {
        var self = this;
        var button = document.createElement('button');
        button.className = 'kanban-add-milestone-btn';
        
        // 创建按钮内容（和添加列按钮保持一致）
        var icon = document.createElement('div');
        icon.style.fontSize = '20px';
        icon.style.marginBottom = '2px';
        icon.style.lineHeight = '1';
        icon.textContent = '+';
        
        var text = document.createElement('div');
        text.style.fontSize = '11px';
        text.style.lineHeight = '1';
        text.textContent = '添加里程碑';
        
        button.appendChild(icon);
        button.appendChild(text);
        
        button.addEventListener('click', function() {
            self.showMilestoneDialog();
        });
        
        return button;
    };
    
    /**
     * 显示里程碑对话框
     */
    KanbanBoard.prototype.showMilestoneDialog = function(milestoneData) {
        var self = this;
        var isEdit = !!milestoneData;
        
        // 创建对话框HTML
        var dialogHtml = `
            <div class="kanban-milestone-dialog" id="milestoneDialog">
                <div class="dialog-overlay"></div>
                <div class="dialog-content">
                    <div class="dialog-header">
                        <h3>${isEdit ? '编辑里程碑' : '添加里程碑'}</h3>
                        <button class="dialog-close">&times;</button>
                    </div>
                    <form class="kanban-milestone-form" id="milestoneForm">
                        <div class="form-group">
                            <label for="milestoneTitle">里程碑标题 *</label>
                            <input type="text" id="milestoneTitle" name="title" required maxlength="255" 
                                   placeholder="请输入里程碑标题" value="${milestoneData ? milestoneData.title : ''}">
                        </div>
                        <div class="form-group">
                            <label for="milestoneDescription">里程碑描述</label>
                            <textarea id="milestoneDescription" name="description" rows="3" 
                                      placeholder="请输入里程碑描述（可选）">${milestoneData ? milestoneData.description || '' : ''}</textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="milestoneTargetDate">目标日期</label>
                                <input type="date" id="milestoneTargetDate" name="target_date" 
                                       value="${milestoneData ? milestoneData.target_date || '' : ''}">
                            </div>
                            <div class="form-group">
                                <label for="milestoneStatus">状态</label>
                                <select id="milestoneStatus" name="status">
                                    <option value="planned" ${milestoneData && milestoneData.status === 'planned' ? 'selected' : ''}>计划中</option>
                                    <option value="in_progress" ${milestoneData && milestoneData.status === 'in_progress' ? 'selected' : ''}>进行中</option>
                                    <option value="completed" ${milestoneData && milestoneData.status === 'completed' ? 'selected' : ''}>已完成</option>
                                    <option value="cancelled" ${milestoneData && milestoneData.status === 'cancelled' ? 'selected' : ''}>已取消</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="milestoneColor">颜色</label>
                                <div class="color-picker">
                                    <input type="color" id="milestoneColor" name="color" 
                                           value="${milestoneData ? milestoneData.color || '#9b59b6' : '#9b59b6'}">
                                    <div class="color-presets">
                                        <span class="color-preset" data-color="#9b59b6" title="紫色" style="width: 3px; height: 3px; background-color: #9b59b6; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                        <span class="color-preset" data-color="#e74c3c" title="红色" style="width: 3px; height: 3px; background-color: #e74c3c; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                        <span class="color-preset" data-color="#f39c12" title="橙色" style="width: 3px; height: 3px; background-color: #f39c12; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                        <span class="color-preset" data-color="#27ae60" title="绿色" style="width: 3px; height: 3px; background-color: #27ae60; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                        <span class="color-preset" data-color="#3498db" title="蓝色" style="width: 3px; height: 3px; background-color: #3498db; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                        <span class="color-preset" data-color="#e67e22" title="深橙色" style="width: 3px; height: 3px; background-color: #e67e22; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel">取消</button>
                            ${isEdit ? '<button type="button" class="btn-delete">删除</button>' : ''}
                            <button type="submit" class="btn-primary">${isEdit ? '更新' : '创建'}</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        // 添加到页面
        document.body.insertAdjacentHTML('beforeend', dialogHtml);
        
        // 绑定事件
        this.bindMilestoneDialogEvents(milestoneData);
        
        // 显示对话框
        document.getElementById('milestoneDialog').style.display = 'block';
    };
    
    /**
     * 绑定里程碑对话框事件
     */
    KanbanBoard.prototype.bindMilestoneDialogEvents = function(milestoneData) {
        var self = this;
        var dialog = document.getElementById('milestoneDialog');
        var form = document.getElementById('milestoneForm');
        var isEdit = !!milestoneData;
        
        // 关闭对话框
        dialog.querySelector('.dialog-close').addEventListener('click', function() {
            self.hideMilestoneDialog();
        });
        
        dialog.querySelector('.dialog-overlay').addEventListener('click', function() {
            self.hideMilestoneDialog();
        });
        
        dialog.querySelector('.btn-cancel').addEventListener('click', function() {
            self.hideMilestoneDialog();
        });
        
        // 颜色预设点击
        dialog.querySelectorAll('.color-preset').forEach(function(preset) {
            preset.addEventListener('click', function() {
                var color = this.dataset.color;
                dialog.querySelector('#milestoneColor').value = color;
            });
        });
        
        // 删除按钮
        if (isEdit) {
            dialog.querySelector('.btn-delete').addEventListener('click', function() {
                if (confirm('确定要删除这个里程碑吗？')) {
                    self.deleteMilestone(milestoneData.milestone_id);
                }
            });
        }
        
        // 表单提交
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isEdit) {
                self.updateMilestone(milestoneData.milestone_id, form);
            } else {
                self.createMilestone(form);
            }
        });
    };
    
    /**
     * 隐藏里程碑对话框
     */
    KanbanBoard.prototype.hideMilestoneDialog = function() {
        var dialog = document.getElementById('milestoneDialog');
        if (dialog) {
            dialog.remove();
        }
    };
    
    /**
     * 创建里程碑
     */
    KanbanBoard.prototype.createMilestone = function(form) {
        var self = this;
        var formData = new FormData(form);
        var params = {
            action: 'kanban',
            kanban_action: 'createmilestone',
            board_id: this.boardId
        };
        
        // 添加表单数据
        for (var [key, value] of formData.entries()) {
            params[key] = value;
        }
        
        // 显示加载状态
        var submitBtn = form.querySelector('.btn-primary');
        var originalText = submitBtn.textContent;
        submitBtn.textContent = '创建中...';
        submitBtn.disabled = true;
        
        // 发送API请求
        this.api.post(params).done(function(data) {
            self.hideMilestoneDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('里程碑创建成功！');
        }).fail(function(error) {
            self.showErrorMessage('创建里程碑失败：' + (error.error || '未知错误'));
        }).always(function() {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    };
    
    /**
     * 更新里程碑
     */
    KanbanBoard.prototype.updateMilestone = function(milestoneId, form) {
        var self = this;
        var formData = new FormData(form);
        var params = {
            action: 'kanban',
            kanban_action: 'updatemilestone',
            milestone_id: milestoneId
        };
        
        // 添加表单数据
        for (var [key, value] of formData.entries()) {
            params[key] = value;
        }
        
        // 显示加载状态
        var submitBtn = form.querySelector('.btn-primary');
        var originalText = submitBtn.textContent;
        submitBtn.textContent = '更新中...';
        submitBtn.disabled = true;
        
        // 发送API请求
        this.api.post(params).done(function(data) {
            self.hideMilestoneDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('里程碑更新成功！');
        }).fail(function(error) {
            self.showErrorMessage('更新里程碑失败：' + (error.error || '未知错误'));
        }).always(function() {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    };
    
    /**
     * 删除里程碑
     */
    KanbanBoard.prototype.deleteMilestone = function(milestoneId) {
        var self = this;
        var params = {
            action: 'kanban',
            kanban_action: 'deletemilestone',
            milestone_id: milestoneId
        };
        
        // 发送API请求
        this.api.post(params).done(function(data) {
            self.hideMilestoneDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('里程碑删除成功！');
        }).fail(function(error) {
            self.showErrorMessage('删除里程碑失败：' + (error.error || '未知错误'));
        });
    };
    
    /**
     * 获取里程碑状态文本
     */
    KanbanBoard.prototype.getMilestoneStatusText = function(status) {
        var statusMap = {
            'planned': '计划中',
            'in_progress': '进行中',
            'completed': '已完成',
            'cancelled': '已取消'
        };
        return statusMap[status] || '未知';
    };

    /**
     * 根据背景色计算合适的文字颜色
     */
    KanbanBoard.prototype.getContrastColor = function(hexColor) {
        // 移除 # 号
        var color = hexColor.replace('#', '');
        
        // 转换为 RGB
        var r = parseInt(color.substr(0, 2), 16);
        var g = parseInt(color.substr(2, 2), 16);
        var b = parseInt(color.substr(4, 2), 16);
        
        // 计算亮度 (使用相对亮度公式)
        var brightness = (r * 299 + g * 587 + b * 114) / 1000;
        
        // 根据亮度返回黑色或白色
        return brightness > 128 ? '#000000' : '#ffffff';
    };

    // 初始化所有看板
    mw.hook('wikipage.content').add(function() {
        console.log('wikipage.content hook triggered');
        document.querySelectorAll('.kanban-board').forEach(function(element) {
            console.log('Found kanban-board element:', element);
            if (!element.kanbanInitialized) {
                console.log('Initializing KanbanBoard for element:', element);
                new KanbanBoard(element);
                element.kanbanInitialized = true;
            }
        });
    });

    // 兼容旧版本
    $(document).ready(function() {
        console.log('Document ready, looking for kanban-board elements');
        $('.kanban-board').each(function() {
            console.log('Found kanban-board element in jQuery:', this);
            if (!this.kanbanInitialized) {
                console.log('Initializing KanbanBoard for element:', this);
                new KanbanBoard(this);
                this.kanbanInitialized = true;
            }
        });
    });

    // 复制看板代码功能
    window.copyKanbanCode = function(button) {
        const codeElement = button.parentElement.querySelector('.kanban-reference-code');
        if (codeElement) {
            const text = codeElement.textContent;
            navigator.clipboard.writeText(text).then(function() {
                button.textContent = '已复制';
                setTimeout(function() {
                    button.textContent = '复制代码';
                }, 2000);
            });
        }
    };

    // 统计功能
    function initKanbanStats() {
        console.log('initKanbanStats 被调用');
        const container = document.getElementById('kanban-stats-container');
        if (!container) {
            console.error('找不到 kanban-stats-container 元素');
            return;
        }
        
        const boardId = container.dataset.boardId;
        console.log('看板ID:', boardId);
        
        // 不再检查boardId，允许查询所有看板
        
        // 绑定时间范围按钮事件
        bindTimeRangeEvents();
        
        // 绑定自定义时间范围事件
        bindCustomTimeRangeEvents();
        
        // 默认加载最近一周的数据
        console.log('开始加载默认数据...');
        loadStatsData(null, 'week', 'time_desc');
    }
    
    // 绑定时间范围按钮事件
    function bindTimeRangeEvents() {
        const timeRangeBtns = document.querySelectorAll('.time-range-btn');
        console.log('找到时间范围按钮数量:', timeRangeBtns.length);
        
        timeRangeBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                console.log('点击时间范围按钮:', this.dataset.range);
                // 移除所有active类
                timeRangeBtns.forEach(function(b) { b.classList.remove('active'); });
                // 添加active类到当前按钮
                this.classList.add('active');
                
                // 获取当前排序方式
                const activeSortBtn = document.querySelector('.sort-btn.active');
                const sortBy = activeSortBtn ? activeSortBtn.dataset.sort : 'time_desc';
                
                // 加载统计数据
                loadStatsData(null, this.dataset.range, sortBy);
            });
        });
    }
    
    // 绑定自定义时间范围事件
    function bindCustomTimeRangeEvents() {
        const timeRangeBtns = document.querySelectorAll('.time-range-btn');
        const customTimeRange = document.getElementById('custom-time-range');
        const applyBtn = document.getElementById('apply-custom-range');
        
        // 监听时间范围按钮点击
        timeRangeBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.dataset.range === 'custom') {
                    customTimeRange.style.display = 'block';
                } else {
                    customTimeRange.style.display = 'none';
                }
            });
        });
        
        // 监听应用按钮点击
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                const startDate = document.getElementById('start-date').value;
                const endDate = document.getElementById('end-date').value;
                
                if (!startDate || !endDate) {
                    alert('请选择开始日期和结束日期');
                    return;
                }
                
                if (new Date(startDate) > new Date(endDate)) {
                    alert('开始日期不能晚于结束日期');
                    return;
                }
                
                // 获取当前排序方式
                const activeSortBtn = document.querySelector('.sort-btn.active');
                const sortBy = activeSortBtn ? activeSortBtn.dataset.sort : 'time_desc';
                
                // 加载自定义时间范围的数据
                loadStatsDataWithCustomRange(startDate, endDate, sortBy);
            });
        }
    }
    
    // 加载自定义时间范围的数据
    function loadStatsDataWithCustomRange(startDate, endDate, sortBy) {
        const api = new mw.Api();
        
        console.log('加载自定义时间范围数据:', {startDate: startDate, endDate: endDate, sortBy: sortBy});
        
        // 显示加载状态
        showLoadingState();
        
        // 构建API参数
        const apiParams = {
            action: 'kanban',
            kanban_action: 'getstats',
            time_range: 'custom',
            start_date: startDate,
            end_date: endDate,
            format: 'json'
        };
        
        // 获取统计数据
        api.post(apiParams).done(function(data) {
            console.log('API响应:', data);
            if (data.stats) {
                updateStatsDisplay(data.stats, sortBy);
            } else {
                console.error('API响应格式错误:', data);
                showErrorState('数据格式错误');
            }
        }).fail(function(error) {
            console.error('获取统计数据失败:', error);
            showErrorState('获取统计数据失败: ' + (error.error || '网络错误'));
        });
    }
    
    // 将initKanbanStats函数暴露到全局作用域
    window.initKanbanStats = initKanbanStats;
    
    function loadStatsData(boardId, timeRange, sortBy) {
        const api = new mw.Api();
        
        console.log('加载统计数据:', {boardId: boardId, timeRange: timeRange, sortBy: sortBy});
        
        // 显示加载状态
        showLoadingState();
        
        // 构建API参数
        const apiParams = {
            action: 'kanban',
            kanban_action: 'getstats',
            time_range: timeRange,
            format: 'json'
        };
        
        // 只有在指定了boardId时才添加board_id参数
        if (boardId) {
            apiParams.board_id = boardId;
        }
        
        // 获取统计数据
        api.post(apiParams).done(function(data) {
            console.log('API响应:', data);
            if (data.stats) {
                updateStatsDisplay(data.stats, sortBy);
            } else {
                console.error('API响应格式错误:', data);
                showErrorState('数据格式错误');
            }
        }).fail(function(error) {
            console.error('获取统计数据失败:', error);
            showErrorState('获取统计数据失败: ' + (error.error || '网络错误'));
        });
    }
    
    function showLoadingState() {
        // 更新统计数字
        document.getElementById('total-tasks').textContent = '...';
        document.getElementById('completed-tasks').textContent = '...';
        document.getElementById('completion-rate').textContent = '...';
        document.getElementById('avg-completion-time').textContent = '...';
        
        // 显示任务加载状态
        const tasksContainer = document.getElementById('weekly-tasks');
        if (tasksContainer) {
            tasksContainer.innerHTML = '<div class="tasks-loading">加载中...</div>';
        }
    }
    
    function updateStatsDisplay(data, sortBy) {
        // 更新总体统计
        document.getElementById('total-tasks').textContent = data.overview.total_tasks || 0;
        document.getElementById('completed-tasks').textContent = data.overview.completed_tasks || 0;
        document.getElementById('completion-rate').textContent = (data.overview.completion_rate || 0) + '%';
        document.getElementById('avg-completion-time').textContent = (data.overview.avg_completion_time || 0) + '天';
        
        // 更新任务展示
        updateTasksDisplay(data.time_range_tasks || {}, sortBy);
        
        // 更新图表
        updateTrendChart(data.trend_data || {});
    }
    
    function updateTasksDisplay(timeRangeData, sortBy) {
        const tasksContainer = document.getElementById('weekly-tasks');
        
        if (!timeRangeData || !timeRangeData.tasks || timeRangeData.tasks.length === 0) {
            tasksContainer.innerHTML = '<div class="tasks-loading">暂无完成的任务</div>';
            return;
        }
        
        // 对任务进行排序
        let sortedTasks = [...timeRangeData.tasks];
        
        switch(sortBy) {
            case 'time_desc':
                sortedTasks.sort(function(a, b) {
                    return new Date(b.completed_at) - new Date(a.completed_at);
                });
                break;
            case 'time_asc':
                sortedTasks.sort(function(a, b) {
                    return new Date(a.completed_at) - new Date(b.completed_at);
                });
                break;
            case 'board_asc':
                sortedTasks.sort(function(a, b) {
                    return (a.board_name || '').localeCompare(b.board_name || '');
                });
                break;
            case 'board_desc':
                sortedTasks.sort(function(a, b) {
                    return (b.board_name || '').localeCompare(a.board_name || '');
                });
                break;
            default:
                // 默认按时间倒序
                sortedTasks.sort(function(a, b) {
                    return new Date(b.completed_at) - new Date(a.completed_at);
                });
        }
        
        // 创建任务卡片布局
        let tasksHtml = '<div class="time-range-tasks-container">';
        
        // 时间范围标题
        const timeRangeLabels = {
            'week': '最近一周',
            'month': '最近一月',
            'quarter': '最近三月',
            'year': '最近一年',
            'all': '全部时间'
        };
        
        tasksHtml += `
            <div class="time-range-header">
                <div class="header-left">
                    <h4>${timeRangeLabels[timeRangeData.time_range] || '最近一月'} (${timeRangeData.start_date} - ${timeRangeData.end_date})</h4>
                    <span class="time-range-count">完成 ${timeRangeData.completed_count} 个任务</span>
                </div>
                <div class="header-right">
                    <div class="sort-controls">
                        <span class="sort-label">排序:</span>
                        <div class="sort-buttons">
                            <button class="sort-btn ${sortBy === 'time_desc' || sortBy === 'time_asc' ? 'active' : ''}" data-sort="${sortBy === 'time_desc' ? 'time_desc' : 'time_asc'}" title="按时间排序">⏰${sortBy === 'time_desc' ? '↓' : '↑'}</button>
                            <button class="sort-btn ${sortBy === 'board_asc' || sortBy === 'board_desc' ? 'active' : ''}" data-sort="${sortBy === 'board_asc' ? 'board_asc' : 'board_desc'}" title="按看板排序">📋${sortBy === 'board_asc' ? 'A-Z' : 'Z-A'}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="time-range-tasks">
        `;
        
        // 显示排序后的任务卡片
        sortedTasks.forEach(function(task) {
            const priorityClass = task.priority || 'medium';
            const completedDate = new Date(task.completed_at);
            const formattedDate = completedDate.toLocaleDateString('zh-CN', {
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            tasksHtml += `
                <div class="task-card completed-task priority-${priorityClass}" style="border-left-color: ${task.color || '#27ae60'}">
                    <div class="task-title">${task.title}</div>
                    <div class="task-board">📋 ${task.board_name || '未知看板'}</div>
                    <div class="task-meta">
                        <span class="task-id">#${task.task_id}</span>
                        <span class="completed-time">✅ ${formattedDate}</span>
                    </div>
                </div>
            `;
        });
        
        tasksHtml += `
            </div>
        </div>
        `;
        
        tasksContainer.innerHTML = tasksHtml;
        
        // 绑定排序按钮事件
        bindSortButtonsInTasks();
    }
    
    // 绑定任务卡片中的排序按钮事件
    function bindSortButtonsInTasks() {
        const sortBtns = document.querySelectorAll('.time-range-tasks-container .sort-btn');
        
        sortBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                console.log('点击排序按钮:', this.dataset.sort);
                
                // 获取当前时间范围
                const activeTimeBtn = document.querySelector('.time-range-btn.active');
                const timeRange = activeTimeBtn ? activeTimeBtn.dataset.range : 'week';
                
                // 确定新的排序方式
                let newSortBy;
                if (this.dataset.sort === 'time_desc') {
                    // 如果当前是按时间倒序，切换到正序
                    newSortBy = 'time_asc';
                } else if (this.dataset.sort === 'time_asc') {
                    // 如果当前是按时间正序，切换到倒序
                    newSortBy = 'time_desc';
                } else if (this.dataset.sort === 'board_asc') {
                    // 如果当前是按看板正序，切换到倒序
                    newSortBy = 'board_desc';
                } else if (this.dataset.sort === 'board_desc') {
                    // 如果当前是按看板倒序，切换到正序
                    newSortBy = 'board_asc';
                } else {
                    newSortBy = this.dataset.sort;
                }
                
                // 更新按钮状态
                sortBtns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                
                // 更新按钮的data-sort属性
                this.dataset.sort = newSortBy;
                
                // 重新加载数据并应用排序
                loadStatsData(null, timeRange, newSortBy);
            });
        });
    }
    
    function updateTrendChart(trendData) {
        const chartContainer = document.getElementById('task-trend-chart');
        if (!chartContainer) return;
        
        if (!trendData || !trendData.days || trendData.days.length === 0) {
            chartContainer.innerHTML = '<div class="chart-loading">暂无数据</div>';
            return;
        }
        
        const days = trendData.days;
        const maxValue = Math.max(
            ...days.map(d => Math.max(d.created_count, d.completed_count))
        );
        
        if (maxValue === 0) {
            chartContainer.innerHTML = '<div class="chart-loading">暂无数据</div>';
            return;
        }
        
        // 创建SVG图表
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'task-trend-chart');
        svg.setAttribute('viewBox', '0 0 800 250');
        
        const width = 800;
        const height = 250;
        const padding = { top: 20, right: 20, bottom: 40, left: 60 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        
        // 绘制坐标轴
        const xAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        xAxis.setAttribute('class', 'chart-axis');
        xAxis.setAttribute('x1', padding.left);
        xAxis.setAttribute('y1', height - padding.bottom);
        xAxis.setAttribute('x2', width - padding.right);
        xAxis.setAttribute('y2', height - padding.bottom);
        svg.appendChild(xAxis);
        
        const yAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        yAxis.setAttribute('class', 'chart-axis');
        yAxis.setAttribute('x1', padding.left);
        yAxis.setAttribute('y1', padding.top);
        yAxis.setAttribute('x2', padding.left);
        yAxis.setAttribute('y2', height - padding.bottom);
        svg.appendChild(yAxis);
        
        // 绘制网格线
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight / 5) * i;
            const gridLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            gridLine.setAttribute('class', 'chart-axis');
            gridLine.setAttribute('x1', padding.left);
            gridLine.setAttribute('y1', y);
            gridLine.setAttribute('x2', width - padding.right);
            gridLine.setAttribute('y2', y);
            gridLine.setAttribute('stroke-dasharray', '2,2');
            svg.appendChild(gridLine);
            
            // Y轴标签
            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('class', 'chart-label');
            label.setAttribute('x', padding.left - 10);
            label.setAttribute('y', y + 4);
            label.setAttribute('text-anchor', 'end');
            label.textContent = Math.round(maxValue * (5 - i) / 5);
            svg.appendChild(label);
        }
        
        // 绘制创建任务折线
        const createdPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        createdPath.setAttribute('class', 'chart-line created');
        let createdPathData = '';
        
        // 绘制完成任务折线
        const completedPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        completedPath.setAttribute('class', 'chart-line completed');
        let completedPathData = '';
        
        days.forEach((day, index) => {
            const x = padding.left + (chartWidth / (days.length - 1)) * index;
            const createdY = height - padding.bottom - (day.created_count / maxValue) * chartHeight;
            const completedY = height - padding.bottom - (day.completed_count / maxValue) * chartHeight;
            
            if (index === 0) {
                createdPathData += `M ${x} ${createdY}`;
                completedPathData += `M ${x} ${completedY}`;
            } else {
                createdPathData += ` L ${x} ${createdY}`;
                completedPathData += ` L ${x} ${completedY}`;
            }
            
            // X轴标签
            if (index % Math.ceil(days.length / 8) === 0 || index === days.length - 1) {
                const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                label.setAttribute('class', 'chart-label');
                label.setAttribute('x', x);
                label.setAttribute('y', height - padding.bottom + 20);
                label.setAttribute('text-anchor', 'middle');
                label.textContent = day.label;
                svg.appendChild(label);
            }
        });
        
        createdPath.setAttribute('d', createdPathData);
        completedPath.setAttribute('d', completedPathData);
        svg.appendChild(createdPath);
        svg.appendChild(completedPath);
        
        // 创建图例
        const legend = document.createElement('div');
        legend.className = 'chart-legend';
        legend.innerHTML = `
            <div class="legend-item">
                <div class="legend-color created"></div>
                <span>创建任务</span>
            </div>
            <div class="legend-item">
                <div class="legend-color completed"></div>
                <span>完成任务</span>
            </div>
        `;
        
        chartContainer.innerHTML = '';
        chartContainer.appendChild(svg);
        chartContainer.appendChild(legend);
    }
    
    function showErrorState(message) {
        const tasksContainer = document.getElementById('weekly-tasks');
        if (tasksContainer) {
            tasksContainer.innerHTML = '<div class="tasks-loading" style="color: #dc3545;">' + message + '</div>';
        }
    }
    
    // 添加图表样式
    const chartStyles = `
        <style>
        .simple-chart {
            display: flex;
            align-items: end;
            justify-content: space-around;
            height: 100%;
            padding: 20px;
            gap: 10px;
        }
        
        .chart-bar {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 80px;
        }
        
        .bar-fill {
            background: linear-gradient(to top, #007bff, #0056b3);
            width: 100%;
            min-height: 20px;
            border-radius: 4px 4px 0 0;
            transition: height 0.3s ease;
        }
        
        .bar-label {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            text-align: center;
            word-break: break-all;
        }
        
        .bar-value {
            font-size: 14px;
            font-weight: bold;
            color: #007bff;
            margin-top: 4px;
        }
        </style>
    `;
    
    // 将样式添加到页面
    if (!document.getElementById('kanban-chart-styles')) {
        const styleElement = document.createElement('div');
        styleElement.id = 'kanban-chart-styles';
        styleElement.innerHTML = chartStyles;
        document.head.appendChild(styleElement);
    }

})();
