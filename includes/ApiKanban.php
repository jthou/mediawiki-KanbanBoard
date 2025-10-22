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
use Exception;

class ApiKanban extends ApiBase {
    
    private LBFactory $dbLoadBalancerFactory;
    
    public function __construct( ApiMain $mainModule, $moduleName ) {
        parent::__construct( $mainModule, $moduleName );
        $this->dbLoadBalancerFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
    }
    
    protected function getDB() {
        return $this->dbLoadBalancerFactory->getMainLB()->getConnectionRef( DB_PRIMARY );
    }
    
    public function execute() {
        $params = $this->extractRequestParams();
        $action = $params['kanban_action'];
        
        // 添加调试日志
        wfDebugLog('kanbanboard', 'API调用: ' . $action . ', 参数: ' . json_encode($params));
        error_log('KanbanBoard API调用: ' . $action . ', 参数: ' . json_encode($params));
        
        switch ( $action ) {
            case 'getboard':
                $this->getBoard( $params );
                break;
            case 'addcolumn':
                $this->addColumn( $params );
                break;
            case 'deletecolumn':
                $this->deleteColumn( $params );
                break;
            case 'updatecolumn':
                $this->updateColumn( $params );
                break;
            case 'reordercolumns':
                $this->reorderColumns( $params );
                break;
            case 'reordercards':
                $this->reorderCards( $params );
                break;
            case 'createtask':
                $this->createTask( $params );
                break;
            case 'updatetask':
                $this->updateTask( $params );
                break;
            case 'deletetask':
                $this->deleteTask( $params );
                break;
            case 'gethistory':
                $this->getTaskHistory( $params );
                break;
            case 'createmilestone':
                $this->createMilestone( $params );
                break;
            case 'updatemilestone':
                $this->updateMilestone( $params );
                break;
            case 'deletemilestone':
                $this->deleteMilestone( $params );
                break;
            case 'getmilestones':
                $this->getMilestones( $params );
                break;
            case 'getstats':
                $this->getStats( $params );
                break;
            case 'hideboard':
                $this->hideBoard( $params );
                break;
            case 'archiveboard':
                $this->archiveBoard( $params );
                break;
            case 'deleteboard':
                $this->deleteBoard( $params );
                break;
            case 'restoreboard':
                $this->restoreBoard( $params );
                break;
            case 'getboards':
                $this->getBoards( $params );
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
        
        wfDebugLog('kanbanboard', '获取看板数据: board_id=' . $boardId);
        
        // 检查看板是否存在
        $board = $this->getBoardData( $boardId );
        if ( !$board ) {
            wfDebugLog('kanbanboard', '看板不存在: board_id=' . $boardId);
            $this->dieWithError( 'Board not found', 'boardnotfound' );
        }
        
        wfDebugLog('kanbanboard', '看板数据: ' . json_encode($board));
        
        // 检查查看权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'view' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 获取列和卡片数据
        $columns = $this->getBoardColumns( $boardId );
        $board['columns'] = $columns;
        
        // 获取里程碑数据
        $milestones = $this->getBoardMilestones( $boardId );
        $board['milestones'] = $milestones;
        
        wfDebugLog('kanbanboard', '返回看板数据: ' . json_encode($board));
        
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
     * 删除列
     */
    private function deleteColumn( $params ) {
        $boardId = (int)$params['board_id'];
        $columnId = (int)$params['column_id'];
        $moveCardsTo = (int)($params['move_cards_to'] ?? 0); // 目标列ID，0表示删除卡片
        
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
        
        // 检查列是否存在且属于该看板
        $column = $this->getDB()->selectRow(
            'kanban_statuses',
            '*',
            [ 'status_id' => $columnId, 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$column ) {
            $this->dieWithError( 'Column not found', 'columnnotfound' );
        }
        
        // 检查是否是最小列数（至少保留1列）
        $columnCount = $this->getDB()->selectField(
            'kanban_statuses',
            'COUNT(*)',
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( $columnCount <= 1 ) {
            $this->dieWithError( 'Cannot delete the last column', 'lastcolumn' );
        }
        
        // 处理列中的卡片
        $this->handleCardsBeforeDeleteColumn( $columnId, $moveCardsTo );
        
        // 删除列
        $this->getDB()->delete(
            'kanban_statuses',
            [ 'status_id' => $columnId ],
            __METHOD__
        );
        
        // 重新排序剩余列
        $this->reorderColumns( $boardId );
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Column deleted successfully' );
    }
    
    /**
     * 更新列信息
     */
    private function updateColumn( $params ) {
        $columnId = (int)$params['column_id'];
        $name = trim( $params['name'] ?? '' );
        $description = trim( $params['description'] ?? '' );
        $color = $params['color'] ?? '#3498db';
        $wipLimit = (int)($params['wip_limit'] ?? 0);
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取列信息
        $column = $this->getDB()->selectRow(
            'kanban_statuses',
            '*',
            [ 'status_id' => $columnId ],
            __METHOD__
        );
        
        if ( !$column ) {
            $this->dieWithError( 'Column not found', 'columnnotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $column->board_id, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 验证输入
        if ( empty( $name ) ) {
            $this->dieWithError( 'Column name cannot be empty', 'emptyname' );
        }
        
        if ( strlen( $name ) > 255 ) {
            $this->dieWithError( 'Column name is too long', 'nametoolong' );
        }
        
        // 检查名称是否重复（排除当前列）
        $existingColumn = $this->getDB()->selectRow(
            'kanban_statuses',
            'status_id',
            [ 'board_id' => $column->board_id, 'status_name' => $name, 'status_id != ' . $columnId ],
            __METHOD__
        );
        
        if ( $existingColumn ) {
            $this->dieWithError( 'Column name already exists', 'duplicatename' );
        }
        
        // 更新列信息
        $result = $this->getDB()->update(
            'kanban_statuses',
            [
                'status_name' => $name,
                'color' => $color,
                'wip_limit' => $wipLimit
            ],
            [ 'status_id' => $columnId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to update column', 'updatefailed' );
        }
        
        // 返回更新后的列信息
        $updatedColumn = $this->getColumnData( $columnId );
        $this->getResult()->addValue( null, 'column', $updatedColumn );
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Column updated successfully' );
    }
    
    /**
     * 处理删除列前的卡片迁移
     */
    private function handleCardsBeforeDeleteColumn( $columnId, $moveCardsTo ) {
        // 获取要删除列中的所有卡片
        $cards = $this->getDB()->select(
            'kanban_tasks',
            '*',
            [ 'status_id' => $columnId ],
            __METHOD__
        );
        
        if ( $moveCardsTo > 0 ) {
            // 将卡片移动到目标列
            foreach ( $cards as $card ) {
                $this->getDB()->update(
                    'kanban_tasks',
                    [ 'status_id' => $moveCardsTo ],
                    [ 'task_id' => $card->task_id ],
                    __METHOD__
                );
            }
        } else {
            // 删除所有卡片
            $this->getDB()->delete(
                'kanban_tasks',
                [ 'status_id' => $columnId ],
                __METHOD__
            );
        }
    }
    
    /**
     * 重新排序列（拖拽排序）
     */
    private function reorderColumns( $params ) {
        $boardId = (int)$params['board_id'];
        $columnOrdersJson = $params['column_orders']; // JSON字符串格式
        
        // 添加调试信息
        error_log( "KanbanBoard: reorderColumns called with board_id: $boardId" );
        error_log( "KanbanBoard: column_orders JSON: $columnOrdersJson" );
        
        // 解析JSON参数
        $columnOrders = json_decode( $columnOrdersJson, true );
        
        // 验证输入
        if ( !is_array( $columnOrders ) || empty( $columnOrders ) ) {
            error_log( "KanbanBoard: Invalid column orders data" );
            $this->dieWithError( 'Invalid column orders', 'invalidorders' );
        }
        
        error_log( "KanbanBoard: Parsed column orders: " . print_r( $columnOrders, true ) );
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkColumnPermission( $user->getId(), $boardId, 'edit' ) ) {
            error_log( "KanbanBoard: Permission denied for user {$user->getId()} on board $boardId" );
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        $db = $this->getDB();
        $db->startAtomic( __METHOD__ );
        
        try {
            // 批量更新列顺序
            foreach ( $columnOrders as $columnOrder ) {
                $columnId = (int)$columnOrder['column_id'];
                $newOrder = (int)$columnOrder['order'];
                
                error_log( "KanbanBoard: Updating column $columnId to order $newOrder" );
                
                // 验证列是否属于该看板
                $exists = $db->selectField(
                    'kanban_statuses',
                    'status_id',
                    [ 'status_id' => $columnId, 'board_id' => $boardId ],
                    __METHOD__
                );
                
                if ( !$exists ) {
                    throw new Exception( "Column {$columnId} not found in board {$boardId}" );
                }
                
                // 更新列顺序
                $db->update(
                    'kanban_statuses',
                    [ 'status_order' => $newOrder ],
                    [ 'status_id' => $columnId ],
                    __METHOD__
                );
            }
            
            $db->endAtomic( __METHOD__ );
            
            error_log( "KanbanBoard: Column reordering completed successfully" );
            
            $this->getResult()->addValue( null, 'result', 'success' );
            $this->getResult()->addValue( null, 'message', 'Columns reordered successfully' );
            
        } catch ( Exception $e ) {
            $db->rollback( __METHOD__ );
            error_log( "KanbanBoard: Error reordering columns: " . $e->getMessage() );
            $this->dieWithError( 'Failed to reorder columns: ' . $e->getMessage(), 'reorderfailed' );
        }
    }
    
    /**
     * 重新排序列（整理顺序）
     */
    private function normalizeColumnOrders( $boardId ) {
        $columns = $this->getDB()->select(
            'kanban_statuses',
            [ 'status_id', 'status_order' ],
            [ 'board_id' => $boardId ],
            __METHOD__,
            [ 'ORDER BY' => 'status_order ASC' ]
        );
        
        $order = 1;
        foreach ( $columns as $column ) {
            $this->getDB()->update(
                'kanban_statuses',
                [ 'status_order' => $order ],
                [ 'status_id' => $column->status_id ],
                __METHOD__
            );
            $order++;
        }
    }
    
    /**
     * 检查列操作权限
     */
    private function checkColumnPermission( $userId, $boardId, $permission = 'view' ) {
        $db = $this->getDB();
        
        // 检查是否是看板所有者
        $isOwner = $db->selectField(
            'kanban_boards',
            'board_owner_id',
            [ 'board_id' => $boardId ],
            __METHOD__
        ) == $userId;
        
        if ( $isOwner ) {
            return true;
        }
        
        // 检查用户权限
        $userPermission = $db->selectField(
            'kanban_permissions',
            'role',
            [ 'board_id' => $boardId, 'user_id' => $userId ],
            __METHOD__
        );
        
        if ( !$userPermission ) {
            return false;
        }
        
        $permissionLevels = [ 'view' => 1, 'edit' => 2, 'admin' => 3 ];
        $userLevel = $permissionLevels[$userPermission] ?? 0;
        $requiredLevel = $permissionLevels[$permission] ?? 1;
        
        return $userLevel >= $requiredLevel;
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
        wfDebugLog('kanbanboard', '获取看板列数据: board_id=' . $boardId);
        
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
                [ 
                    'status_id' => $status->status_id,
                    'deleted_at IS NULL'
                ],
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
                    'status_name' => $statusArr['status_name'],
                    'card_title' => $t['title'],
                    'card_description' => $t['description'],
                    'card_assignee_id' => null,
                    'card_creator_id' => $t['created_by'] ?? null,
                    'card_priority' => $t['priority'],
                    'card_color' => $t['color'],
                    'card_order' => $t['task_order'],
                    'card_due_date' => $t['due_date'],
                    'card_completed_at' => $t['completed_at'],
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
                'is_terminal' => $statusArr['is_terminal'],
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
            ],
            'column_id' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'move_cards_to' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'column_orders' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'card_orders' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'task_id' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'title' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'description' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'priority' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'due_date' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'status_id' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'limit' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'offset' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'milestone_id' => [
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'target_date' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'completed_date' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'status' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'board_status' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'filter_status' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'time_range' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
                ParamValidator::PARAM_DEFAULT => 'month'
            ],
            'start_date' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'end_date' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ]
        ];
    }
    
    public function mustBePosted() {
        $params = $this->extractRequestParams();
        $action = $params['kanban_action'] ?? '';
        
        // 写操作需要POST请求
        $writeActions = ['addcolumn', 'deletecolumn', 'updatecolumn', 'createtask', 'updatetask', 'deletetask', 'createmilestone', 'updatemilestone', 'deletemilestone', 'hideboard', 'archiveboard', 'deleteboard', 'restoreboard'];
        return in_array($action, $writeActions);
    }
    
    public function isWriteMode() {
        $params = $this->extractRequestParams();
        $action = $params['kanban_action'] ?? '';
        
        // 写操作需要返回true
        $writeActions = ['addcolumn', 'deletecolumn', 'updatecolumn', 'createtask', 'updatetask', 'deletetask', 'createmilestone', 'updatemilestone', 'deletemilestone', 'hideboard', 'archiveboard', 'deleteboard', 'restoreboard'];
        return in_array($action, $writeActions);
    }
    
    public function needsToken() {
        return false;
    }
    
    /**
     * 重新排序卡片
     */
    private function reorderCards( $params ) {
        $boardId = (int)$params['board_id'];
        $cardOrdersJson = $params['card_orders']; // JSON字符串格式
        
        // 添加调试信息
        error_log( "KanbanBoard: reorderCards called with board_id: $boardId" );
        error_log( "KanbanBoard: card_orders JSON: $cardOrdersJson" );
        
        // 解析JSON参数
        $cardOrders = json_decode( $cardOrdersJson, true );
        
        // 验证输入
        if ( !is_array( $cardOrders ) || empty( $cardOrders ) ) {
            error_log( "KanbanBoard: Invalid card orders data" );
            $this->dieWithError( 'Invalid card orders', 'invalidorders' );
        }
        
        error_log( "KanbanBoard: Parsed card orders: " . print_r( $cardOrders, true ) );
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'edit' ) ) {
            error_log( "KanbanBoard: Permission denied for user {$user->getId()} on board $boardId" );
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        $db = $this->getDB();
        $db->startAtomic( __METHOD__ );
        
        try {
            // 批量更新卡片顺序
            foreach ( $cardOrders as $cardOrder ) {
                $cardId = (int)$cardOrder['card_id'];
                $newOrder = (int)$cardOrder['order'];
                $newStatusId = isset($cardOrder['status_id']) ? (int)$cardOrder['status_id'] : null;
                
                error_log( "KanbanBoard: Updating card $cardId to order $newOrder" );
                if ($newStatusId) {
                    error_log( "KanbanBoard: Updating card $cardId status to $newStatusId" );
                }
                
                // 验证卡片是否属于该看板
                $exists = $db->selectField(
                    'kanban_tasks',
                    'task_id',
                    [ 'task_id' => $cardId ],
                    __METHOD__
                );
                
                if ( !$exists ) {
                    throw new Exception( "Card {$cardId} not found" );
                }
                
                // 准备更新数据
                $updateData = [ 'task_order' => $newOrder ];
                
                // 如果提供了新的状态ID，验证并更新状态
                if ( $newStatusId ) {
                    $statusExists = $db->selectField(
                        'kanban_statuses',
                        'status_id',
                        [ 'status_id' => $newStatusId, 'board_id' => $boardId ],
                        __METHOD__
                    );
                    
                    if ( !$statusExists ) {
                        throw new Exception( "Status {$newStatusId} not found in board {$boardId}" );
                    }
                    
                    $updateData['status_id'] = $newStatusId;
                }
                
                // 更新卡片顺序和状态
                $db->update(
                    'kanban_tasks',
                    $updateData,
                    [ 'task_id' => $cardId ],
                    __METHOD__
                );
            }
            
            $db->endAtomic( __METHOD__ );
            
            error_log( "KanbanBoard: Card reordering completed successfully" );
            
            $this->getResult()->addValue( null, 'result', 'success' );
            $this->getResult()->addValue( null, 'message', 'Cards reordered successfully' );
            
        } catch ( Exception $e ) {
            $db->rollback( __METHOD__ );
            error_log( "KanbanBoard: Error reordering cards: " . $e->getMessage() );
            $this->dieWithError( 'Failed to reorder cards: ' . $e->getMessage(), 'reorderfailed' );
        }
    }
    
    /**
     * 创建任务
     */
    private function createTask( $params ) {
        $boardId = (int)$params['board_id'];
        $columnId = (int)$params['column_id'];
        $title = trim( $params['title'] ?? '' );
        $description = trim( $params['description'] ?? '' );
        $priority = $params['priority'] ?? 'medium';
        $color = $params['color'] ?? '#ffffff';
        $dueDate = $params['due_date'] ?? null;
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 验证输入
        if ( empty( $title ) ) {
            $this->dieWithError( 'Task title cannot be empty', 'emptytitle' );
        }
        
        if ( strlen( $title ) > 500 ) {
            $this->dieWithError( 'Task title is too long', 'titletoolong' );
        }
        
        if ( !in_array( $priority, [ 'low', 'medium', 'high', 'urgent' ] ) ) {
            $this->dieWithError( 'Invalid priority', 'invalidpriority' );
        }
        
        // 检查列是否存在且属于该看板
        $column = $this->getDB()->selectRow(
            'kanban_statuses',
            '*',
            [ 'status_id' => $columnId, 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$column ) {
            $this->dieWithError( 'Column not found', 'columnnotfound' );
        }
        
        // 获取下一个任务顺序
        $nextOrder = $this->getDB()->selectField(
            'kanban_tasks',
            'MAX(task_order) + 1',
            [ 'status_id' => $columnId ],
            __METHOD__
        ) ?: 1;
        
        // 插入新任务
        $taskId = $this->getDB()->insert(
            'kanban_tasks',
            [
                'board_id' => $boardId,
                'status_id' => $columnId,
                'title' => $title,
                'description' => $description ?: null,
                'priority' => $priority,
                'color' => $color,
                'task_order' => $nextOrder,
                'due_date' => $dueDate ?: null,
                'created_by' => $user->getId(),
                'updated_by' => $user->getId()
            ],
            __METHOD__
        );
        
        if ( !$taskId ) {
            $this->dieWithError( 'Failed to create task', 'createfailed' );
        }
        
        // 记录任务创建历史
        $this->recordTaskHistory( $taskId, null, [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'color' => $color,
            'status_id' => $columnId,
            'due_date' => $dueDate
        ], $user->getId(), 'create', 'Task created' );
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'task_id', $taskId );
        $this->getResult()->addValue( null, 'message', 'Task created successfully' );
    }
    
    /**
     * 更新任务
     */
    private function updateTask( $params ) {
        $taskId = (int)$params['task_id'];
        $title = trim( $params['title'] ?? '' );
        $description = trim( $params['description'] ?? '' );
        $priority = $params['priority'] ?? 'medium';
        $color = $params['color'] ?? '#ffffff';
        $dueDate = $params['due_date'] ?? null;
        $statusId = (int)($params['status_id'] ?? 0);
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取任务信息
        $task = $this->getDB()->selectRow(
            'kanban_tasks',
            '*',
            [ 'task_id' => $taskId ],
            __METHOD__
        );
        
        if ( !$task ) {
            $this->dieWithError( 'Task not found', 'tasknotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $task->board_id, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 验证输入
        if ( empty( $title ) ) {
            $this->dieWithError( 'Task title cannot be empty', 'emptytitle' );
        }
        
        if ( strlen( $title ) > 500 ) {
            $this->dieWithError( 'Task title is too long', 'titletoolong' );
        }
        
        if ( !in_array( $priority, [ 'low', 'medium', 'high', 'urgent' ] ) ) {
            $this->dieWithError( 'Invalid priority', 'invalidpriority' );
        }
        
        // 如果状态ID发生变化，验证新状态是否有效
        if ( $statusId > 0 && $statusId != $task->status_id ) {
            $newStatus = $this->getDB()->selectRow(
                'kanban_statuses',
                '*',
                [ 'status_id' => $statusId, 'board_id' => $task->board_id ],
                __METHOD__
            );
            
            if ( !$newStatus ) {
                $this->dieWithError( 'Invalid status', 'invalidstatus' );
            }
            
            // 获取新状态下的最大任务顺序
            $maxOrder = $this->getDB()->selectField(
                'kanban_tasks',
                'MAX(task_order)',
                [ 'status_id' => $statusId ],
                __METHOD__
            ) ?: 0;
            
            // 处理完成时间逻辑
            $this->handleTaskCompletionStatus( $task, $newStatus );
        }
        
        // 准备更新数据
        $updateData = [
            'title' => $title,
            'description' => $description ?: null,
            'priority' => $priority,
            'color' => $color,
            'updated_by' => $user->getId()
        ];
        
        // 根据任务状态决定更新哪个日期字段
        if ( $statusId > 0 && $statusId != $task->status_id ) {
            // 状态发生变化，检查新状态是否为终态
            $newStatus = $this->getDB()->selectRow(
                'kanban_statuses',
                '*',
                [ 'status_id' => $statusId, 'board_id' => $task->board_id ],
                __METHOD__
            );
            
            if ( $newStatus && $newStatus->is_terminal ) {
                // 移动到终态，更新完成时间
                $updateData['completed_at'] = $dueDate ?: $this->getDB()->timestamp();
            } else {
                // 移动到非终态，更新截止日期
                $updateData['due_date'] = $dueDate ?: null;
            }
        } else {
            // 状态未变化，根据当前状态决定更新哪个字段
            $currentStatus = $this->getDB()->selectRow(
                'kanban_statuses',
                '*',
                [ 'status_id' => $task->status_id ],
                __METHOD__
            );
            
            if ( $currentStatus && $currentStatus->is_terminal ) {
                // 当前是终态，更新完成时间
                $updateData['completed_at'] = $dueDate ?: null;
            } else {
                // 当前是非终态，更新截止日期
                $updateData['due_date'] = $dueDate ?: null;
            }
        }
        
        // 如果状态发生变化，更新状态和顺序
        if ( $statusId > 0 && $statusId != $task->status_id ) {
            $updateData['status_id'] = $statusId;
            $updateData['task_order'] = $maxOrder + 1;
        }
        
        // 记录任务更新历史
        $this->recordTaskHistory( $taskId, (array)$task, $updateData, $user->getId(), 'update', 'Task updated' );
        
        // 更新任务
        $result = $this->getDB()->update(
            'kanban_tasks',
            $updateData,
            [ 'task_id' => $taskId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to update task', 'updatefailed' );
        }
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Task updated successfully' );
    }
    
    /**
     * 删除任务
     */
    private function deleteTask( $params ) {
        $taskId = (int)$params['task_id'];
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取任务信息
        $task = $this->getDB()->selectRow(
            'kanban_tasks',
            '*',
            [ 'task_id' => $taskId ],
            __METHOD__
        );
        
        if ( !$task ) {
            $this->dieWithError( 'Task not found', 'tasknotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $task->board_id, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 记录任务删除历史
        $this->recordTaskHistory( $taskId, (array)$task, null, $user->getId(), 'delete', 'Task deleted' );
        
        // 软删除任务
        $result = $this->getDB()->update(
            'kanban_tasks',
            [
                'deleted_at' => $this->getDB()->timestamp(),
                'updated_by' => $user->getId()
            ],
            [ 'task_id' => $taskId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to delete task', 'deletefailed' );
        }
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Task deleted successfully' );
    }
    
    /**
     * 记录任务历史
     */
    private function recordTaskHistory( $taskId, $oldData, $newData, $userId, $changeType, $reason = null ) {
        $db = $this->getDB();
        $request = $this->getMain()->getRequest();
        
        // 获取客户端信息
        $ipAddress = $request->getIP();
        $userAgent = $request->getHeader( 'User-Agent' );
        
        // 如果是创建操作，记录所有字段
        if ( $changeType === 'create' ) {
            foreach ( $newData as $field => $value ) {
                if ( $field === 'updated_by' ) continue; // 跳过系统字段
                
                $db->insert(
                    'kanban_task_history',
                    [
                        'task_id' => $taskId,
                        'field_name' => $field,
                        'old_value' => null,
                        'new_value' => $value,
                        'changed_by' => $userId,
                        'change_type' => $changeType,
                        'change_reason' => $reason,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent
                    ],
                    __METHOD__
                );
            }
        }
        // 如果是更新操作，只记录有变化的字段
        elseif ( $changeType === 'update' && $oldData && $newData ) {
            foreach ( $newData as $field => $newValue ) {
                if ( $field === 'updated_by' ) continue; // 跳过系统字段
                
                $oldValue = $oldData[$field] ?? null;
                
                // 只记录有变化的字段
                if ( $oldValue != $newValue ) {
                    $db->insert(
                        'kanban_task_history',
                        [
                            'task_id' => $taskId,
                            'field_name' => $field,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'changed_by' => $userId,
                            'change_type' => $changeType,
                            'change_reason' => $reason,
                            'ip_address' => $ipAddress,
                            'user_agent' => $userAgent
                        ],
                        __METHOD__
                    );
                }
            }
        }
        // 如果是删除操作
        elseif ( $changeType === 'delete' && $oldData ) {
            $db->insert(
                'kanban_task_history',
                [
                    'task_id' => $taskId,
                    'field_name' => 'deleted',
                    'old_value' => 'active',
                    'new_value' => 'deleted',
                    'changed_by' => $userId,
                    'change_type' => $changeType,
                    'change_reason' => $reason,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ],
                __METHOD__
            );
        }
    }
    
    /**
     * 获取任务历史记录
     */
    private function getTaskHistory( $params ) {
        $taskId = (int)$params['task_id'];
        $limit = (int)($params['limit'] ?? 50);
        $offset = (int)($params['offset'] ?? 0);
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取任务信息以检查权限
        $task = $this->getDB()->selectRow(
            'kanban_tasks',
            '*',
            [ 'task_id' => $taskId ],
            __METHOD__
        );
        
        if ( !$task ) {
            $this->dieWithError( 'Task not found', 'tasknotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $task->board_id, 'view' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 获取历史记录
        $history = $this->getDB()->select(
            [ 'kanban_task_history', 'user' ],
            [
                'history_id',
                'field_name',
                'old_value',
                'new_value',
                'changed_at',
                'change_type',
                'change_reason',
                'user_name'
            ],
            [ 'task_id' => $taskId ],
            __METHOD__,
            [
                'ORDER BY' => 'changed_at DESC',
                'LIMIT' => $limit,
                'OFFSET' => $offset
            ],
            [
                'user' => [ 'LEFT JOIN', 'kanban_task_history.changed_by = user.user_id' ]
            ]
        );
        
        $historyData = [];
        foreach ( $history as $record ) {
            $historyData[] = [
                'history_id' => $record->history_id,
                'field_name' => $record->field_name,
                'old_value' => $record->old_value,
                'new_value' => $record->new_value,
                'changed_at' => $record->changed_at,
                'change_type' => $record->change_type,
                'change_reason' => $record->change_reason,
                'changed_by' => $record->user_name
            ];
        }
        
        $this->getResult()->addValue( null, 'history', $historyData );
        $this->getResult()->addValue( null, 'result', 'success' );
    }
    
    /**
     * 获取看板的里程碑数据
     */
    private function getBoardMilestones( $boardId ) {
        $milestones = $this->getDB()->select(
            'kanban_milestones',
            '*',
            [ 
                'board_id' => $boardId,
                'deleted_at IS NULL'
            ],
            __METHOD__,
            [ 'ORDER BY' => 'milestone_order ASC, target_date ASC' ]
        );
        
        $milestonesData = [];
        foreach ( $milestones as $milestone ) {
            $milestonesData[] = [
                'milestone_id' => $milestone->milestone_id,
                'board_id' => $milestone->board_id,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'target_date' => $milestone->target_date,
                'completed_date' => $milestone->completed_date,
                'status' => $milestone->status,
                'color' => $milestone->color,
                'milestone_order' => $milestone->milestone_order,
                'created_by' => $milestone->created_by,
                'created_at' => $milestone->created_at,
                'updated_at' => $milestone->updated_at
            ];
        }
        
        return $milestonesData;
    }
    
    /**
     * 创建里程碑
     */
    private function createMilestone( $params ) {
        $boardId = (int)$params['board_id'];
        $title = trim( $params['title'] ?? '' );
        $description = trim( $params['description'] ?? '' );
        $targetDate = $params['target_date'] ?? null;
        $color = $params['color'] ?? '#9b59b6';
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 验证输入
        if ( empty( $title ) ) {
            $this->dieWithError( 'Milestone title cannot be empty', 'emptytitle' );
        }
        
        if ( strlen( $title ) > 255 ) {
            $this->dieWithError( 'Milestone title is too long', 'titletoolong' );
        }
        
        // 获取下一个里程碑顺序
        $nextOrder = $this->getDB()->selectField(
            'kanban_milestones',
            'MAX(milestone_order) + 1',
            [ 'board_id' => $boardId ],
            __METHOD__
        ) ?: 1;
        
        // 插入新里程碑
        $milestoneId = $this->getDB()->insert(
            'kanban_milestones',
            [
                'board_id' => $boardId,
                'title' => $title,
                'description' => $description ?: null,
                'target_date' => $targetDate ?: null,
                'color' => $color,
                'milestone_order' => $nextOrder,
                'created_by' => $user->getId(),
                'updated_by' => $user->getId()
            ],
            __METHOD__
        );
        
        if ( !$milestoneId ) {
            $this->dieWithError( 'Failed to create milestone', 'createfailed' );
        }
        
        // 记录里程碑创建历史
        $this->recordMilestoneHistory( $milestoneId, null, [
            'title' => $title,
            'description' => $description,
            'target_date' => $targetDate,
            'color' => $color
        ], $user->getId(), 'create', 'Milestone created' );
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'milestone_id', $milestoneId );
        $this->getResult()->addValue( null, 'message', 'Milestone created successfully' );
    }
    
    /**
     * 更新里程碑
     */
    private function updateMilestone( $params ) {
        $milestoneId = (int)$params['milestone_id'];
        $title = trim( $params['title'] ?? '' );
        $description = trim( $params['description'] ?? '' );
        $targetDate = $params['target_date'] ?? null;
        $completedDate = $params['completed_date'] ?? null;
        $status = $params['status'] ?? 'planned';
        $color = $params['color'] ?? '#9b59b6';
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取里程碑信息
        $milestone = $this->getDB()->selectRow(
            'kanban_milestones',
            '*',
            [ 'milestone_id' => $milestoneId ],
            __METHOD__
        );
        
        if ( !$milestone ) {
            $this->dieWithError( 'Milestone not found', 'milestonenotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $milestone->board_id, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 验证输入
        if ( empty( $title ) ) {
            $this->dieWithError( 'Milestone title cannot be empty', 'emptytitle' );
        }
        
        if ( strlen( $title ) > 255 ) {
            $this->dieWithError( 'Milestone title is too long', 'titletoolong' );
        }
        
        if ( !in_array( $status, [ 'planned', 'in_progress', 'completed', 'cancelled' ] ) ) {
            $this->dieWithError( 'Invalid status', 'invalidstatus' );
        }
        
        // 准备更新数据
        $updateData = [
            'title' => $title,
            'description' => $description ?: null,
            'target_date' => $targetDate ?: null,
            'completed_date' => $completedDate ?: null,
            'status' => $status,
            'color' => $color,
            'updated_by' => $user->getId()
        ];
        
        // 记录里程碑更新历史
        $this->recordMilestoneHistory( $milestoneId, (array)$milestone, $updateData, $user->getId(), 'update', 'Milestone updated' );
        
        // 更新里程碑
        $result = $this->getDB()->update(
            'kanban_milestones',
            $updateData,
            [ 'milestone_id' => $milestoneId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to update milestone', 'updatefailed' );
        }
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Milestone updated successfully' );
    }
    
    /**
     * 删除里程碑
     */
    private function deleteMilestone( $params ) {
        $milestoneId = (int)$params['milestone_id'];
        
        // 检查权限
        $user = $this->getUser();
        
        // 获取里程碑信息
        $milestone = $this->getDB()->selectRow(
            'kanban_milestones',
            '*',
            [ 'milestone_id' => $milestoneId ],
            __METHOD__
        );
        
        if ( !$milestone ) {
            $this->dieWithError( 'Milestone not found', 'milestonenotfound' );
        }
        
        if ( !$this->checkBoardPermission( $user->getId(), $milestone->board_id, 'edit' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 记录里程碑删除历史
        $this->recordMilestoneHistory( $milestoneId, (array)$milestone, null, $user->getId(), 'delete', 'Milestone deleted' );
        
        // 软删除里程碑
        $result = $this->getDB()->update(
            'kanban_milestones',
            [
                'deleted_at' => $this->getDB()->timestamp(),
                'updated_by' => $user->getId()
            ],
            [ 'milestone_id' => $milestoneId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to delete milestone', 'deletefailed' );
        }
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Milestone deleted successfully' );
    }
    
    /**
     * 获取里程碑列表
     */
    private function getMilestones( $params ) {
        $boardId = (int)$params['board_id'];
        
        // 检查权限
        $user = $this->getUser();
        if ( !$this->checkBoardPermission( $user->getId(), $boardId, 'view' ) ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        $milestones = $this->getBoardMilestones( $boardId );
        
        $this->getResult()->addValue( null, 'milestones', $milestones );
        $this->getResult()->addValue( null, 'result', 'success' );
    }
    
    /**
     * 记录里程碑历史
     */
    private function recordMilestoneHistory( $milestoneId, $oldData, $newData, $userId, $changeType, $reason = null ) {
        $db = $this->getDB();
        $request = $this->getMain()->getRequest();
        
        // 获取客户端信息
        $ipAddress = $request->getIP();
        $userAgent = $request->getHeader( 'User-Agent' );
        
        // 如果是创建操作，记录所有字段
        if ( $changeType === 'create' ) {
            foreach ( $newData as $field => $value ) {
                if ( $field === 'updated_by' ) continue; // 跳过系统字段
                
                $db->insert(
                    'kanban_milestone_history',
                    [
                        'milestone_id' => $milestoneId,
                        'field_name' => $field,
                        'old_value' => null,
                        'new_value' => $value,
                        'changed_by' => $userId,
                        'change_type' => $changeType,
                        'change_reason' => $reason,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent
                    ],
                    __METHOD__
                );
            }
        }
        // 如果是更新操作，只记录有变化的字段
        elseif ( $changeType === 'update' && $oldData && $newData ) {
            foreach ( $newData as $field => $newValue ) {
                if ( $field === 'updated_by' ) continue; // 跳过系统字段
                
                $oldValue = $oldData[$field] ?? null;
                
                // 只记录有变化的字段
                if ( $oldValue != $newValue ) {
                    $db->insert(
                        'kanban_milestone_history',
                        [
                            'milestone_id' => $milestoneId,
                            'field_name' => $field,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'changed_by' => $userId,
                            'change_type' => $changeType,
                            'change_reason' => $reason,
                            'ip_address' => $ipAddress,
                            'user_agent' => $userAgent
                        ],
                        __METHOD__
                    );
                }
            }
        }
        // 如果是删除操作
        elseif ( $changeType === 'delete' && $oldData ) {
            $db->insert(
                'kanban_milestone_history',
                [
                    'milestone_id' => $milestoneId,
                    'field_name' => 'deleted',
                    'old_value' => 'active',
                    'new_value' => 'deleted',
                    'changed_by' => $userId,
                    'change_type' => $changeType,
                    'change_reason' => $reason,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ],
                __METHOD__
            );
        }
    }
    
    /**
     * 隐藏看板
     */
    private function hideBoard( $params ) {
        $boardId = (int)$params['board_id'];
        $this->changeBoardStatus( $boardId, 'hidden' );
    }
    
    /**
     * 存档看板
     */
    private function archiveBoard( $params ) {
        $boardId = (int)$params['board_id'];
        $this->changeBoardStatus( $boardId, 'archived' );
    }
    
    /**
     * 删除看板
     */
    private function deleteBoard( $params ) {
        $boardId = (int)$params['board_id'];
        $this->changeBoardStatus( $boardId, 'deleted' );
    }
    
    /**
     * 恢复看板
     */
    private function restoreBoard( $params ) {
        $boardId = (int)$params['board_id'];
        $this->changeBoardStatus( $boardId, 'active' );
    }
    
    /**
     * 获取看板列表
     */
    private function getBoards( $params ) {
        $user = $this->getUser();
        $filterStatus = $params['filter_status'] ?? 'active';
        
        // 构建查询条件
        $conditions = [];
        
        // 根据状态过滤
        if ( $filterStatus !== 'all' ) {
            $conditions['board_status'] = $filterStatus;
        }
        
        // 权限过滤：只显示用户有权限的看板
        $boards = $this->getDB()->select(
            'kanban_boards',
            '*',
            $conditions,
            __METHOD__,
            [ 'ORDER BY' => 'board_created_at DESC' ]
        );
        
        $boardsData = [];
        foreach ( $boards as $board ) {
            // 检查用户权限
            if ( $this->checkBoardPermission( $user->getId(), $board->board_id, 'view' ) ) {
                $boardsData[] = [
                    'board_id' => $board->board_id,
                    'board_name' => $board->board_name,
                    'board_description' => $board->board_description,
                    'board_owner_id' => $board->board_owner_id,
                    'board_permissions' => $board->board_permissions,
                    'board_status' => $board->board_status,
                    'status_changed_at' => $board->status_changed_at,
                    'status_changed_by' => $board->status_changed_by,
                    'board_created_at' => $board->board_created_at,
                    'board_updated_at' => $board->board_updated_at
                ];
            }
        }
        
        $this->getResult()->addValue( null, 'boards', $boardsData );
        $this->getResult()->addValue( null, 'result', 'success' );
    }
    
    /**
     * 更改看板状态
     */
    private function changeBoardStatus( $boardId, $newStatus ) {
        $user = $this->getUser();
        
        // 检查看板是否存在
        $board = $this->getBoardData( $boardId );
        if ( !$board ) {
            $this->dieWithError( 'Board not found', 'boardnotfound' );
        }
        
        // 检查权限（只有所有者可以更改状态）
        if ( $board['board_owner_id'] != $user->getId() ) {
            $this->dieWithError( 'Permission denied', 'permissiondenied' );
        }
        
        // 更新看板状态
        $result = $this->getDB()->update(
            'kanban_boards',
            [
                'board_status' => $newStatus,
                'status_changed_at' => $this->getDB()->timestamp(),
                'status_changed_by' => $user->getId()
            ],
            [ 'board_id' => $boardId ],
            __METHOD__
        );
        
        if ( !$result ) {
            $this->dieWithError( 'Failed to change board status', 'statuschangefailed' );
        }
        
        $this->getResult()->addValue( null, 'result', 'success' );
        $this->getResult()->addValue( null, 'message', 'Board status changed successfully' );
    }
    
    /**
     * 处理任务完成状态变更
     */
    private function handleTaskCompletionStatus( $task, $newStatus ) {
        $db = $this->getDB();
        
        // 获取旧状态信息
        $oldStatus = $db->selectRow(
            'kanban_statuses',
            '*',
            [ 'status_id' => $task->status_id ],
            __METHOD__
        );
        
        // 如果移动到终态列且之前没有完成时间，设置完成时间
        if ( $newStatus->is_terminal && !$task->completed_at ) {
            $db->update(
                'kanban_tasks',
                [ 'completed_at' => $db->timestamp() ],
                [ 'task_id' => $task->task_id ],
                __METHOD__
            );
        }
        // 如果从终态列移出，清空完成时间
        elseif ( $oldStatus && $oldStatus->is_terminal && !$newStatus->is_terminal ) {
            $db->update(
                'kanban_tasks',
                [ 'completed_at' => null ],
                [ 'task_id' => $task->task_id ],
                __METHOD__
            );
        }
    }
    
    /**
     * 获取看板统计数据
     */
    private function getStats( $params ) {
        $boardId = $params['board_id'] ?? null;
        $timeRange = $params['time_range'] ?? 'month';
        
        wfDebugLog('kanbanboard', '获取统计数据: board_id=' . $boardId . ', time_range=' . $timeRange);
        
        $db = $this->getDB();
        
        // 计算时间范围
        $dateCondition = $this->getDateCondition( $timeRange );
        
        // 获取总体统计
        $overview = $this->getOverviewStats( $db, $boardId, $dateCondition );
        
        // 获取按时间范围的任务统计
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $timeRangeTasks = $this->getTimeRangeTasks( $db, $boardId, $timeRange, $startDate, $endDate );
        
        // 获取任务趋势数据
        $trendData = $this->getTaskTrendData( $db, $boardId, $timeRange, $startDate, $endDate );
        
        // 获取最近完成的任务
        $recentTasks = $this->getRecentTasks( $db, $boardId, $dateCondition );
        
        $result = [
            'overview' => $overview,
            'time_range_tasks' => $timeRangeTasks,
            'trend_data' => $trendData,
            'recent_tasks' => $recentTasks
        ];
        
        wfDebugLog('kanbanboard', '统计数据: ' . json_encode($result));
        
        $this->getResult()->addValue( null, 'stats', $result );
    }
    
    /**
     * 根据时间范围获取日期条件
     */
    private function getDateCondition( $timeRange ) {
        $now = time();
        
        switch ( $timeRange ) {
            case 'week':
                $startTime = $now - (7 * 24 * 60 * 60);
                break;
            case 'month':
                $startTime = $now - (30 * 24 * 60 * 60);
                break;
            case 'quarter':
                $startTime = $now - (90 * 24 * 60 * 60);
                break;
            case 'year':
                $startTime = $now - (365 * 24 * 60 * 60);
                break;
            case 'all':
            default:
                $startTime = 0;
                break;
        }
        
        return $startTime;
    }
    
    /**
     * 获取总体统计数据
     */
    private function getOverviewStats( $db, $boardId, $dateCondition ) {
        // 构建查询条件
        $whereCondition = [];
        if ( $boardId ) {
            $whereCondition['board_id'] = $boardId;
        }
        
        // 总任务数
        $totalTasks = $db->selectField(
            'kanban_tasks',
            'COUNT(*)',
            $whereCondition,
            __METHOD__
        );
        
        // 已完成任务数
        $completedCondition = array_merge( $whereCondition, [ 'completed_at IS NOT NULL' ] );
        $completedTasks = $db->selectField(
            'kanban_tasks',
            'COUNT(*)',
            $completedCondition,
            __METHOD__
        );
        
        // 完成率
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        
        // 平均完成时间（天）
        $avgCompletionTime = $this->getAverageCompletionTime( $db, $boardId, $dateCondition );
        
        return [
            'total_tasks' => (int)$totalTasks,
            'completed_tasks' => (int)$completedTasks,
            'completion_rate' => $completionRate,
            'avg_completion_time' => $avgCompletionTime
        ];
    }
    
    /**
     * 获取平均完成时间
     */
    private function getAverageCompletionTime( $db, $boardId, $dateCondition ) {
        // 构建查询条件
        $whereCondition = [
            'completed_at IS NOT NULL',
            'created_at IS NOT NULL'
        ];
        if ( $boardId ) {
            $whereCondition['board_id'] = $boardId;
        }
        
        $result = $db->select(
            'kanban_tasks',
            [ 'created_at', 'completed_at' ],
            $whereCondition,
            __METHOD__
        );
        
        $totalDays = 0;
        $count = 0;
        
        foreach ( $result as $row ) {
            $createdAt = strtotime( $row->created_at );
            $completedAt = strtotime( $row->completed_at );
            
            if ( $createdAt && $completedAt ) {
                $days = ($completedAt - $createdAt) / (24 * 60 * 60);
                $totalDays += $days;
                $count++;
            }
        }
        
        return $count > 0 ? round($totalDays / $count, 1) : 0;
    }
    
    /**
     * 获取按时间范围完成的任务
     */
    private function getTimeRangeTasks( $db, $boardId, $timeRange, $startDate = null, $endDate = null ) {
        // 计算时间范围
        $now = time();
        $timeRangeMap = [
            'week' => 7 * 24 * 60 * 60,
            'month' => 30 * 24 * 60 * 60,
            'quarter' => 90 * 24 * 60 * 60,
            'year' => 365 * 24 * 60 * 60,
            'all' => 0
        ];
        
        if ( $timeRange === 'custom' && $startDate && $endDate ) {
            // 自定义时间范围
            $startTime = strtotime($startDate . ' 00:00:00');
            $endTime = strtotime($endDate . ' 23:59:59');
        } else {
            // 预设时间范围
            $timeRangeSeconds = $timeRangeMap[$timeRange] ?? $timeRangeMap['month'];
            $startTime = $timeRangeSeconds > 0 ? $now - $timeRangeSeconds : 0;
            $endTime = strtotime(date('Y-m-d') . ' 23:59:59'); // 包含今天的结束时间
        }
        
        // 构建查询条件
        $whereCondition = [
            'completed_at IS NOT NULL',
            'completed_at >= ' . $db->addQuotes( date('Y-m-d H:i:s', $startTime ) ),
            'completed_at <= ' . $db->addQuotes( date('Y-m-d H:i:s', $endTime ) )
        ];
        if ( $boardId ) {
            $whereCondition['board_id'] = $boardId;
        }
        
        // 获取任务详情
        $completedTasks = $db->select(
            [ 'kanban_tasks', 'kanban_boards' ],
            [ 
                'kanban_tasks.task_id', 
                'kanban_tasks.title', 
                'kanban_tasks.completed_at', 
                'kanban_tasks.priority', 
                'kanban_tasks.color',
                'kanban_boards.board_name'
            ],
            array_merge($whereCondition, [
                'kanban_tasks.board_id = kanban_boards.board_id'
            ]),
            __METHOD__,
            [ 'ORDER BY' => 'kanban_tasks.completed_at DESC' ]
        );
        
        $tasks = [];
        foreach ( $completedTasks as $task ) {
            $tasks[] = [
                'task_id' => $task->task_id,
                'title' => $task->title,
                'completed_at' => $task->completed_at,
                'priority' => $task->priority,
                'color' => $task->color,
                'board_name' => $task->board_name
            ];
        }
        
        return [
            'time_range' => $timeRange,
            'start_date' => date('Y-m-d', $startTime),
            'end_date' => date('Y-m-d', $endTime),
            'completed_count' => count($tasks),
            'tasks' => $tasks
        ];
    }
    
    /**
     * 获取任务趋势数据（创建vs完成）
     */
    private function getTaskTrendData( $db, $boardId, $timeRange, $startDate = null, $endDate = null ) {
        // 计算时间范围
        $now = time();
        $timeRangeMap = [
            'week' => 7 * 24 * 60 * 60,
            'month' => 30 * 24 * 60 * 60,
            'quarter' => 90 * 24 * 60 * 60,
            'year' => 365 * 24 * 60 * 60,
            'all' => 0
        ];
        
        if ( $timeRange === 'custom' && $startDate && $endDate ) {
            // 自定义时间范围
            $startTime = strtotime($startDate . ' 00:00:00');
            $endTime = strtotime($endDate . ' 23:59:59');
        } else {
            // 预设时间范围
            $timeRangeSeconds = $timeRangeMap[$timeRange] ?? $timeRangeMap['month'];
            $startTime = $timeRangeSeconds > 0 ? $now - $timeRangeSeconds : 0;
            $endTime = strtotime(date('Y-m-d') . ' 23:59:59'); // 包含今天的结束时间
        }
        
        // 生成日期范围（按天分组）
        $days = [];
        $currentTime = $startTime;
        while ( $currentTime <= $endTime ) {
            $days[] = [
                'date' => date('Y-m-d', $currentTime),
                'label' => date('m/d', $currentTime),
                'created_count' => 0,
                'completed_count' => 0
            ];
            $currentTime += 24 * 60 * 60; // 增加一天
        }
        
        // 查询创建的任务
        $createdWhere = [
            'created_at >= ' . $db->addQuotes( date('Y-m-d H:i:s', $startTime ) ),
            'created_at <= ' . $db->addQuotes( date('Y-m-d H:i:s', $endTime ) )
        ];
        if ( $boardId ) {
            $createdWhere['board_id'] = $boardId;
        }
        
        $createdTasks = $db->select(
            'kanban_tasks',
            [ 'DATE(created_at) as date', 'COUNT(*) as count' ],
            $createdWhere,
            __METHOD__,
            [ 'GROUP BY' => 'DATE(created_at)' ]
        );
        
        foreach ( $createdTasks as $task ) {
            foreach ( $days as &$day ) {
                if ( $day['date'] === $task->date ) {
                    $day['created_count'] = (int)$task->count;
                    break;
                }
            }
        }
        
        // 查询完成的任务
        $completedWhere = [
            'completed_at IS NOT NULL',
            'completed_at >= ' . $db->addQuotes( date('Y-m-d H:i:s', $startTime ) ),
            'completed_at <= ' . $db->addQuotes( date('Y-m-d H:i:s', $endTime ) )
        ];
        if ( $boardId ) {
            $completedWhere['board_id'] = $boardId;
        }
        
        $completedTasks = $db->select(
            'kanban_tasks',
            [ 'DATE(completed_at) as date', 'COUNT(*) as count' ],
            $completedWhere,
            __METHOD__,
            [ 'GROUP BY' => 'DATE(completed_at)' ]
        );
        
        foreach ( $completedTasks as $task ) {
            foreach ( $days as &$day ) {
                if ( $day['date'] === $task->date ) {
                    $day['completed_count'] = (int)$task->count;
                    break;
                }
            }
        }
        
        return [
            'time_range' => $timeRange,
            'start_date' => date('Y-m-d', $startTime),
            'end_date' => date('Y-m-d', $endTime),
            'days' => $days
        ];
    }
    
    /**
     * 获取最近完成的任务
     */
    private function getRecentTasks( $db, $boardId, $dateCondition ) {
        // 构建查询条件
        $whereCondition = [ 'completed_at IS NOT NULL' ];
        if ( $boardId ) {
            $whereCondition['board_id'] = $boardId;
        }
        
        $result = $db->select(
            [ 'kanban_tasks', 'kanban_boards' ],
            [ 
                'kanban_tasks.task_id', 
                'kanban_tasks.title', 
                'kanban_tasks.completed_at',
                'kanban_boards.board_name'
            ],
            array_merge($whereCondition, [
                'kanban_tasks.board_id = kanban_boards.board_id'
            ]),
            __METHOD__,
            [
                'ORDER BY' => 'kanban_tasks.completed_at DESC',
                'LIMIT' => 20
            ]
        );
        
        $tasks = [];
        foreach ( $result as $row ) {
            $tasks[] = [
                'card_id' => $row->task_id,
                'card_title' => $row->title,
                'card_completed_at' => $row->completed_at,
                'board_name' => $row->board_name
            ];
        }
        
        return $tasks;
    }
}