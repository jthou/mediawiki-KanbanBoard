<?php
/**
 * Special page for Kanban Task Search
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use SpecialPage;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class SpecialKanbanSearch extends SpecialPage {
    
    public function __construct() {
        parent::__construct( 'KanbanSearch' );
    }
    
    public function execute( $subPage ) {
        $this->setHeaders();
        $this->getOutput()->addModules( 'ext.kanbanboard' );
        
        $user = $this->getUser();
        $request = $this->getRequest();
        
        if ( !$user->isRegistered() ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-must-be-logged-in' );
            return;
        }
        
        $searchTerm = $request->getText( 'search' );
        $boardId = $request->getInt( 'board' );
        $statusId = $request->getInt( 'status' );
        $priority = $request->getText( 'priority' );
        
        $html = Html::element( 'h1', [], '看板任务搜索' );
        
        // 搜索表单
        $html .= $this->renderSearchForm( $searchTerm, $boardId, $statusId, $priority );
        
        if ( $searchTerm ) {
            $html .= $this->renderSearchResults( $searchTerm, $boardId, $statusId, $priority, $user );
        }
        
        $this->getOutput()->addHTML( $html );
    }
    
    /**
     * 渲染搜索表单
     */
    private function renderSearchForm( $searchTerm, $boardId, $statusId, $priority ) {
        $html = Html::openElement( 'form', [
            'method' => 'get',
            'action' => $this->getPageTitle()->getLocalURL()
        ] );
        
        // 搜索词
        $html .= Html::element( 'label', [ 'for' => 'search' ], '搜索词' );
        $html .= Html::element( 'input', [
            'type' => 'text',
            'id' => 'search',
            'name' => 'search',
            'value' => $searchTerm,
            'placeholder' => '输入任务标题或描述'
        ] );
        
        // 看板选择
        $html .= Html::element( 'label', [ 'for' => 'board' ], '看板' );
        $html .= Html::openElement( 'select', [
            'id' => 'board',
            'name' => 'board'
        ] );
        $html .= Html::element( 'option', [ 'value' => '' ], '所有看板' );
        
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $boards = $db->select(
            'kanban_boards',
            [ 'board_id', 'board_name' ],
            [],
            __METHOD__,
            [ 'ORDER BY' => 'board_name ASC' ]
        );
        
        foreach ( $boards as $board ) {
            $html .= Html::element( 'option', [
                'value' => $board->board_id,
                'selected' => $boardId == $board->board_id ? 'selected' : null
            ], $board->board_name );
        }
        
        $html .= Html::closeElement( 'select' );
        
        // 优先级选择
        $html .= Html::element( 'label', [ 'for' => 'priority' ], '优先级' );
        $html .= Html::openElement( 'select', [
            'id' => 'priority',
            'name' => 'priority'
        ] );
        $html .= Html::element( 'option', [ 'value' => '' ], '所有优先级' );
        $html .= Html::element( 'option', [
            'value' => 'urgent',
            'selected' => $priority === 'urgent' ? 'selected' : null
        ], '紧急' );
        $html .= Html::element( 'option', [
            'value' => 'high',
            'selected' => $priority === 'high' ? 'selected' : null
        ], '高' );
        $html .= Html::element( 'option', [
            'value' => 'medium',
            'selected' => $priority === 'medium' ? 'selected' : null
        ], '中' );
        $html .= Html::element( 'option', [
            'value' => 'low',
            'selected' => $priority === 'low' ? 'selected' : null
        ], '低' );
        $html .= Html::closeElement( 'select' );
        
        // 提交按钮
        $html .= Html::element( 'button', [
            'type' => 'submit',
            'class' => 'mw-ui-button mw-ui-progressive'
        ], '搜索' );
        
        $html .= Html::closeElement( 'form' );
        
        return $html;
    }
    
    /**
     * 渲染搜索结果
     */
    private function renderSearchResults( $searchTerm, $boardId, $statusId, $priority, $user ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        // 构建搜索条件
        $conditions = [
            'kanban_tasks.deleted_at IS NULL'
        ];
        
        if ( $searchTerm ) {
            $conditions[] = $db->makeList( [
                'kanban_tasks.title LIKE ' . $db->addQuotes( '%' . $searchTerm . '%' ),
                'kanban_tasks.description LIKE ' . $db->addQuotes( '%' . $searchTerm . '%' )
            ], LIST_OR );
        }
        
        if ( $boardId ) {
            $conditions[] = 'kanban_tasks.board_id = ' . $boardId;
        }
        
        if ( $statusId ) {
            $conditions[] = 'kanban_tasks.status_id = ' . $statusId;
        }
        
        if ( $priority ) {
            $conditions[] = 'kanban_tasks.priority = ' . $db->addQuotes( $priority );
        }
        
        // 权限检查
        $permissionConditions = [
            'kanban_boards.visibility = ' . $db->addQuotes( 'public' ),
            'kanban_boards.board_owner_id = ' . $user->getId()
        ];
        
        $userPermissions = $db->selectFieldValues(
            'kanban_permissions',
            'board_id',
            [ 'user_id' => $user->getId() ],
            __METHOD__
        );
        
        if ( !empty( $userPermissions ) ) {
            $permissionConditions[] = 'kanban_boards.board_id IN (' . $db->makeList( $userPermissions ) . ')';
        }
        
        $conditions[] = $db->makeList( $permissionConditions, LIST_OR );
        
        // 执行搜索
        $results = $db->select(
            [
                'kanban_tasks',
                'kanban_boards',
                'kanban_statuses'
            ],
            [
                'kanban_tasks.task_id',
                'kanban_tasks.title',
                'kanban_tasks.description',
                'kanban_tasks.priority',
                'kanban_tasks.due_date',
                'kanban_tasks.created_at',
                'kanban_boards.board_name',
                'kanban_boards.board_id',
                'kanban_statuses.status_name'
            ],
            $conditions,
            __METHOD__,
            [
                'ORDER BY' => 'kanban_tasks.created_at DESC',
                'LIMIT' => 50
            ],
            [
                'kanban_boards' => [ 'INNER JOIN', 'kanban_tasks.board_id = kanban_boards.board_id' ],
                'kanban_statuses' => [ 'INNER JOIN', 'kanban_tasks.status_id = kanban_statuses.status_id' ]
            ]
        );
        
        $html = Html::element( 'h2', [], '搜索结果' );
        
        if ( $results->numRows() == 0 ) {
            $html .= Html::element( 'p', [ 'class' => 'kanban-no-results' ], '没有找到匹配的任务' );
            return $html;
        }
        
        $html .= Html::openElement( 'div', [ 'class' => 'kanban-search-results' ] );
        
        foreach ( $results as $task ) {
            $html .= $this->renderTaskResult( $task, $searchTerm );
        }
        
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    /**
     * 渲染单个任务结果
     */
    private function renderTaskResult( $task, $searchTerm ) {
        $html = Html::openElement( 'div', [ 'class' => 'kanban-task-result' ] );
        
        // 任务标题
        $title = $task->title;
        if ( $searchTerm ) {
            $title = str_ireplace( $searchTerm, '<strong>' . $searchTerm . '</strong>', $title );
        }
        
        $html .= Html::element( 'h3', [ 'class' => 'kanban-task-title' ], $title );
        
        // 任务描述
        if ( $task->description ) {
            $description = $task->description;
            if ( $searchTerm ) {
                $description = str_ireplace( $searchTerm, '<strong>' . $searchTerm . '</strong>', $description );
            }
            $html .= Html::rawElement( 'p', [ 'class' => 'kanban-task-description' ], $description );
        }
        
        // 任务元数据
        $html .= Html::openElement( 'div', [ 'class' => 'kanban-task-meta' ] );
        
        $html .= Html::element( 'span', [ 'class' => 'kanban-task-board' ], '看板: ' . $task->board_name );
        $html .= Html::element( 'span', [ 'class' => 'kanban-task-status' ], '状态: ' . $task->status_name );
        $html .= Html::element( 'span', [ 
            'class' => 'kanban-task-priority kanban-task-priority-' . $task->priority 
        ], '优先级: ' . $this->getPriorityText( $task->priority ) );
        
        if ( $task->due_date ) {
            $html .= Html::element( 'span', [ 'class' => 'kanban-task-due' ], '截止: ' . $task->due_date );
        }
        
        $html .= Html::closeElement( 'div' );
        
        // 任务链接
        $boardUrl = Title::makeTitle( NS_SPECIAL, 'KanbanBoard' )->getLocalURL( [ 'board' => $task->board_id ] );
        $html .= Html::element( 'a', [
            'href' => $boardUrl . '#task-' . $task->task_id,
            'class' => 'mw-ui-button mw-ui-progressive mw-ui-small'
        ], '查看看板' );
        
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    /**
     * 获取优先级文本
     */
    private function getPriorityText( $priority ) {
        $priorityMap = [
            'urgent' => '紧急',
            'high' => '高',
            'medium' => '中',
            'low' => '低'
        ];
        
        return $priorityMap[$priority] ?? $priority;
    }
    
    protected function getGroupName() {
        return 'other';
    }
}
