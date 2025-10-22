<?php
/**
 * Special page for Kanban Board management
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use SpecialPage;
use Html;
use MediaWiki\MediaWikiServices;
use Exception;

class SpecialKanbanBoard extends SpecialPage {
    
    public function __construct() {
        parent::__construct( 'KanbanBoard' );
    }
    
    public function execute( $subPage ) {
        $this->setHeaders();
        $this->getOutput()->addModules( 'ext.kanbanboard' );
        
        $user = $this->getUser();
        
        if ( !$user->isRegistered() ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-must-be-logged-in' );
            return;
        }
        
        // 处理表单提交
        if ( $this->getRequest()->wasPosted() ) {
            $action = $this->getRequest()->getText( 'action' );
            if ( $action === 'edit' ) {
                $this->handleEditBoard();
            } else {
                $this->handleCreateBoard();
            }
            return;
        }
        
        // 检查是否有 board 参数
        $boardId = $this->getRequest()->getInt( 'board' );
        if ( $boardId ) {
            $this->showBoard( $boardId );
            return;
        }
        
        // 检查是否有 stats 参数
        $statsParam = $this->getRequest()->getVal( 'stats' );
        if ( $statsParam ) {
            if ( $statsParam === 'all' ) {
                // 显示所有看板的统计
                $this->showAllBoardsStats();
            } else {
                // 显示特定看板的统计
                $statsBoardId = (int)$statsParam;
                if ( $statsBoardId > 0 ) {
                    $this->showBoardStats( $statsBoardId );
                }
            }
            return;
        }
        
        // 检查是否有 edit 参数
        $editBoardId = $this->getRequest()->getInt( 'edit' );
        if ( $editBoardId ) {
            $this->showEditBoardForm( $editBoardId );
            return;
        }
        
        if ( $subPage === 'create' ) {
            $this->showCreateBoardForm();
        } else {
            $this->showBoardList();
        }
    }
    
    /**
     * 显示所有看板
     */
    private function showBoardList() {
        $user = $this->getUser();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        // 读取状态筛选参数：active | hidden | archived | deleted | all
        $request = $this->getRequest();
        $filterStatus = $request->getText( 'filter_status', 'all' );
        $validFilters = [ 'active', 'hidden', 'archived', 'deleted', 'all' ];
        if ( !in_array( $filterStatus, $validFilters, true ) ) {
            $filterStatus = 'all';
        }
        
        // 管理表格：始终查询全部看板
        $allBoards = $db->select(
            'kanban_boards',
            '*',
            [],
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        $html = Html::element( 'h2', [], $this->msg( 'kanbanboard-board-list' )->text() );
        
        // 筛选与创建按钮行
        $html .= Html::openElement( 'div', [ 'style' => 'display:flex; gap:10px; align-items:center; margin: 8px 0; justify-content:space-between;' ] );
        $html .= Html::openElement( 'div', [ 'style' => 'display:flex; gap:10px; align-items:center;' ] );
        // 状态筛选下拉
        $html .= Html::openElement( 'form', [ 'method' => 'get', 'action' => $this->getPageTitle()->getLocalURL() ] );
        $html .= Html::openElement( 'label', [ 'for' => 'filter-status', 'style' => 'margin-right:6px;' ] ) . '筛选状态' . Html::closeElement( 'label' );
        $html .= Html::openElement( 'select', [ 'id' => 'filter-status', 'name' => 'filter_status', 'onchange' => 'this.form.submit()' ] );
        $options = [
            'active' => '活跃',
            'hidden' => '隐藏',
            'archived' => '存档',
            'deleted' => '已删除',
            'all' => '全部'
        ];
        foreach ( $options as $val => $label ) {
            $html .= Html::element( 'option', [ 'value' => $val, 'selected' => $filterStatus === $val ? 'selected' : null ], $label );
        }
        $html .= Html::closeElement( 'select' );
        $html .= Html::closeElement( 'form' );
        
        // 创建新看板按钮
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle( 'create' )->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg( 'kanbanboard-create-board' )->text() );
        $html .= Html::closeElement( 'div' );
        
        // 统计按钮（右对齐）
        $html .= Html::openElement( 'div', [ 'style' => 'margin-right: 10px;' ] );
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle()->getLocalURL( [ 'stats' => 'all' ] ),
            'class' => 'mw-ui-button mw-ui-quiet',
            'title' => '查看任务完成情况统计'
        ], '📊 统计' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::element( 'hr' );
        
        // 显示管理表格
        if ( $allBoards->numRows() > 0 ) {
            $html .= Html::element( 'h3', [], $this->msg( 'kanbanboard-board-management' )->text() );
            $html .= $this->renderBoardTable( $allBoards, $user );
            
            $html .= Html::element( 'hr' );
            
            // 显示嵌入的看板（按状态与权限过滤）
            $html .= Html::element( 'h2', [], $this->msg( 'kanbanboard-all-boards' )->text() );
            foreach ( $allBoards as $board ) {
                $isOwner = ( $board->board_owner_id == $user->getId() );
                $status = $board->board_status ?? 'active';
                
                // 下拉筛选仅影响“所有看板”区域
                if ( $filterStatus !== 'all' && $status !== $filterStatus ) {
                    continue;
                }
                // 已删除：不展示
                if ( $status === 'deleted' ) {
                    continue;
                }
                // 隐藏：任何人都不展示
                if ( $status === 'hidden' ) {
                    continue;
                }
                // 活跃：公开或所有者可见
                if ( $status === 'active' && !$isOwner ) {
                    if ( $board->visibility !== 'public' ) {
                        continue;
                    }
                }
                $html .= $this->renderEmbeddedBoard( $board, $user );
            }
        } else {
            $html .= Html::element( 'p', [ 'class' => 'kanban-no-boards' ], $this->msg( 'kanbanboard-no-boards' )->text() );
        }
        
        $this->getOutput()->addHTML( $html );
        
        // 保留并已修复的内联脚本（见上一次修改）
        $this->getOutput()->addInlineScript( <<<JS
            function changeBoardStatus(boardId, newStatus) {
                if (confirm("确定要更改看板状态吗？")) {
                    var actionMap = {
                        "hidden": "hideboard",
                        "archived": "archiveboard",
                        "deleted": "deleteboard",
                        "active": "restoreboard"
                    };
                    var mappedAction = actionMap[newStatus] || "";
                    if (!mappedAction) {
                        alert("无效的状态：" + newStatus);
                        return;
                    }
                    var params = new URLSearchParams({
                        action: "kanban",
                        format: "json",
                        kanban_action: mappedAction,
                        board_id: boardId
                    });
                    fetch(mw.config.get("wgScriptPath") + "/api.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        credentials: "same-origin",
                        body: params
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.result === "success") {
                            location.reload();
                        } else {
                            var msg = (data && data.error && (data.error.info || data.error.code)) || (data.message || data.result) || "未知错误";
                            alert("操作失败：" + msg);
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        alert("操作失败，请稍后重试");
                    });
                }
            }
