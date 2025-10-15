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
        
        // 获取用户拥有的看板
        $ownedBoards = $db->select(
            'kanban_boards',
            '*',
            [ 'board_owner_id' => $user->getId() ],
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        // 获取用户有权限访问的看板
        $accessibleBoards = $db->select(
            [ 'kanban_boards', 'kanban_permissions' ],
            'kanban_boards.*',
            [
                'kanban_permissions.user_id' => $user->getId(),
                'kanban_boards.board_id = kanban_permissions.board_id'
            ],
            __METHOD__,
            [ 'ORDER BY' => 'kanban_boards.board_created_at DESC' ]
        );
        
        $html = Html::element( 'h2', [], $this->msg( 'kanbanboard-board-list' )->text() );
        
        // 创建新看板按钮
        $html .= Html::element( 'a', [
            'href' => $this->getPageTitle( 'create' )->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg( 'kanbanboard-create-board' )->text() );
        
        $html .= Html::element( 'hr' );
        
        // 显示拥有的看板
        if ( $ownedBoards->numRows() > 0 ) {
            $html .= Html::element( 'h3', [], $this->msg( 'kanbanboard-owned-boards' )->text() );
            $html .= Html::openElement( 'div', [ 'class' => 'kanban-board-list' ] );
            
            foreach ( $ownedBoards as $board ) {
                $html .= $this->renderBoardItem( $board, true );
            }
            
            $html .= Html::closeElement( 'div' );
        }
        
        // 显示可访问的看板
        if ( $accessibleBoards->numRows() > 0 ) {
            $html .= Html::element( 'h3', [], $this->msg( 'kanbanboard-accessible-boards' )->text() );
            $html .= Html::openElement( 'div', [ 'class' => 'kanban-board-list' ] );
            
            foreach ( $accessibleBoards as $board ) {
                $html .= $this->renderBoardItem( $board, false );
            }
            
            $html .= Html::closeElement( 'div' );
        }
        
        $this->getOutput()->addHTML( $html );
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
