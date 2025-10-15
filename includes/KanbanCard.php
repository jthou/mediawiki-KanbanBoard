<?php
/**
 * Kanban Card Model Class
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;

class KanbanCard {
    
    private $db;
    private $data;
    
    public function __construct( $data ) {
        $this->db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnectionRef( DB_PRIMARY );
        $this->data = (array)$data;
    }
    
    /**
     * 更新卡片
     */
    public function updateCard( $title, $description, $assigneeId = null, $priority = 'medium', $dueDate = null ) {
        $this->db->update(
            'kanban_cards',
            [
                'card_title' => $title,
                'card_description' => $description,
                'card_assignee_id' => $assigneeId,
                'card_priority' => $priority,
                'card_due_date' => $dueDate
            ],
            [ 'card_id' => $this->data['card_id'] ],
            __METHOD__
        );
        
        $this->loadCard();
        return true;
    }
    
    /**
     * 移动卡片到新列
     */
    public function moveToColumn( $newColumnId, $newOrder ) {
        $this->db->update(
            'kanban_cards',
            [
                'column_id' => $newColumnId,
                'card_order' => $newOrder
            ],
            [ 'card_id' => $this->data['card_id'] ],
            __METHOD__
        );
        
        $this->data['column_id'] = $newColumnId;
        $this->data['card_order'] = $newOrder;
        
        return true;
    }
    
    /**
     * 删除卡片
     */
    public function deleteCard() {
        $this->db->delete(
            'kanban_cards',
            [ 'card_id' => $this->data['card_id'] ],
            __METHOD__
        );
        
        return true;
    }
    
    /**
     * 添加评论
     */
    public function addComment( $userId, $commentText ) {
        $this->db->insert(
            'kanban_comments',
            [
                'card_id' => $this->data['card_id'],
                'user_id' => $userId,
                'comment_text' => $commentText
            ],
            __METHOD__
        );
        
        return $this->db->insertId();
    }
    
    /**
     * 获取卡片评论
     */
    public function getComments() {
        $result = $this->db->select(
            [ 'kanban_comments', 'user' ],
            [
                'comment_id',
                'comment_text',
                'comment_created_at',
                'user_name',
                'user_real_name'
            ],
            [ 'card_id' => $this->data['card_id'] ],
            __METHOD__,
            [ 'ORDER BY' => 'comment_created_at ASC' ],
            [ 'user' => [ 'LEFT JOIN', 'kanban_comments.user_id = user.user_id' ] ]
        );
        
        $comments = [];
        foreach ( $result as $row ) {
            $comments[] = (array)$row;
        }
        
        return $comments;
    }
    
    /**
     * 加载卡片数据
     */
    private function loadCard() {
        $row = $this->db->selectRow(
            'kanban_cards',
            '*',
            [ 'card_id' => $this->data['card_id'] ],
            __METHOD__
        );
        
        if ( $row ) {
            $this->data = (array)$row;
        }
    }
    
    /**
     * 获取卡片数据（用于API）
     */
    public function getCardData() {
        return [
            'card_id' => $this->data['card_id'],
            'card_title' => $this->data['card_title'],
            'card_description' => $this->data['card_description'],
            'card_assignee_id' => $this->data['card_assignee_id'],
            'card_creator_id' => $this->data['card_creator_id'],
            'card_priority' => $this->data['card_priority'],
            'card_color' => $this->data['card_color'],
            'card_order' => $this->data['card_order'],
            'card_due_date' => $this->data['card_due_date'],
            'card_created_at' => $this->data['card_created_at'],
            'card_updated_at' => $this->data['card_updated_at']
        ];
    }
}