JS
        );
    }
    
    /**
     * 渲染嵌入的看板
     */
    private function renderEmbeddedBoard( $board, $user ) {
        $status = $board->board_status ?? 'active';
        $containerClasses = 'kanban-board-container';
        if ( $status === 'archived' ) {
            $containerClasses .= ' kanban-board-archived';
        }
        $html = Html::openElement( 'div', [ 
            'class' => $containerClasses,
            'style' => 'margin: 20px 0; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'
        ] );
        
        // 看板标题和描述（添加链接）
        $boardUrl = $this->getPageTitle()->getLocalURL( [ 'board' => $board->board_id ] );
        $html .= Html::element( 'h3', [ 
            'style' => 'margin: 0 0 10px 0; color: #2c3e50;'
        ] );
        $html .= Html::element( 'a', [ 
            'href' => $boardUrl,
            'class' => 'kanban-board-link',
            'title' => '进入看板：' . $board->board_name,
            'style' => 'color: #007bff; text-decoration: none; font-weight: bold;'
        ], $board->board_name );
        $html .= Html::closeElement( 'h3' );
        
        if ( $board->board_description ) {
            $html .= Html::element( 'p', [ 
                'style' => 'margin: 0 0 15px 0; color: #6c757d;'
            ], $board->board_description );
        }
        
        // 看板元信息
        $html .= Html::openElement( 'div', [ 
            'style' => 'margin-bottom: 15px; font-size: 12px; color: #6c757d;'
        ] );
        
        $isOwner = $board->board_owner_id == $user->getId();
        if ( $isOwner ) {
            $html .= Html::element( 'span', [ 
                'style' => 'background: #e3f2fd; padding: 2px 8px; border-radius: 12px; margin-right: 10px;'
            ], $this->msg( 'kanbanboard-my-board' )->text() );
        }
        
        $permissionText = $board->visibility === 'public' ? '公开' : '私有';
        $permissionClass = $board->visibility === 'public' ? 'kanban-permission-public' : 'kanban-permission-private';
        $html .= Html::element( 'span', [ 
            'style' => 'background: #f8f9fa; padding: 2px 8px; border-radius: 12px; margin-right: 10px;'
        ], $permissionText );
        
        $html .= Html::element( 'span', [], '创建于 ' . $this->getLanguage()->userDate( $board->board_created_at, $user ) );
        $html .= Html::closeElement( 'div' );
        
        // 嵌入看板组件
        $html .= Html::openElement( 'div', [ 
            'class' => 'kanban-board',
            'data-board-id' => $board->board_id,
            'data-readonly' => 'false',
            'style' => 'min-height: 200px;'
        ] );
        $html .= Html::element( 'div', [ 
            'class' => 'kanban-loading'
        ], $this->msg( 'kanbanboard-loading' )->text() );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    /**
     * 显示看板统计页面
     */
    private function showBoardStats( $boardId ) {
        $user = $this->getUser();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        // 获取看板信息
        $board = $db->selectRow(
            'kanban_boards',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$board ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-board-not-found' );
            return;
        }
        
        // 检查权限
        $isOwner = $board->board_owner_id == $user->getId();
        if ( !$isOwner && $board->visibility !== 'public' ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-no-permission' );
            return;
        }
        
        $html = Html::element( 'h2', [], '任务完成情况统计' );
        
        // 返回按钮
        $html .= Html::openElement( 'div', [ 'style' => 'margin: 10px 0;' ] );
        $html .= Html::element( 'a', [ 
            'href' => $this->getPageTitle()->getLocalURL( [ 'board' => $boardId ] ),
            'class' => 'btn btn-secondary'
        ], '← 返回看板' );
        $html .= Html::closeElement( 'div' );
        
        // 统计容器
        $html .= Html::openElement( 'div', [ 
            'id' => 'kanban-stats-container',
            'class' => 'kanban-stats-container',
            'data-board-id' => $boardId
        ] );
        
        // 时间范围选择器
        $html .= Html::openElement( 'div', [ 'class' => 'stats-time-selector' ] );
        $html .= Html::element( 'h3', [], '时间范围' );
        $html .= Html::openElement( 'div', [ 'class' => 'time-range-buttons' ] );
        
        $ranges = [
            'week' => '最近一周',
            'month' => '最近一月', 
            'quarter' => '最近三月',
            'year' => '最近一年',
            'custom' => '自选范围',
            'all' => '全部时间'
        ];
        
        foreach ( $ranges as $key => $label ) {
            $html .= Html::element( 'button', [ 
                'class' => 'time-range-btn' . ( $key === 'month' ? ' active' : '' ),
                'data-range' => $key
            ], $label );
        }
        
        $html .= Html::closeElement( 'div' );
        
        // 自定义时间范围输入框
        $html .= Html::openElement( 'div', [ 'id' => 'custom-time-range', 'class' => 'custom-time-range', 'style' => 'display: none; margin-top: 15px;' ] );
        $html .= Html::openElement( 'div', [ 'class' => 'custom-time-inputs' ] );
        
        $html .= Html::openElement( 'div', [ 'class' => 'time-input-group' ] );
        $html .= Html::element( 'label', [ 'for' => 'start-date' ], '开始日期:' );
        $html .= Html::element( 'input', [ 
            'type' => 'date', 
            'id' => 'start-date', 
            'name' => 'start_date',
            'class' => 'time-input'
        ] );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'time-input-group' ] );
        $html .= Html::element( 'label', [ 'for' => 'end-date' ], '结束日期:' );
        $html .= Html::element( 'input', [ 
            'type' => 'date', 
            'id' => 'end-date', 
            'name' => 'end_date',
            'class' => 'time-input'
        ] );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::element( 'button', [ 
            'id' => 'apply-custom-range',
            'class' => 'btn btn-primary',
            'type' => 'button'
        ], '应用' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 统计卡片区域
        $html .= Html::openElement( 'div', [ 'class' => 'stats-cards' ] );
        
        // 总体统计
        $html .= Html::openElement( 'div', [ 'class' => 'stats-overview' ] );
        $html .= Html::element( 'h3', [], '总体统计' );
        $html .= Html::openElement( 'div', [ 'class' => 'overview-grid' ] );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'total-tasks' ], '0' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '总任务数' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'completed-tasks' ], '0' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '已完成' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'completion-rate' ], '0%' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '完成率' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'avg-completion-time' ], '0天' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '平均完成时间' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 任务趋势图表
        $html .= Html::openElement( 'div', [ 'class' => 'stats-chart' ] );
        $html .= Html::element( 'h3', [], '任务趋势' );
        $html .= Html::openElement( 'div', [ 'id' => 'task-trend-chart', 'class' => 'chart-container' ] );
        $html .= Html::element( 'div', [ 'class' => 'chart-loading' ], '加载中...' );
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 任务卡片展示区域
        $html .= Html::openElement( 'div', [ 'id' => 'weekly-tasks', 'class' => 'tasks-container' ] );
        $html .= Html::element( 'div', [ 'class' => 'tasks-loading' ], '加载中...' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        $this->getOutput()->addHTML( $html );
        
        // 添加统计相关的JavaScript
        $this->getOutput()->addInlineScript( "
            console.log('统计页面JavaScript开始执行');
            
            // 等待kanban.js模块加载完成
            function waitForKanbanModule() {
                console.log('等待kanban模块加载...');
                
                if (typeof initKanbanStats === 'function') {
                    console.log('initKanbanStats函数已可用，开始初始化');
                    initStatsWhenReady();
                } else {
                    console.log('initKanbanStats函数还未加载，100ms后重试');
                    setTimeout(waitForKanbanModule, 100);
                }
            }
            
            // 等待页面完全加载
            function initStatsWhenReady() {
                console.log('initStatsWhenReady 被调用, document.readyState:', document.readyState);
                
                if (document.readyState === 'loading') {
                    console.log('页面还在加载中，等待DOMContentLoaded事件');
                    document.addEventListener('DOMContentLoaded', initStatsWhenReady);
                    return;
                }
                
                console.log('页面已加载完成，开始初始化统计功能');
                
                // 延迟执行，确保DOM元素可用
                setTimeout(function() {
                    console.log('延迟执行开始，检查DOM元素');
                    
                    if (document.getElementById('kanban-stats-container')) {
                        console.log('找到kanban-stats-container元素，调用initKanbanStats');
                        if (typeof initKanbanStats === 'function') {
                            initKanbanStats();
                        } else {
                            console.error('initKanbanStats函数不存在');
                        }
                    } else {
                        console.error('找不到kanban-stats-container元素');
                    }
                }, 200);
            }
            
            // 开始等待kanban模块
            waitForKanbanModule();
        " );
    }
    
    /**
     * 显示所有看板的统计页面
     */
    private function showAllBoardsStats() {
        $html = Html::element( 'h2', [], '任务完成情况统计' );
        
        // 返回按钮
        $html .= Html::openElement( 'div', [ 'style' => 'margin: 10px 0;' ] );
        $html .= Html::element( 'a', [ 
            'href' => $this->getPageTitle()->getLocalURL(),
            'class' => 'btn btn-secondary'
        ], '← 返回看板列表' );
        $html .= Html::closeElement( 'div' );
        
        // 统计容器（不指定board_id，查询所有看板）
        $html .= Html::openElement( 'div', [ 
            'id' => 'kanban-stats-container',
            'class' => 'kanban-stats-container',
            'data-board-id' => ''
        ] );
        
        // 时间范围选择器
        $html .= Html::openElement( 'div', [ 'class' => 'stats-time-selector' ] );
        $html .= Html::element( 'h3', [], '时间范围' );
        $html .= Html::openElement( 'div', [ 'class' => 'time-range-buttons' ] );
        
        $ranges = [
            'week' => '最近一周',
            'month' => '最近一月', 
            'quarter' => '最近三月',
            'year' => '最近一年',
            'custom' => '自选范围',
            'all' => '全部时间'
        ];
        
        foreach ( $ranges as $key => $label ) {
            $html .= Html::element( 'button', [ 
                'class' => 'time-range-btn' . ( $key === 'month' ? ' active' : '' ),
                'data-range' => $key
            ], $label );
        }
        
        $html .= Html::closeElement( 'div' );
        
        // 自定义时间范围输入框
        $html .= Html::openElement( 'div', [ 'id' => 'custom-time-range', 'class' => 'custom-time-range', 'style' => 'display: none; margin-top: 15px;' ] );
        $html .= Html::openElement( 'div', [ 'class' => 'custom-time-inputs' ] );
        
        $html .= Html::openElement( 'div', [ 'class' => 'time-input-group' ] );
        $html .= Html::element( 'label', [ 'for' => 'start-date' ], '开始日期:' );
        $html .= Html::element( 'input', [ 
            'type' => 'date', 
            'id' => 'start-date', 
            'name' => 'start_date',
            'class' => 'time-input'
        ] );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'time-input-group' ] );
        $html .= Html::element( 'label', [ 'for' => 'end-date' ], '结束日期:' );
        $html .= Html::element( 'input', [ 
            'type' => 'date', 
            'id' => 'end-date', 
            'name' => 'end_date',
            'class' => 'time-input'
        ] );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::element( 'button', [ 
            'id' => 'apply-custom-range',
            'class' => 'btn btn-primary',
            'type' => 'button'
        ], '应用' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 统计卡片区域
        $html .= Html::openElement( 'div', [ 'class' => 'stats-cards' ] );
        
        // 总体统计
        $html .= Html::openElement( 'div', [ 'class' => 'stats-overview' ] );
        $html .= Html::element( 'h3', [], '总体统计' );
        $html .= Html::openElement( 'div', [ 'class' => 'overview-grid' ] );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'total-tasks' ], '0' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '总任务数' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'completed-tasks' ], '0' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '已完成' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'completion-rate' ], '0%' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '完成率' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::openElement( 'div', [ 'class' => 'stat-card' ] );
        $html .= Html::element( 'div', [ 'class' => 'stat-number', 'id' => 'avg-completion-time' ], '0天' );
        $html .= Html::element( 'div', [ 'class' => 'stat-label' ], '平均完成时间' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 任务趋势图表
        $html .= Html::openElement( 'div', [ 'class' => 'stats-chart' ] );
        $html .= Html::element( 'h3', [], '任务趋势' );
        $html .= Html::openElement( 'div', [ 'id' => 'task-trend-chart', 'class' => 'chart-container' ] );
        $html .= Html::element( 'div', [ 'class' => 'chart-loading' ], '加载中...' );
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        // 任务卡片展示区域
        $html .= Html::openElement( 'div', [ 'id' => 'weekly-tasks', 'class' => 'tasks-container' ] );
        $html .= Html::element( 'div', [ 'class' => 'tasks-loading' ], '加载中...' );
        $html .= Html::closeElement( 'div' );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        $this->getOutput()->addHTML( $html );
        
        // 添加统计相关的JavaScript（与showBoardStats相同）
        $this->getOutput()->addInlineScript( "
            console.log('统计页面JavaScript开始执行');
            
            // 等待kanban.js模块加载完成
            function waitForKanbanModule() {
                console.log('等待kanban模块加载...');
                
                if (typeof initKanbanStats === 'function') {
                    console.log('initKanbanStats函数已可用，开始初始化');
                    initStatsWhenReady();
                } else {
                    console.log('initKanbanStats函数还未加载，100ms后重试');
                    setTimeout(waitForKanbanModule, 100);
                }
            }
            
            function initStatsWhenReady() {
                console.log('initStatsWhenReady 被调用, document.readyState:', document.readyState);
                
                if (document.readyState === 'loading') {
                    console.log('页面还在加载中，等待DOMContentLoaded事件');
                    document.addEventListener('DOMContentLoaded', initStatsWhenReady);
                    return;
                }
                
                console.log('页面已加载完成，开始初始化统计功能');
                
                setTimeout(function() {
                    console.log('延迟执行开始，检查DOM元素');
                    if (document.getElementById('kanban-stats-container')) {
                        console.log('找到kanban-stats-container元素，调用initKanbanStats');
                        if (typeof initKanbanStats === 'function') {
                            initKanbanStats();
                        } else {
                            console.error('initKanbanStats函数不存在');
                        }
                    } else {
                        console.error('找不到kanban-stats-container元素');
                    }
                }, 200);
            }
            
            // 开始等待kanban模块
            waitForKanbanModule();
        " );
    }
    
    /**
     * 渲染看板表格
     */
    private function renderBoardTable( $boards, $user, $isOwnedOnly = false ) {
        $html = Html::openElement( 'table', [ 'class' => 'wikitable sortable kanban-board-table' ] );
        
        // 表头
        $html .= Html::openElement( 'thead' );
        $html .= Html::openElement( 'tr' );
        $html .= Html::element( 'th', [], '看板名称' );
        $html .= Html::element( 'th', [], '描述' );
        $html .= Html::element( 'th', [], '所有者' );
        $html .= Html::element( 'th', [], '权限' );
        $html .= Html::element( 'th', [], '状态' );
        $html .= Html::element( 'th', [], '创建时间' );
        $html .= Html::element( 'th', [], '引用代码' );
        $html .= Html::element( 'th', [], '操作' );
        $html .= Html::closeElement( 'tr' );
        $html .= Html::closeElement( 'thead' );
        
        // 表格内容
        $html .= Html::openElement( 'tbody' );
        
        foreach ( $boards as $board ) {
            $isOwner = $board->board_owner_id == $user->getId();
            
            $html .= Html::openElement( 'tr' );
            
            // 看板名称（添加链接）
            $html .= Html::openElement( 'td' );
            $boardUrl = $this->getPageTitle()->getLocalURL( [ 'board' => $board->board_id ] );
            $html .= Html::element( 'a', [ 
                'href' => $boardUrl,
                'class' => 'kanban-board-link',
                'title' => '进入看板：' . $board->board_name
            ], $board->board_name );
            $html .= Html::closeElement( 'td' );
            
            // 描述
            $html .= Html::openElement( 'td' );
            $html .= Html::element( 'span', [], $board->board_description ?: '-' );
            $html .= Html::closeElement( 'td' );
            
            // 所有者
            $html .= Html::openElement( 'td' );
            if ( $isOwner ) {
                $html .= Html::element( 'span', [ 'class' => 'kanban-owner-badge' ], '我' );
            } else {
                // 获取所有者用户名
                $owner = $this->getUserById( $board->board_owner_id );
                $html .= Html::element( 'span', [], $owner ? $owner->getName() : '用户#' . $board->board_owner_id );
            }
            $html .= Html::closeElement( 'td' );
            
            // 权限
            $html .= Html::openElement( 'td' );
            $permissionText = $board->visibility === 'public' ? '公开' : '私有';
            $permissionClass = $board->visibility === 'public' ? 'kanban-permission-public' : 'kanban-permission-private';
            $html .= Html::element( 'span', [ 'class' => $permissionClass ], $permissionText );
            $html .= Html::closeElement( 'td' );
            
            // 状态
            $html .= Html::openElement( 'td' );
            $statusText = $this->getBoardStatusText( $board->board_status ?? 'active' );
            $statusClass = $this->getBoardStatusClass( $board->board_status ?? 'active' );
            $html .= Html::element( 'span', [ 'class' => $statusClass ], $statusText );
            $html .= Html::closeElement( 'td' );
            
            // 创建时间
            $html .= Html::openElement( 'td' );
            $html .= Html::element( 'span', [], $this->getLanguage()->userDate( $board->board_created_at, $user ) );
            $html .= Html::closeElement( 'td' );
            
            // 引用代码
            $html .= Html::openElement( 'td' );
            $kanbanCode = '<kanban name="' . htmlspecialchars( $board->kanban_name ) . '" />';
            $html .= Html::openElement( 'div', [ 'class' => 'kanban-code-container' ] );
            $html .= Html::element( 'code', [ 
                'class' => 'kanban-reference-code',
                'data-code' => $kanbanCode
            ], $kanbanCode );
            $html .= Html::element( 'button', [
                'class' => 'kanban-copy-btn mw-ui-button mw-ui-small',
                'onclick' => 'copyKanbanCode(this)',
                'title' => '点击复制'
            ], '复制' );
            $html .= Html::closeElement( 'div' );
            $html .= Html::closeElement( 'td' );
            
            // 操作
            $html .= Html::openElement( 'td' );
            
            // 查看看板按钮
            $boardUrl = $this->getPageTitle()->getLocalURL( [ 'board' => $board->board_id ] );
            $html .= Html::element( 'a', [
                'href' => $boardUrl,
                'class' => 'mw-ui-button mw-ui-progressive mw-ui-small'
            ], $this->msg( 'kanbanboard-view-board' )->text() );
            
            $html .= ' ';
            
            // 如果是所有者，显示编辑和状态管理按钮
            if ( $isOwner ) {
                $editUrl = $this->getPageTitle()->getLocalURL( [ 'edit' => $board->board_id ] );
                $html .= Html::element( 'a', [
                    'href' => $editUrl,
                    'class' => 'mw-ui-button mw-ui-small'
                ], '编辑' );
                
                $html .= ' ';
                
                // 状态管理按钮
                $currentStatus = $board->board_status ?? 'active';
                $html .= $this->renderStatusButtons( $board->board_id, $currentStatus );
            }
            
            $html .= Html::closeElement( 'td' );
            $html .= Html::closeElement( 'tr' );
        }
        
        $html .= Html::closeElement( 'tbody' );
        $html .= Html::closeElement( 'table' );
        
        return $html;
    }
    
    /**
     * 根据用户ID获取用户对象
     */
    private function getUserById( $userId ) {
        $userFactory = MediaWikiServices::getInstance()->getUserFactory();
        return $userFactory->newFromId( $userId );
    }
    
    /**
     * 渲染看板项目
     */
    private function renderBoardItem( $board, $isOwner ) {
        $html = Html::openElement( 'div', [ 'class' => 'kanban-board-item' ] );
        
        $html .= Html::element( 'h4', [], $board->board_name );
        
        if ( $board->board_description ) {
            $html .= Html::element( 'p', [ 'class' => 'kanban-board-description' ], $board->board_description );
        }
        
        $html .= Html::element( 'p', [ 'class' => 'kanban-board-meta' ], 
            $this->msg( 'kanbanboard-created' )->params( 
                $this->getLanguage()->userDate( $board->board_created_at, $this->getUser() )
            )->text() 
        );
        
        if ( $isOwner ) {
            $html .= Html::element( 'span', [ 'class' => 'kanban-board-owner' ], 
                $this->msg( 'kanbanboard-owner' )->text() 
            );
        }
        
        // 看板链接
        $boardUrl = $this->getPageTitle()->getLocalURL( [ 'board' => $board->board_id ] );
        $html .= Html::element( 'a', [
            'href' => $boardUrl,
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg( 'kanbanboard-view-board' )->text() );
        
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    /**
     * 显示具体看板
     */
    private function showBoard( $boardId ) {
        $user = $this->getUser();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        // 获取看板信息
        $board = $db->selectRow(
            'kanban_boards',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$board ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-board-not-found' );
            return;
        }
        
        // 检查权限
        $hasAccess = false;
        if ( $board->board_owner_id == $user->getId() ) {
            $hasAccess = true;
        } elseif ( $board->visibility === 'public' ) {
            $hasAccess = true;
        } else {
            // 检查是否有权限记录
            $permission = $db->selectField(
                'kanban_permissions',
                'permission_id',
                [
                    'board_id' => $boardId,
                    'user_id' => $user->getId()
                ],
                __METHOD__
            );
            if ( $permission ) {
                $hasAccess = true;
            }
        }
        
        if ( !$hasAccess ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-permission-denied' );
            return;
        }
        
        // 获取看板状态
        $statuses = $db->select(
            'kanban_statuses',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__,
            [ 'ORDER BY' => 'status_order ASC' ]
        );
        
        // 获取任务
        $tasks = $db->select(
            [ 'kanban_tasks', 'kanban_statuses' ],
            'kanban_tasks.*',
            [
                'kanban_tasks.board_id' => $boardId,
                'kanban_tasks.status_id = kanban_statuses.status_id'
            ],
            __METHOD__,
            [ 'ORDER BY' => 'kanban_statuses.status_order ASC, kanban_tasks.task_order ASC' ]
        );
        
        // 构建看板HTML
        $html = Html::element( 'h1', [], $board->board_name );
        
        if ( $board->board_description ) {
            $html .= Html::element( 'p', [ 'class' => 'kanban-board-description' ], $board->board_description );
        }
        
        // 返回看板列表的链接
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle()->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], '← 返回看板列表' );
        
        $html .= Html::element( 'hr' );
        
        // 看板容器
        $html .= Html::openElement( 'div', [ 
            'id' => 'kanban-board-' . $boardId,
            'class' => 'kanban-board',
            'data-board-id' => $boardId
        ] );
        
        // 状态列
        foreach ( $statuses as $status ) {
            $html .= Html::openElement( 'div', [
                'class' => 'kanban-column',
                'data-status-id' => $status->status_id
            ] );
            
            $html .= Html::element( 'h3', [ 'class' => 'kanban-column-header' ], $status->status_name );
            
            $html .= Html::openElement( 'div', [ 'class' => 'kanban-column-content' ] );
            
            // 该状态的任务
            $tasks->rewind();
            foreach ( $tasks as $task ) {
                if ( $task->status_id == $status->status_id ) {
                    $html .= Html::openElement( 'div', [
                        'class' => 'kanban-card',
                        'data-task-id' => $task->task_id
                    ] );
                    
                    $html .= Html::element( 'h4', [ 'class' => 'kanban-card-title' ], $task->task_title );
                    
                    if ( $task->task_description ) {
                        $html .= Html::element( 'p', [ 'class' => 'kanban-card-description' ], $task->task_description );
                    }
                    
                    $html .= Html::closeElement( 'div' );
                }
            }
            
            $html .= Html::closeElement( 'div' );
            $html .= Html::closeElement( 'div' );
        }
        
        $html .= Html::closeElement( 'div' );
        
        $this->getOutput()->addHTML( $html );
    }
    
    /**
     * 显示编辑看板表单
     */
    private function showEditBoardForm( $boardId ) {
        $user = $this->getUser();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        // 获取看板信息
        $board = $db->selectRow(
            'kanban_boards',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$board ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-board-not-found' );
            return;
        }
        
        // 检查权限（只有所有者可以编辑）
        if ( $board->board_owner_id != $user->getId() ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-permission-denied' );
            return;
        }
        
        $html = Html::element( 'h2', [], '编辑看板：' . $board->board_name );
        
        // 返回看板列表的链接
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle()->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], '← 返回看板列表' );
        
        $html .= Html::element( 'hr' );
        
        $html .= Html::openElement( 'form', [
            'method' => 'post',
            'action' => $this->getPageTitle()->getLocalURL()
        ] );
        
        // 隐藏字段
        $html .= Html::element( 'input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'edit'
        ] );
        $html .= Html::element( 'input', [
            'type' => 'hidden',
            'name' => 'board_id',
            'value' => $boardId
        ] );
        
        // 看板名称
        $html .= Html::element( 'label', [ 'for' => 'board-name' ], 
            $this->msg( 'kanbanboard-board-name' )->text() 
        );
        $html .= Html::element( 'input', [
            'type' => 'text',
            'id' => 'board-name',
            'name' => 'board_name',
            'value' => $board->board_name,
            'required' => true
        ] );
        
        // 看板描述
        $html .= Html::element( 'label', [ 'for' => 'board-description' ], 
            $this->msg( 'kanbanboard-board-description' )->text() 
        );
        $html .= Html::element( 'textarea', [
            'id' => 'board-description',
            'name' => 'board_description',
            'rows' => 3
        ], $board->board_description );
        
        // 可见性
        $html .= Html::element( 'label', [ 'for' => 'board-visibility' ], '可见性' );
        $html .= Html::openElement( 'select', [
            'id' => 'board-visibility',
            'name' => 'board_visibility'
        ] );
        $html .= Html::element( 'option', [ 
            'value' => 'private',
            'selected' => $board->visibility === 'private' ? 'selected' : null
        ], '私有' );
        $html .= Html::element( 'option', [ 
            'value' => 'internal',
            'selected' => $board->visibility === 'internal' ? 'selected' : null
        ], '内部' );
        $html .= Html::element( 'option', [ 
            'value' => 'public',
            'selected' => $board->visibility === 'public' ? 'selected' : null
        ], '公开' );
        $html .= Html::closeElement( 'select' );
        
        // 最大列数
        $html .= Html::element( 'label', [ 'for' => 'board-max-columns' ], '最大列数' );
        $html .= Html::element( 'input', [
            'type' => 'number',
            'id' => 'board-max-columns',
            'name' => 'board_max_columns',
            'value' => $board->board_max_columns,
            'min' => 1,
            'max' => 50
        ] );
        
        // 提交按钮
        $html .= Html::element( 'button', [
            'type' => 'submit',
            'class' => 'mw-ui-button mw-ui-progressive'
        ], '保存更改' );
        
        $html .= Html::closeElement( 'form' );
        
        $this->getOutput()->addHTML( $html );
    }
    
    /**
     * 处理编辑看板表单提交
     */
    private function handleEditBoard() {
        $user = $this->getUser();
        $request = $this->getRequest();
        
        $boardId = $request->getInt( 'board_id' );
        $boardName = trim( $request->getText( 'board_name' ) );
        $boardDescription = trim( $request->getText( 'board_description' ) );
        $boardVisibility = $request->getText( 'board_visibility' );
        $boardMaxColumns = $request->getInt( 'board_max_columns' );
        
        if ( empty( $boardName ) ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-board-name-required' );
            $this->showEditBoardForm( $boardId );
            return;
        }
        
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        try {
            // 检查看板是否存在且用户有权限编辑
            $board = $db->selectRow(
                'kanban_boards',
                '*',
                [ 'board_id' => $boardId ],
                __METHOD__
            );
            
            if ( !$board ) {
                $this->getOutput()->addWikiMsg( 'kanbanboard-board-not-found' );
                return;
            }
            
            if ( $board->board_owner_id != $user->getId() ) {
                $this->getOutput()->addWikiMsg( 'kanbanboard-permission-denied' );
                return;
            }
            
            // 检查名称是否与其他看板重复
            $existing = $db->selectField(
                'kanban_boards',
                'board_id',
                [ 
                    'kanban_name' => $boardName,
                    'board_id != ' . $boardId
                ],
                __METHOD__
            );
            
            if ( $existing ) {
                $this->getOutput()->addWikiMsg( 'kanbanboard-board-name-exists' );
                $this->showEditBoardForm( $boardId );
                return;
            }
            
            // 更新看板信息
            $db->update(
                'kanban_boards',
                [
                    'board_name' => $boardName,
                    'board_description' => $boardDescription,
                    'visibility' => $boardVisibility,
                    'board_max_columns' => $boardMaxColumns,
                    'board_updated_at' => $db->timestamp()
                ],
                [ 'board_id' => $boardId ],
                __METHOD__
            );
            
            // 重定向到看板列表页面
            $this->getOutput()->redirect( $this->getPageTitle()->getLocalURL() );
            
        } catch ( Exception $e ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-create-error', $e->getMessage() );
            $this->showEditBoardForm( $boardId );
        }
    }
    
    /**
     * 处理创建看板表单提交
     */
    private function handleCreateBoard() {
        $user = $this->getUser();
        $request = $this->getRequest();
        
        $boardName = trim( $request->getText( 'board_name' ) );
        $boardDescription = trim( $request->getText( 'board_description' ) );
        $boardPermissions = $request->getText( 'board_permissions' );
        
        if ( empty( $boardName ) ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-board-name-required' );
            $this->showCreateBoardForm();
            return;
        }
        
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        try {
            // 生成看板 slug
            $slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $boardName ) );
            if ( empty( $slug ) ) {
                $slug = 'board-' . time();
            }
            
            // 检查名称是否已存在
            $existing = $db->selectField(
                'kanban_boards',
                'board_id',
                [ 'kanban_name' => $boardName ],
                __METHOD__
            );
            
            if ( $existing ) {
                $this->getOutput()->addWikiMsg( 'kanbanboard-board-name-exists' );
                $this->showCreateBoardForm();
                return;
            }
            
            // 插入新看板
            $db->insert(
                'kanban_boards',
                [
                    'kanban_name' => $boardName,
                    'kanban_slug' => $slug,
                    'board_name' => $boardName,
                    'board_description' => $boardDescription,
                    'board_owner_id' => $user->getId(),
                    'visibility' => $boardPermissions === 'public' ? 'public' : 'private',
                    'board_permissions' => $boardPermissions,
                    'board_created_at' => $db->timestamp(),
                    'board_updated_at' => $db->timestamp(),
                    'board_max_columns' => 10
                ],
                __METHOD__
            );
            
            $boardId = $db->insertId();
            
            // 创建默认状态
            $defaultStatuses = [
                [ 'name' => '待办', 'order' => 1, 'color' => '#ff6b6b' ],
                [ 'name' => '进行中', 'order' => 2, 'color' => '#4ecdc4' ],
                [ 'name' => '已完成', 'order' => 3, 'color' => '#45b7d1' ]
            ];
            
            foreach ( $defaultStatuses as $status ) {
                // 生成唯一的 status_key
                $statusKey = strtolower( preg_replace( '/[^a-z0-9_]+/i', '_', $status['name'] ) );
                if ( empty( $statusKey ) || $statusKey === '_' ) {
                    $statusKey = 'status_' . $status['order'];
                }
                
                // 确保键值唯一
                $counter = 1;
                $originalKey = $statusKey;
                while ( true ) {
                    $existing = $db->selectField(
                        'kanban_statuses',
                        'status_id',
                        [ 
                            'board_id' => $boardId,
                            'status_key' => $statusKey 
                        ],
                        __METHOD__
                    );
                    
                    if ( !$existing ) {
                        break;
                    }
                    
                    $statusKey = $originalKey . '_' . $counter;
                    $counter++;
                }
                
                $db->insert(
                    'kanban_statuses',
                    [
                        'board_id' => $boardId,
                        'status_key' => $statusKey,
                        'status_name' => $status['name'],
                        'status_order' => $status['order'],
                        'color' => $status['color'],
                        'wip_limit' => 0
                    ],
                    __METHOD__
                );
            }
            
            // 重定向到看板列表页面
            $this->getOutput()->redirect( $this->getPageTitle()->getLocalURL() );
            
        } catch ( Exception $e ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-create-error', $e->getMessage() );
            $this->showCreateBoardForm();
        }
    }
    
    /**
     * 显示创建看板表单
     */
    private function showCreateBoardForm() {
        $html = Html::element( 'h2', [], $this->msg( 'kanbanboard-create-board' )->text() );
        
        $html .= Html::openElement( 'form', [
            'method' => 'post',
            'action' => $this->getPageTitle()->getLocalURL()
        ] );
        
        $html .= Html::element( 'label', [ 'for' => 'board-name' ], 
            $this->msg( 'kanbanboard-board-name' )->text() 
        );
        $html .= Html::element( 'input', [
            'type' => 'text',
            'id' => 'board-name',
            'name' => 'board_name',
            'required' => true
        ] );
        
        $html .= Html::element( 'label', [ 'for' => 'board-description' ], 
            $this->msg( 'kanbanboard-board-description' )->text() 
        );
        $html .= Html::element( 'textarea', [
            'id' => 'board-description',
            'name' => 'board_description',
            'rows' => 3
        ] );
        
        $html .= Html::element( 'label', [ 'for' => 'board-permissions' ], 
            $this->msg( 'kanbanboard-permissions' )->text() 
        );
        $html .= Html::openElement( 'select', [
            'id' => 'board-permissions',
            'name' => 'board_permissions'
        ] );
        $html .= Html::element( 'option', [ 'value' => 'private' ], 
            $this->msg( 'kanbanboard-permissions-private' )->text() 
        );
        $html .= Html::element( 'option', [ 'value' => 'public' ], 
            $this->msg( 'kanbanboard-permissions-public' )->text() 
        );
        $html .= Html::closeElement( 'select' );
        
        $html .= Html::element( 'button', [
            'type' => 'submit',
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg( 'kanbanboard-create-board' )->text() );
        
        $html .= Html::closeElement( 'form' );
        
        $this->getOutput()->addHTML( $html );
    }
    
    protected function getGroupName() {
        return 'other';
    }
    
    /**
     * 获取看板状态文本
     */
    private function getBoardStatusText( $status ) {
        switch ( $status ) {
            case 'active':
                return '活跃';
            case 'hidden':
                return '隐藏';
            case 'archived':
                return '存档';
            case 'deleted':
                return '已删除';
            default:
                return '未知';
        }
    }
    
    /**
     * 获取看板状态CSS类
     */
    private function getBoardStatusClass( $status ) {
        switch ( $status ) {
            case 'active':
                return 'kanban-status-active';
            case 'hidden':
                return 'kanban-status-hidden';
            case 'archived':
                return 'kanban-status-archived';
            case 'deleted':
                return 'kanban-status-deleted';
            default:
                return 'kanban-status-unknown';
        }
    }
    
    /**
     * 渲染状态管理按钮
     */
    private function renderStatusButtons( $boardId, $currentStatus ) {
        $html = '';
        
        // 根据当前状态显示不同的按钮
        switch ( $currentStatus ) {
            case 'active':
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "hidden")',
                    'title' => '隐藏看板'
                ], '隐藏' );
                $html .= ' ';
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "archived")',
                    'title' => '存档看板'
                ], '存档' );
                $html .= ' ';
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small mw-ui-quiet',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "deleted")',
                    'title' => '删除看板'
                ], '删除' );
                break;
                
            case 'hidden':
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small mw-ui-progressive',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "active")',
                    'title' => '显示看板'
                ], '显示' );
                $html .= ' ';
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "archived")',
                    'title' => '存档看板'
                ], '存档' );
                break;
                
            case 'archived':
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small mw-ui-progressive',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "active")',
                    'title' => '恢复看板'
                ], '恢复' );
                break;
                
            case 'deleted':
                $html .= Html::element( 'button', [
                    'class' => 'mw-ui-button mw-ui-small mw-ui-progressive',
                    'onclick' => 'changeBoardStatus(' . $boardId . ', "active")',
                    'title' => '恢复看板'
                ], '恢复' );
                break;
        }
        
        return $html;
    }
}
