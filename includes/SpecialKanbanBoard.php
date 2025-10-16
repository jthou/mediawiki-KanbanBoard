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
use OOUI; // OOUI PHP widgets

class SpecialKanbanBoard extends SpecialPage {
    
    public function __construct() {
        parent::__construct( 'KanbanBoard' );
    }
    
    public function execute( $subPage ) {
        $this->setHeaders();
        $out = $this->getOutput();
        $out->addModules( 'ext.kanbanboard' );
        $out->enableOOUI();
        
        $user = $this->getUser();
        
        if ( !$user->isRegistered() ) {
            $this->getOutput()->addWikiMsg( 'kanbanboard-must-be-logged-in' );
            return;
        }
        
        $request = $this->getRequest();
        $parts = array_values( array_filter( explode( '/', (string)$subPage ) ) );
        if ( $parts && $parts[0] === 'create' ) {
            $this->handleCreateBoard();
            return;
        }
        if ( $parts && $parts[0] === 'edit' && isset( $parts[1] ) ) {
            $this->handleEditBoard( (int)$parts[1] );
            return;
        }
        if ( $parts && $parts[0] === 'delete' && isset( $parts[1] ) ) {
            $this->handleDeleteBoard( (int)$parts[1] );
            return;
        }
        $this->showBoardList();
    }
    
    /**
     * 显示看板列表
     */
    private function showBoardList() {
        $user = $this->getUser();
        // 使用请求周期内的只读连接，避免 maintenance 连接在 Web 请求中被关闭
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        // 获取用户拥有的看板（若存在 deleted_at 列则过滤未软删除）
        $ownedConds = [ 'board_owner_id' => $user->getId() ];
        if ( method_exists( $db, 'fieldExists' ) && $db->fieldExists( 'kanban_boards', 'deleted_at', __METHOD__ ) ) {
            $ownedConds[] = 'deleted_at IS NULL';
        }
        $ownedBoards = $db->select(
            'kanban_boards',
            '*',
            $ownedConds,
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        // 获取用户有权限访问的看板（若存在 deleted_at 列则过滤未软删除）
        $accessConds = [
            'kanban_permissions.user_id' => $user->getId(),
            'kanban_boards.board_id = kanban_permissions.board_id',
        ];
        if ( method_exists( $db, 'fieldExists' ) && $db->fieldExists( 'kanban_boards', 'deleted_at', __METHOD__ ) ) {
            $accessConds[] = 'kanban_boards.deleted_at IS NULL';
        }
        $accessibleBoards = $db->select(
            [ 'kanban_boards', 'kanban_permissions' ],
            'kanban_boards.*',
            $accessConds,
            __METHOD__,
            [ 'ORDER BY' => 'kanban_boards.board_created_at DESC' ]
        );
        
        $html = '';
        
        // 创建新看板按钮
        $createBtn = new OOUI\ButtonWidget( [
            'label' => $this->msg( 'kanbanboard-create-board' )->text(),
            'flags' => [ 'progressive', 'primary' ],
            'href' => $this->getPageTitle( 'create' )->getLocalURL()
        ] );
        $html .= $createBtn->toString();
        
        $html .= Html::element( 'hr' );
        
        // 汇总为一个列表表格（创建看板下面显示）
        $boards = [];
        foreach ( $ownedBoards as $b ) { $boards[(int)$b->board_id] = [ $b, true ]; }
        foreach ( $accessibleBoards as $b ) { if ( !isset( $boards[(int)$b->board_id] ) ) { $boards[(int)$b->board_id] = [ $b, false ]; } }

        $html .= Html::element( 'h3', [], '已有看板' );
        $html .= Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] );
        $html .= Html::rawElement( 'thead', [], Html::rawElement( 'tr', [],
            Html::element( 'th', [], '标识' ) .
            Html::element( 'th', [], '名称' ) .
            Html::element( 'th', [], '可见性' ) .
            Html::element( 'th', [], '创建时间' ) .
            Html::element( 'th', [], '嵌入代码' ) .
            Html::element( 'th', [], '操作' )
        ) );
        $html .= Html::openElement( 'tbody' );
        foreach ( $boards as $tuple ) {
            /** @var stdClass $board */
            $board = $tuple[0];
            $isOwner = (bool)$tuple[1];
            $ops = '';
            // 查看按钮 - 跳转到显示看板的 wiki 页面
            $viewPageTitle = \Title::newFromText( '看板:' . $board->board_name );
            $viewBtn = new OOUI\ButtonWidget( [ 'label' => '查看', 'href' => $viewPageTitle->getLocalURL(), 'flags' => [ 'progressive' ] ] );
            $ops .= $viewBtn->toString() . ' ';
            $editBtn = new OOUI\ButtonWidget( [ 'label' => '编辑', 'href' => $this->getPageTitle( 'edit/' . $board->board_id )->getLocalURL() ] );
            $ops .= $editBtn->toString() . ' ';
            if ( $isOwner ) {
                $delBtn = new OOUI\ButtonWidget( [ 'label' => '删除', 'href' => $this->getPageTitle( 'delete/' . $board->board_id )->getLocalURL(), 'flags' => [ 'destructive' ] ] );
                $ops .= $delBtn->toString();
            }
            $embedCode = '<kanban name="' . htmlspecialchars( (string)($board->kanban_name ?? $board->board_name) ) . '" />';
            $embedInput = Html::element( 'input', [
                'type' => 'text',
                'readonly' => true,
                'class' => 'kanban-embed-input',
                'value' => $embedCode,
                'onclick' => 'this.select();document.execCommand&&document.execCommand("copy");'
            ] );

            $html .= Html::rawElement( 'tr', [],
                Html::element( 'td', [], (string)$board->board_id ) .
                Html::element( 'td', [], (string)$board->board_name ) .
                Html::element( 'td', [], (string)($board->visibility ?? 'private') ) .
                Html::element( 'td', [], $this->getLanguage()->userDate( $board->board_created_at ?? '', $this->getUser() ) ) .
                Html::rawElement( 'td', [], $embedInput ) .
                Html::rawElement( 'td', [], $ops )
            );
        }
        $html .= Html::closeElement( 'tbody' );
        $html .= Html::closeElement( 'table' );
        
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
        
