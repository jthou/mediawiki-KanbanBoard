<?php
/**
 * Kanban Article Class
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use Article;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\ContentRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\HookContainer\HookContainer;

class KanbanArticle extends Article {
    
    private $kanbanData;
    
    public function __construct( Title $title ) {
        parent::__construct( $title );
        $this->kanbanData = KanbanNamespaceHandler::getKanbanData( $title );
    }
    
    /**
     * 获取看板数据
     */
    public function getKanbanData() {
        return $this->kanbanData;
    }
    
    /**
     * 检查页面是否存在
     */
    public function exists() {
        return $this->kanbanData !== null;
    }
    
    /**
     * 获取页面内容
     */
    public function getContent() {
        if ( !$this->kanbanData ) {
            return null;
        }
        
        // 生成看板内容的文本表示
        $content = $this->generateKanbanContent();
        
        $contentHandler = ContentHandler::getForModelID( 'kanban-board' );
        return $contentHandler->unserializeContent( $content );
    }
    
    /**
     * 生成看板内容
     */
    private function generateKanbanContent() {
        if ( !$this->kanbanData ) {
            return '';
        }
        
        $content = "# " . $this->kanbanData->board_name . "\n\n";
        
        if ( $this->kanbanData->board_description ) {
            $content .= $this->kanbanData->board_description . "\n\n";
        }
        
        // 获取看板状态和任务
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        $statuses = $db->select(
            'kanban_statuses',
            '*',
            [ 'board_id' => $this->kanbanData->board_id ],
            __METHOD__,
            [ 'ORDER BY' => 'status_order ASC' ]
        );
        
        foreach ( $statuses as $status ) {
            $content .= "## " . $status->status_name . "\n\n";
            
            $tasks = $db->select(
                'kanban_tasks',
                '*',
                [ 
                    'status_id' => $status->status_id,
                    'deleted_at IS NULL'
                ],
                __METHOD__,
                [ 'ORDER BY' => 'task_order ASC' ]
            );
            
            foreach ( $tasks as $task ) {
                $priority = $this->getPriorityText( $task->priority );
                $content .= "- **" . $task->title . "** (" . $priority . ")\n";
                
                if ( $task->description ) {
                    $content .= "  " . $task->description . "\n";
                }
                
                if ( $task->due_date ) {
                    $content .= "  *截止日期: " . $task->due_date . "*\n";
                }
                
                $content .= "\n";
            }
        }
        
        return $content;
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
    
    /**
     * 获取页面HTML
     */
    public function getHTML() {
        $content = $this->getContent();
        
        if ( !$content ) {
            return '<div class="kanban-article-empty">看板不存在</div>';
        }
        
        // 使用看板内容处理器生成HTML
        if ( $content instanceof KanbanContent ) {
            return $content->getKanbanHTML();
        }
        
        return htmlspecialchars( $content->getText() );
    }
    
    /**
     * 检查用户是否有权限查看
     */
    public function userCan( $action, $user = null ) {
        if ( !$user ) {
            $user = $this->getContext()->getUser();
        }
        
        if ( !$this->kanbanData ) {
            return false;
        }
        
        // 检查看板权限
        if ( $this->kanbanData->visibility === 'public' ) {
            return true;
        }
        
        if ( $this->kanbanData->board_owner_id == $user->getId() ) {
            return true;
        }
        
        // 检查用户权限
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $permission = $db->selectField(
            'kanban_permissions',
            'permission_type',
            [
                'board_id' => $this->kanbanData->board_id,
                'user_id' => $user->getId()
            ],
            __METHOD__
        );
        
        return $permission !== false;
    }
    
    /**
     * 获取页面摘要
     */
    public function getSummary() {
        if ( !$this->kanbanData ) {
            return '';
        }
        
        $summary = $this->kanbanData->board_description ?: $this->kanbanData->board_name;
        
        if ( strlen( $summary ) > 200 ) {
            $summary = substr( $summary, 0, 200 ) . '...';
        }
        
        return $summary;
    }
    
    /**
     * 获取页面大小
     */
    public function getSize() {
        $content = $this->getContent();
        return $content ? $content->getSize() : 0;
    }
    
    /**
     * 获取最后修改时间
     */
    public function getTimestamp() {
        return $this->kanbanData ? $this->kanbanData->board_updated_at : null;
    }
}
