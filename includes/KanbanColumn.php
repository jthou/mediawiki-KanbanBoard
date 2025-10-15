<?php
/**
 * Kanban Column Model Class
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;

class KanbanColumn {
    
    private $db;
    private $data;
    
    public function __construct( $data ) {
        $this->db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnectionRef( DB_PRIMARY );
        $this->data = (array)$data;
    }
    
    /**
     * 获取列的所有卡片
     */
    public function getCards() {
        $result = $this->db->select(
            'kanban_cards',
            '*',
            [ 'column_id' => $this->data['column_id'] ],
            __METHOD__,
            [ 'ORDER BY' => 'card_order ASC' ]
        );
        
        $cards = [];
        foreach ( $result as $row ) {
            $cards[] = new KanbanCard( $row );
        }
        
        return $cards;
    }
    
    /**
     * 添加新卡片
     */
    public function addCard( $title, $description, $creatorId, $assigneeId = null, $priority = 'medium' ) {
        // 获取下一个排序位置
        $maxOrder = $this->db->selectField(
            'kanban_cards',
            'MAX(card_order)',
            [ 'column_id' => $this->data['column_id'] ],
            __METHOD__
        );
        
        $this->db->insert(
            'kanban_cards',
            [
                'column_id' => $this->data['column_id'],
                'card_title' => $title,
                'card_description' => $description,
                'card_creator_id' => $creatorId,
                'card_assignee_id' => $assigneeId,
                'card_priority' => $priority,
                'card_order' => ( $maxOrder ?: 0 ) + 1
            ],
            __METHOD__
        );
        
        return $this->db->insertId();
    }
    
    /**
     * 移动卡片到新位置
     */
    public function moveCard( $cardId, $newOrder ) {
        $this->db->update(
            'kanban_cards',
            [ 'card_order' => $newOrder ],
            [ 'card_id' => $cardId ],
            __METHOD__
        );
        
        return true;
    }
    
    /**
     * 获取列数据（用于API）
     */
    public function getColumnData() {
        $cards = $this->getCards();
        $cardsData = [];
        
        foreach ( $cards as $card ) {
            $cardsData[] = $card->getCardData();
        }
        
        return [
            'column_id' => $this->data['column_id'],
            'column_name' => $this->data['column_name'],
            'column_description' => $this->data['column_description'],
            'column_color' => $this->data['column_color'],
            'column_order' => $this->data['column_order'],
            'cards' => $cardsData
        ];
    }
}