        // 引用标签复制
        $embed = '<kanban name="' . htmlspecialchars( $board->kanban_name ?? $board->board_name ) . '" />';
        $html .= Html::rawElement( 'pre', [ 'class' => 'kanban-embed' ], htmlspecialchars( $embed ) );

        // 看板链接
        $boardUrl = $this->getPageTitle()->getLocalURL( [ 'board' => $board->board_id ] );
        $viewBtn = new OOUI\ButtonWidget( [ 'label' => $this->msg( 'kanbanboard-view-board' )->text(), 'href' => $boardUrl, 'flags' => [ 'progressive' ] ] );
        $editUrl = $this->getPageTitle( 'edit/' . $board->board_id )->getLocalURL();
        $deleteUrl = $this->getPageTitle( 'delete/' . $board->board_id )->getLocalURL();
        $editBtn = new OOUI\ButtonWidget( [ 'label' => '编辑', 'href' => $editUrl ] );
        $deleteBtn = new OOUI\ButtonWidget( [ 'label' => '删除', 'href' => $deleteUrl, 'flags' => [ 'destructive' ] ] );
        $html .= $viewBtn->toString() . ' ' . $editBtn->toString() . ' ' . $deleteBtn->toString();
        
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    /**
     * 显示创建看板表单
     */
    private function showBoardForm( string $mode, array $values = [], int $boardId = 0 ) {
        $isEdit = ( $mode === 'edit' );
        $titleText = $isEdit ? '编辑看板' : $this->msg( 'kanbanboard-create-board' )->text();
        $action = $isEdit ? $this->getPageTitle( 'edit/' . $boardId )->getLocalURL() : $this->getPageTitle( 'create' )->getLocalURL();
        $token = $this->getUser()->getEditToken();

        $fieldset = new OOUI\FieldsetLayout( [ 'label' => $titleText ] );

        // 标识由系统递增分配（board_id），无需输入

        $boardName = new OOUI\TextInputWidget( [ 'name' => 'board_name', 'value' => $values['board_name'] ?? '', 'required' => true ] );
        $fieldset->addItems( [ new OOUI\FieldLayout( $boardName, [ 'label' => '看板名称', 'align' => 'top' ] ) ] );

        $desc = new OOUI\MultilineTextInputWidget( [ 'name' => 'board_description', 'value' => $values['board_description'] ?? '', 'rows' => 3 ] );
        $fieldset->addItems( [ new OOUI\FieldLayout( $desc, [ 'label' => '看板描述', 'align' => 'top' ] ) ] );

        $vis = $values['visibility'] ?? 'private';
        $visibility = new OOUI\DropdownInputWidget( [
            'name' => 'visibility',
            'value' => $vis,
            'options' => [
                [ 'data' => 'private', 'label' => '私密' ],
                [ 'data' => 'internal', 'label' => '内部' ],
                [ 'data' => 'public', 'label' => '公开' ],
            ]
        ] );
        $fieldset->addItems( [ new OOUI\FieldLayout( $visibility, [ 'label' => '可见性', 'align' => 'top' ] ) ] );

        $submit = new OOUI\ButtonInputWidget( [ 'type' => 'submit', 'label' => ( $isEdit ? '保存修改' : '创建看板' ), 'flags' => [ 'progressive', 'primary' ] ] );

        $form = Html::openElement( 'form', [ 'method' => 'post', 'action' => $action ] ) .
            Html::hidden( 'token', $token ) .
            $fieldset->toString() .
            $submit->toString() .
            Html::closeElement( 'form' );

        $this->getOutput()->addHTML( $form );
    }

