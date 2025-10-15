/**
 * MediaWiki Kanban Board Extension - Main JavaScript (Static Version)
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
        
        this.init();
    }

    KanbanBoard.prototype.init = function() {
        this.loadBoard();
        this.bindEvents();
    };

    /**
     * 加载看板数据（静态版本）
     */
    KanbanBoard.prototype.loadBoard = function() {
        var self = this;
        
        this.showLoading();
        
        // 模拟API调用延迟
        setTimeout(function() {
            // 使用静态测试数据
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
                        cards: []
                    }
                ]
            };
            
            self.renderBoard(boardData);
            self.hideLoading();
        }, 1000);
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
        var name = prompt('请输入列名称:');
        
        if (name && name.trim()) {
            // 模拟添加列
            alert('添加列功能暂时不可用，请等待API修复');
        }
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
        this.element.dataset.columnId = this.data.column_id;
        
        // 列头部
        var header = document.createElement('div');
        header.className = 'kanban-column-header';
        
        var title = document.createElement('h3');
        title.textContent = this.data.column_name || '未命名列';
        header.appendChild(title);
        
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