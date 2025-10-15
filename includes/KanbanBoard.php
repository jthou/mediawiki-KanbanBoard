<?php
/**
 * Kanban Board Model Class
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class KanbanBoard {
    
    private $db;
    private $boardId;
    private $data;
    
    public function __construct( $boardId = null ) {
        $this->db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnectionRef( DB_PRIMARY );
        $this->boardId = $boardId;
        if ( $boardId ) {
            $this->loadBoard();
        }
    }
    
    /**
     * 加载看板数据
     */
    public function loadBoard() {
        $row = $this->db->selectRow(
            'kanban_boards',
            '*',
            [ 'board_id' => $this->boardId ],
            __METHOD__
        );
        
        if ( $row ) {
            $this->data = (array)$row;
            return true;
        }
        return false;
    }
    
    /**
     * 创建新看板
     */
    public function createBoard( $name, $description, $ownerId, $permissions = 'private' ) {
        $this->db->insert(
            'kanban_boards',
            [
                'board_name' => $name,
                'board_description' => $description,
                'board_owner_id' => $ownerId,
                'board_permissions' => $permissions
            ],
            __METHOD__
        );
        
        $this->boardId = $this->db->insertId();
        $this->loadBoard();
        
        // 创建默认列
        $this->createDefaultColumns();
        
        return $this->boardId;
    }
    
    /**
     * 创建默认列
     */
    private function createDefaultColumns() {
        $defaultColumns = [
            [ 'name' => '待办', 'color' => '#e74c3c', 'order' => 1 ],
            [ 'name' => '进行中', 'color' => '#f39c12', 'order' => 2 ],
            [ 'name' => '已完成', 'color' => '#27ae60', 'order' => 3 ]
        ];
        
        foreach ( $defaultColumns as $column ) {
            $this->db->insert(
                'kanban_columns',
                [
                    'board_id' => $this->boardId,
                    'column_name' => $column['name'],
                    'column_color' => $column['color'],
                    'column_order' => $column['order']
                ],
                __METHOD__
            );
        }
    }
    
    /**
     * 获取看板的所有列
     */
    public function getColumns() {
        $result = $this->db->select(
            'kanban_columns',
            '*',
            [ 'board_id' => $this->boardId ],
            __METHOD__,
            [ 'ORDER BY' => 'column_order ASC' ]
        );
        
        $columns = [];
        foreach ( $result as $row ) {
            $columns[] = new KanbanColumn( $row );
        }
        
        return $columns;
    }
    
    /**
     * 检查用户权限
     */
    public function checkPermission( $userId, $permission = 'view' ) {
        // 看板所有者有所有权限
        if ( $this->data['board_owner_id'] == $userId ) {
            return true;
        }
        
        // 检查公开权限
        if ( $this->data['board_permissions'] === 'public' && $permission === 'view' ) {
            return true;
        }
        
        // 检查特定用户权限
        $row = $this->db->selectRow(
            'kanban_permissions',
            'permission_type',
            [
                'board_id' => $this->boardId,
                'user_id' => $userId
            ],
            __METHOD__
        );
        
        if ( $row ) {
            $userPermission = $row->permission_type;
            $permissionLevels = [ 'view' => 1, 'edit' => 2, 'admin' => 3 ];
            
            return $permissionLevels[$userPermission] >= $permissionLevels[$permission];
        }
        
        return false;
    }
    
    /**
     * 获取看板数据（用于API）
     */
    public function getBoardData() {
        if ( !$this->data ) {
            return null;
        }
        
        $columns = $this->getColumns();
        $columnsData = [];
        
        foreach ( $columns as $column ) {
            $columnsData[] = $column->getColumnData();
        }
        
        return [
            'board_id' => $this->boardId,
            'board_name' => $this->data['board_name'],
            'board_description' => $this->data['board_description'],
            'board_owner_id' => $this->data['board_owner_id'],
            'board_permissions' => $this->data['board_permissions'],
            'columns' => $columnsData
        ];
    }
}
