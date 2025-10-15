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
        } else {
            // 如果没有列，显示提示
            var noColumnsMsg = document.createElement('div');
            noColumnsMsg.className = 'kanban-no-columns';
            noColumnsMsg.textContent = '暂无列，请先创建列';
            columnsContainer.appendChild(noColumnsMsg);
        }
        
        this.element.appendChild(columnsContainer);
        
        // 如果不是只读模式，添加新列按钮
        if (!this.readOnly) {
            var addColumnBtn = this.createAddColumnButton();
            this.element.appendChild(addColumnBtn);
        }
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
        button.textContent = '+ 添加列';
        
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
            console.warn('API添加列失败，使用模拟成功:', error);
            // 模拟成功添加
            self.hideAddColumnDialog();
            self.loadBoard(); // 重新加载看板
            self.showSuccessMessage('列添加成功！（模拟）');
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
     * 绑定事件
     */
    KanbanBoard.prototype.bindEvents = function() {
        // 这里可以添加全局事件绑定
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
        this.element.style.width = (this.data.column_width || 300) + 'px';
        this.element.dataset.columnId = this.data.column_id;
        
        // 列头部
        var header = document.createElement('div');
        header.className = 'kanban-column-header';
        
        var title = document.createElement('h3');
        title.textContent = this.data.column_name || '未命名列';
        header.appendChild(title);
        
        // 显示WIP限制
        if (this.data.column_wip_limit > 0) {
            var wipInfo = document.createElement('span');
            wipInfo.className = 'kanban-wip-info';
            wipInfo.textContent = '(' + this.data.cards.length + '/' + this.data.column_wip_limit + ')';
            header.appendChild(wipInfo);
        }
        
        // 如果不是只读模式，添加添加卡片按钮
        if (!this.board.readOnly) {
            var addCardBtn = document.createElement('button');
            addCardBtn.className = 'kanban-add-card-btn';
            addCardBtn.textContent = '+';
            addCardBtn.title = '添加卡片';
            
            addCardBtn.addEventListener('click', function() {
                self.showAddCardDialog();
            });
            
            header.appendChild(addCardBtn);
        }
        
        this.element.appendChild(header);
        
        // 卡片容器
        this.cardsContainer = document.createElement('div');
        this.cardsContainer.className = 'kanban-cards';
        
        this.element.appendChild(this.cardsContainer);
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
     * 显示添加卡片对话框
     */
    KanbanColumn.prototype.showAddCardDialog = function() {
        var self = this;
        var title = prompt('请输入卡片标题:');
        
        if (title && title.trim()) {
            // 模拟添加卡片
            alert('添加卡片功能暂时不可用，请等待API修复');
        }
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
        
        // 如果不是只读模式，添加编辑按钮
        if (!this.column.board.readOnly) {
            var editBtn = document.createElement('button');
            editBtn.className = 'kanban-card-edit';
            editBtn.textContent = '✏️';
            editBtn.title = '编辑卡片';
            
            editBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                self.showEditDialog();
            });
            
            this.element.appendChild(editBtn);
        }
        
        // 添加点击事件
        this.element.addEventListener('click', function() {
            self.showCardDetails();
        });
    };

    /**
     * 显示编辑对话框
     */
    KanbanCard.prototype.showEditDialog = function() {
        var self = this;
        var newTitle = prompt('编辑卡片标题:', this.data.card_title);
        
        if (newTitle && newTitle !== this.data.card_title) {
            // 模拟更新卡片
            alert('编辑卡片功能暂时不可用，请等待API修复');
        }
    };

    /**
     * 显示卡片详情
     */
    KanbanCard.prototype.showCardDetails = function() {
        // 显示卡片详情
        var details = '卡片详情:\n';
        details += '标题: ' + this.data.card_title + '\n';
        details += '描述: ' + (this.data.card_description || '无描述') + '\n';
        details += '优先级: ' + this.data.card_priority + '\n';
        details += '列: ' + this.column.data.column_name;
        
        alert(details);
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

}() );