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
     * 显示看板列表
     */
    private function showBoardList() {
        $user = $this->getUser();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getMaintenanceConnectionRef( DB_PRIMARY );
        
        // 获取所有看板
        $allBoards = $db->select(
            'kanban_boards',
            '*',
            [],
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        // 获取用户拥有的看板
        $ownedBoards = $db->select(
            'kanban_boards',
            '*',
            [ 'board_owner_id' => $user->getId() ],
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        $html = Html::element( 'h2', [], $this->msg( 'kanbanboard-board-list' )->text() );
        
        // 创建新看板按钮
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle( 'create' )->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg( 'kanbanboard-create-board' )->text() );
        
        $html .= Html::element( 'hr' );
        
        // 显示所有看板表格
        if ( $allBoards->numRows() > 0 ) {
            $html .= Html::element( 'h3', [], '所有看板' );
            $html .= $this->renderBoardTable( $allBoards, $user );
        }
        
        // 显示拥有的看板表格
        if ( $ownedBoards->numRows() > 0 ) {
            $html .= Html::element( 'h3', [], $this->msg( 'kanbanboard-owned-boards' )->text() );
            $html .= $this->renderBoardTable( $ownedBoards, $user, true );
        }
        
        $this->getOutput()->addHTML( $html );
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
            
            // 看板名称
            $html .= Html::openElement( 'td' );
            $html .= Html::element( 'strong', [], $board->board_name );
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
            
            // 如果是所有者，显示编辑按钮
            if ( $isOwner ) {
                $editUrl = $this->getPageTitle()->getLocalURL( [ 'edit' => $board->board_id ] );
                $html .= Html::element( 'a', [
                    'href' => $editUrl,
                    'class' => 'mw-ui-button mw-ui-small'
                ], '编辑' );
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
}
