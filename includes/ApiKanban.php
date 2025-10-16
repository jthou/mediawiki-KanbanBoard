<?php
/**
 * Enhanced Kanban Board API Module
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use ApiBase;
use ApiMain;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LBFactory;

class ApiKanban extends ApiBase {
    
    private LBFactory $dbLoadBalancerFactory;
    
    public function __construct( ApiMain $mainModule, $moduleName, LBFactory $dbLoadBalancerFactory ) {
        parent::__construct( $mainModule, $moduleName );
        $this->dbLoadBalancerFactory = $dbLoadBalancerFactory;
    }
    
    protected function getDB() {
        return $this->dbLoadBalancerFactory->getMainLB()->getConnectionRef( DB_PRIMARY );
    }
    
    public function execute() {
        $params = $this->extractRequestParams();
        $action = $params['kanban_action'];
        
        switch ( $action ) {
            case 'getboard':
                $this->getBoard( $params );
                break;
            case 'addcolumn':
                $this->addColumn( $params );
                break;
            case 'test':
                $this->test();
                break;
            default:
                $this->dieWithError( 'Invalid action', 'invalidaction' );
        }
    }
    
    /**
     * 测试方法
     */
    private function test() {
        $this->getResult()->addValue( null, 'test', 'API is working' );
        $this->getResult()->addValue( null, 'timestamp', time() );
    }
    
    /**
     * 获取看板数据
     */
    private function getBoard( $params ) {
        $boardId = (int)$params['board_id'];
        
        // 检查看板是否存在
        $board = $this->getBoardData( $boardId );
        if ( !$board ) {
            $this->dieWithError( 'Board not found', 'boardnotfound' );
        }
        
        // 检查查看权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'view' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 获取列和卡片数据
        $columns = $this->getBoardColumns( $boardId );
        $board['columns'] = $columns;
        
        $this->getResult()->addValue( null, 'board', $board );
    }
    
    /**
     * 添加列到看板
     */
    private function addColumn( $params ) {
        $boardId = (int)$params['board_id'];
        $name = trim( $params['name'] );
        $description = trim( $params['description'] ?? '' );
        $color = $params['color'] ?? '#3498db';
        $position = (int)($params['position'] ?? -1);
        $width = (int)($params['width'] ?? 300);
        $maxCards = (int)($params['max_cards'] ?? 0);
        $wipLimit = (int)($params['wip_limit'] ?? 0);
        
        // 验证输入
        $this->validateColumnInput( $name, $color, $width, $maxCards, $wipLimit );
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 检查看板是否存在
        $board = $this->getBoardData( $boardId );
        if ( !$board ) {
            $this->dieWithError( 'Board not found', 'boardnotfound' );
        }
        
        // 检查列数限制
        $this->checkColumnLimit( $boardId );
        
        // 计算插入位置
        $order = $this->calculateColumnOrder( $boardId, $position );
        
        // 生成状态key并插入新状态（对外仍返回列结构）
        $statusKey = strtolower( preg_replace( '/[^a-z0-9_]+/i', '_', $name ) );
        if ( $statusKey === '' ) {
            $statusKey = 'status_' . substr( md5( $name . microtime(true) ), 0, 8 );
        }

        $columnId = $this->insertColumn( [
            'board_id' => $boardId,
            'status_key' => $statusKey,
            'status_name' => $name,
            'status_order' => $order,
            'color' => $color,
            'wip_limit' => $wipLimit
        ] );
        
        // 返回新列信息
        $column = $this->getColumnData( $columnId );
        $this->getResult()->addValue( null, 'column', $column );
        $this->getResult()->addValue( null, 'result', 'success' );
    }
    
    /**
     * 获取看板数据
     */
    private function getBoardData( $boardId ) {
        $row = $this->getDB()->selectRow(
            'kanban_boards',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        return $row ? (array)$row : null;
    }
    
    /**
     * 获取看板的所有列
     */
    private function getBoardColumns( $boardId ) {
        $statuses = $this->getDB()->select(
            'kanban_statuses',
            '*',
            [ 'board_id' => $boardId ],
            __METHOD__,
            [ 'ORDER BY' => 'status_order ASC' ]
        );

        $columnsData = [];
        foreach ( $statuses as $status ) {
            $statusArr = (array)$status;

            // 加载该状态下的任务并按旧结构返回为 cards
            $tasks = $this->getDB()->select(
                'kanban_tasks',
                '*',
                [ 'status_id' => $status->status_id ],
                __METHOD__,
                [ 'ORDER BY' => 'task_order ASC' ]
            );

            $cardsData = [];
            foreach ( $tasks as $task ) {
                $t = (array)$task;
                // 映射为旧字段命名，保持现有前端兼容
                $cardsData[] = [
                    'card_id' => $t['task_id'],
                    'column_id' => $statusArr['status_id'],
                    'card_title' => $t['title'],
                    'card_description' => $t['description'],
                    'card_assignee_id' => null,
                    'card_creator_id' => $t['created_by'] ?? null,
                    'card_priority' => $t['priority'],
                    'card_color' => $t['color'],
                    'card_order' => $t['task_order'],
                    'card_due_date' => $t['due_date'],
                    'card_created_at' => $t['created_at'],
                    'card_updated_at' => $t['updated_at'],
                ];
            }

            // 将状态映射为旧的列结构
            $columnsData[] = [
                'column_id' => $statusArr['status_id'],
                'board_id' => $statusArr['board_id'],
                'column_name' => $statusArr['status_name'],
                'column_description' => null,
                'column_order' => $statusArr['status_order'],
                'column_width' => 300,
                'column_max_cards' => $statusArr['wip_limit'],
                'column_is_collapsed' => 0,
                'column_wip_limit' => $statusArr['wip_limit'],
                'column_creator_id' => null,
                'column_color' => $statusArr['color'],
                'column_created_at' => $statusArr['created_at'],
                'cards' => $cardsData,
            ];
        }

        return $columnsData;
    }
    
    /**
     * 获取列数据
     */
    private function getColumnData( $columnId ) {
        $row = $this->getDB()->selectRow(
            'kanban_statuses',
            '*',
            [ 'status_id' => $columnId ],
            __METHOD__
        );

        if ( !$row ) {
            return null;
        }
        $r = (array)$row;
        return [
            'column_id' => $r['status_id'],
            'board_id' => $r['board_id'],
            'column_name' => $r['status_name'],
            'column_description' => null,
            'column_order' => $r['status_order'],
            'column_width' => 300,
            'column_max_cards' => $r['wip_limit'],
            'column_is_collapsed' => 0,
            'column_wip_limit' => $r['wip_limit'],
            'column_creator_id' => null,
            'column_color' => $r['color'],
            'column_created_at' => $r['created_at'],
        ];
    }
    
    /**
     * 检查看板权限
     */
    private function checkBoardPermission( $userId, $boardId, $permission ) {
        // 检查是否是看板所有者
        $board = $this->getBoardData( $boardId );
        if ( !$board ) {
            return false;
        }
        
        if ( $board['board_owner_id'] == $userId ) {
            return true; // 所有者拥有所有权限
        }
        
        // 检查公开看板的查看权限
        if ( $permission === 'view' && $board['board_permissions'] === 'public' ) {
            return true;
        }
        
        // 检查用户权限
        $userPermission = $this->getDB()->selectField(
            'kanban_permissions',
            'permission_type',
            [
                'board_id' => $boardId,
                'user_id' => $userId
            ],
            __METHOD__
        );
        
        switch ( $permission ) {
            case 'view':
                return in_array( $userPermission, [ 'admin', 'edit', 'view' ] );
            case 'edit':
                return in_array( $userPermission, [ 'admin', 'edit' ] );
            case 'admin':
                return $userPermission === 'admin';
            default:
                return false;
        }
    }
    
    /**
     * 验证列输入数据
     */
    private function validateColumnInput( $name, $color, $width, $maxCards, $wipLimit ) {
        if ( empty( $name ) ) {
            $this->dieWithError( 'Column name cannot be empty', 'invalidname' );
        }
        
        if ( strlen( $name ) > 255 ) {
            $this->dieWithError( 'Column name is too long', 'nametoolong' );
        }
        
        // 检查名称是否包含危险字符
        if ( preg_match( '/[<>"\']/', $name ) ) {
            $this->dieWithError( 'Column name contains invalid characters', 'invalidname' );
        }
        
        if ( !preg_match( '/^#[0-9A-Fa-f]{6}$/', $color ) ) {
            $this->dieWithError( 'Invalid color format', 'invalidcolor' );
        }
        
        if ( $width < 200 || $width > 800 ) {
            $this->dieWithError( 'Column width must be between 200 and 800 pixels', 'invalidwidth' );
        }
        
        if ( $maxCards < 0 || $maxCards > 1000 ) {
            $this->dieWithError( 'Maximum cards must be between 0 and 1000', 'invalidmaxcards' );
        }
        
        if ( $wipLimit < 0 || $wipLimit > 100 ) {
            $this->dieWithError( 'WIP limit must be between 0 and 100', 'invalidwiplimit' );
        }
    }
    
    /**
     * 检查列数限制
     */
    private function checkColumnLimit( $boardId ) {
        // 获取看板的最大列数设置
        $maxColumns = $this->getDB()->selectField(
            'kanban_boards',
            'board_max_columns',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$maxColumns ) {
            $maxColumns = 10; // 默认最大列数
        }
        
        // 获取当前列数
        $currentCount = $this->getDB()->selectField(
            'kanban_columns',
            'COUNT(*)',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( $currentCount >= $maxColumns ) {
            $this->dieWithError( 'Maximum number of columns exceeded', 'columnlimitexceeded' );
        }
    }
    
    /**
     * 计算列的顺序
     */
    private function calculateColumnOrder( $boardId, $position ) {
        if ( $position === -1 ) {
            // 插入到末尾
            $maxOrder = $this->getDB()->selectField(
                'kanban_columns',
                'MAX(column_order)',
                [ 'board_id' => $boardId ],
                __METHOD__
            );
            return ( $maxOrder ?? 0 ) + 1;
        } else {
            // 插入到指定位置，需要调整其他列的顺序
            $this->adjustColumnOrders( $boardId, $position );
            return $position;
        }
    }
    
    /**
     * 调整列顺序
     */
    private function adjustColumnOrders( $boardId, $insertPosition ) {
        $db = $this->getDB();
        $db->startAtomic( __METHOD__ );
        
        try {
            // 将插入位置及之后的列顺序+1
            $db->update(
                'kanban_statuses',
                [ 'status_order = status_order + 1' ],
                [ 'board_id' => $boardId, 'status_order >= ' . $insertPosition ],
                __METHOD__
            );
            
            $db->endAtomic( __METHOD__ );
        } catch ( Exception $e ) {
            $db->rollback( __METHOD__ );
            throw $e;
        }
    }
    
    /**
     * 插入新列
     */
    private function insertColumn( $data ) {
        $db = $this->getDB();

        // 写入新模型：kanban_statuses
        $insertData = [
            'board_id' => $data['board_id'],
            'status_key' => $data['status_key'],
            'status_name' => $data['status_name'],
            'status_order' => $data['status_order'],
            'color' => $data['color'],
            'wip_limit' => $data['wip_limit'] ?? 0,
        ];

        $db->startAtomic( __METHOD__ );
        try {
            $db->insert( 'kanban_statuses', $insertData, __METHOD__ );
            $columnId = $db->insertId();
            $db->endAtomic( __METHOD__ );
            return $columnId;
        } catch ( Exception $e ) {
            $db->rollback( __METHOD__ );
            throw $e;
        }
    }
    
    public function getAllowedParams() {
        return [
            'kanban_action' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true
            ],
            'board_id' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'name' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'description' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'color' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'position' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'width' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'max_cards' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'wip_limit' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ]
        ];
    }
    
    public function mustBePosted() {
        return false;
    }
    
    public function isWriteMode() {
        return false;
    }
    
    public function needsToken() {
        return false;
    }
    
    public function isReadMode() {
        return false;
    }
}