    private function handleCreateBoard() {
        $req = $this->getRequest();
        if ( !$req->wasPosted() ) {
            $this->showBoardForm( 'create' );
            return;
        }
        if ( !$this->getUser()->matchEditToken( $req->getVal( 'token' ) ) ) {
            $this->getOutput()->addHTML( '<div class="error">Invalid token</div>' );
            $this->showBoardForm( 'create' );
            return;
        }
        $boardName = trim( (string)$req->getVal( 'board_name' ) );
        $desc = (string)$req->getVal( 'board_description' );
        $visibility = (string)$req->getVal( 'visibility', 'private' );
        if ( $boardName === '' ) {
            $this->getOutput()->addHTML( '<div class="error">Missing required fields</div>' );
            $this->showBoardForm( 'create', [ 'board_name' => $boardName, 'board_description' => $desc, 'visibility' => $visibility ] );
            return;
        }
        $slug = strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', $boardName ) );
        $userId = $this->getUser()->getId();
        $dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
        // 唯一性检查
        $exists = $dbw->selectField( 'kanban_boards', 'board_id', [ 'board_name' => $boardName, 'deleted_at IS NULL' ], __METHOD__ );
        if ( $exists ) {
            $this->getOutput()->addHTML( '<div class="error">看板名称已存在</div>' );
            $this->showBoardForm( 'create', [ 'board_name' => $boardName, 'board_description' => $desc, 'visibility' => $visibility ] );
            return;
        }
        // 插入看板
        $dbw->insert( 'kanban_boards', [
            'kanban_name' => $boardName,
            'kanban_slug' => $slug,
            'board_name' => $boardName,
            'board_description' => $desc,
            'board_owner_id' => $userId,
            'visibility' => $visibility,
            'created_by' => $userId,
            'updated_by' => $userId,
        ], __METHOD__ );
        $boardId = (int)$dbw->insertId();
        // 默认状态
        $defaults = [
            [ 'key' => 'todo', 'name' => '待办', 'order' => 1, 'color' => '#e74c3c' ],
            [ 'key' => 'doing', 'name' => '进行中', 'order' => 2, 'color' => '#f39c12' ],
            [ 'key' => 'done', 'name' => '已完成', 'order' => 3, 'color' => '#27ae60' ],
        ];
        foreach ( $defaults as $st ) {
            $dbw->insert( 'kanban_statuses', [
                'board_id' => $boardId,
                'status_key' => $st['key'],
                'status_name' => $st['name'],
                'status_order' => $st['order'],
                'color' => $st['color'],
                'wip_limit' => 0,
            ], __METHOD__ );
        }
        
        // 自动创建显示看板的 wiki 页面
        $this->createBoardWikiPage( $boardName, $desc );
        
        $this->getOutput()->addHTML( '<div class="success">已创建。引用：&lt;kanban name="' . htmlspecialchars( $boardName ) . '" /&gt;</div>' );
        $this->showBoardList();
    }

