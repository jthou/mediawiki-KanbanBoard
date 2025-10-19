<?php
/**
 * Custom Search Result for Kanban Tasks
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use SearchResult;
use MediaWiki\Title\Title;

class KanbanSearchResult extends SearchResult {
    
    private $taskData;
    private $searchTerm;
    protected $mTitle;
    
    public function __construct( Title $title, $taskData, $searchTerm ) {
        parent::__construct();
        $this->mTitle = $title;
        $this->taskData = $taskData;
        $this->searchTerm = $searchTerm;
    }
    
    /**
     * 获取搜索结果标题
     */
    public function getTitle() {
        return $this->mTitle;
    }
    
    /**
     * 获取搜索结果文本
     */
    public function getTextSnippet( $terms = [] ) {
        $text = $this->taskData->description ?: $this->taskData->title;
        
        // 高亮搜索词
        if ( !empty( $this->searchTerm ) ) {
            $text = str_ireplace( 
                $this->searchTerm, 
                '<strong>' . $this->searchTerm . '</strong>', 
                $text 
            );
        }
        
        // 截取文本长度
        if ( strlen( $text ) > 200 ) {
            $text = substr( $text, 0, 200 ) . '...';
        }
        
        return $text;
    }
    
    /**
     * 获取搜索结果摘要（别名方法）
     */
    public function getSnippet() {
        return $this->getTextSnippet();
    }
    
    /**
     * 获取搜索结果摘要
     */
    public function getSectionTitle() {
        return '看板任务';
    }
    
    /**
     * 获取看板名称
     */
    public function getBoardName() {
        return $this->taskData->board_name;
    }
    
    /**
     * 获取状态名称
     */
    public function getStatusName() {
        return $this->taskData->status_name;
    }
    
    /**
     * 获取优先级
     */
    public function getPriority() {
        return $this->taskData->priority;
    }
    
    /**
     * 获取任务标题
     */
    public function getTaskTitle() {
        return $this->taskData->title;
    }
    
    /**
     * 获取搜索结果URL
     */
    public function getURL() {
        // 指向看板页面，并传递任务ID参数
        return \SpecialPage::getTitleFor( 'KanbanBoard' )->getLocalURL( [
            'board' => $this->taskData->board_id,
            'task' => $this->taskData->task_id
        ] );
    }
    
    /**
     * 获取搜索结果大小
     */
    public function getSize() {
        return strlen( $this->taskData->title ) + strlen( $this->taskData->description ?: '' );
    }
    
    /**
     * 获取搜索结果时间戳
     */
    public function getTimestamp() {
        return $this->taskData->created_at;
    }
    
    /**
     * 获取搜索结果相关度分数
     */
    public function getScore() {
        $score = 0;
        
        // 标题匹配得分更高
        if ( stripos( $this->taskData->title, $this->searchTerm ) !== false ) {
            $score += 100;
        }
        
        // 描述匹配得分
        if ( stripos( $this->taskData->description ?: '', $this->searchTerm ) !== false ) {
            $score += 50;
        }
        
        // 看板名称匹配得分
        if ( stripos( $this->taskData->board_name, $this->searchTerm ) !== false ) {
            $score += 30;
        }
        
        // 状态名称匹配得分
        if ( stripos( $this->taskData->status_name, $this->searchTerm ) !== false ) {
            $score += 20;
        }
        
        return $score;
    }
    
    /**
     * 获取搜索结果元数据
     */
    public function getMetadata() {
        return [
            'type' => 'kanban_task',
            'task_id' => $this->taskData->task_id,
            'board_id' => $this->taskData->board_id,
            'board_name' => $this->taskData->board_name,
            'status_name' => $this->taskData->status_name,
            'priority' => $this->taskData->priority,
            'due_date' => $this->taskData->due_date
        ];
    }
    
    /**
     * 检查是否应该显示在搜索结果中
     */
    public function isBrokenTitle() {
        return false;
    }
    
    /**
     * 检查是否是重定向
     */
    public function isRedirect() {
        return false;
    }
}