    private function handleEditBoard( int $boardId ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $conds = [ 'board_id' => $boardId ];
        if ( method_exists( $db, 'fieldExists' ) && $db->fieldExists( 'kanban_boards', 'deleted_at', __METHOD__ ) ) {
            $conds[] = 'deleted_at IS NULL';
        }
        $row = $db->selectRow( 'kanban_boards', '*', $conds, __METHOD__ );
        if ( !$row ) {
            $this->getOutput()->addHTML( '<div class="error">Board not found</div>' );
            $this->showBoardList();
            return;
        }
        $req = $this->getRequest();
        if ( !$req->wasPosted() ) {
            $this->showBoardForm( 'edit', [
                'kanban_name' => (string)$row->kanban_name,
                'board_name' => (string)$row->board_name,
                'board_description' => (string)$row->board_description,
                'visibility' => (string)($row->visibility ?? 'private')
            ], $boardId );
            return;
        }
        if ( !$this->getUser()->matchEditToken( $req->getVal( 'token' ) ) ) {
            $this->getOutput()->addHTML( '<div class="error">Invalid token</div>' );
            return;
        }
        $boardName = trim( (string)$req->getVal( 'board_name' ) );
        $desc = (string)$req->getVal( 'board_description' );
        $visibility = (string)$req->getVal( 'visibility', 'private' );
        if ( $boardName === '' ) {
            $this->getOutput()->addHTML( '<div class="error">看板名称不能为空</div>' );
            $this->showBoardForm( 'edit', [ 'board_name' => $boardName, 'board_description' => $desc, 'visibility' => $visibility ], $boardId );
            return;
        }
        // 唯一性检查（排除自身）
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $dup = $db->selectField( 'kanban_boards', 'board_id', [ 'board_name' => $boardName, 'board_id != ' . (int)$boardId ], __METHOD__ );
        if ( $dup ) {
            $this->getOutput()->addHTML( '<div class="error">看板名称已存在</div>' );
            $this->showBoardForm( 'edit', [ 'board_name' => $boardName, 'board_description' => $desc, 'visibility' => $visibility ], $boardId );
            return;
        }
        $slug = strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', $boardName ) );
        $dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
        $dbw->update( 'kanban_boards', [
            'kanban_name' => $boardName,
            'kanban_slug' => $slug,
            'board_name' => $boardName,
            'board_description' => $desc,
            'visibility' => $visibility,
            'updated_by' => $this->getUser()->getId(),
        ], [ 'board_id' => $boardId ], __METHOD__ );
        $this->getOutput()->addHTML( '<div class="success">已保存。</div>' );
        $this->showBoardList();
    }

    private function handleDeleteBoard( int $boardId ) {
        $req = $this->getRequest();
        if ( !$req->wasPosted() ) {
            $action = $this->getPageTitle( 'delete/' . $boardId )->getLocalURL();
            $token = $this->getUser()->getEditToken();
            $html = Html::openElement( 'form', [ 'method' => 'post', 'action' => $action ] );
            $html .= Html::hidden( 'token', $token );
            $html .= Html::element( 'p', [], '确认删除该看板？此操作为软删除，可后续恢复。' );
            $html .= Html::element( 'button', [ 'type' => 'submit', 'class' => 'mw-ui-button mw-ui-destructive' ], '确认删除' );
            $html .= Html::closeElement( 'form' );
            $this->getOutput()->addHTML( $html );
            return;
        }
        if ( !$this->getUser()->matchEditToken( $req->getVal( 'token' ) ) ) {
            $this->getOutput()->addHTML( '<div class="error">Invalid token</div>' );
            return;
        }
        $dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
        $dbw->update( 'kanban_boards', [ 'deleted_at' => $dbw->timestamp() ], [ 'board_id' => $boardId ], __METHOD__ );
        $this->getOutput()->addHTML( '<div class="success">Board deleted.</div>' );
        $this->showBoardList();
    }
    
    /**
     * 创建显示看板的 wiki 页面
     */
    private function createBoardWikiPage( $boardName, $description ) {
        $title = \Title::newFromText( '看板:' . $boardName );
        if ( !$title || $title->exists() ) {
            return; // 页面已存在，跳过创建
        }
        
        $user = $this->getUser();
        $wikiText = "{{#tag:kanban|name={$boardName}}}";
        
        if ( $description ) {
            $wikiText = "== {$boardName} ==\n\n{$description}\n\n" . $wikiText;
        } else {
            $wikiText = "== {$boardName} ==\n\n" . $wikiText;
        }
        
        // 创建页面
        $page = \WikiPage::factory( $title );
        $content = \ContentHandler::makeContent( $wikiText, $title );
        
        $page->doUserEditContent(
            $content,
            $user,
            '自动创建看板页面',
            \EDIT_NEW | \EDIT_SUPPRESS_RC
        );
    }
    
    protected function getGroupName() {
        return 'other';
    }
}
