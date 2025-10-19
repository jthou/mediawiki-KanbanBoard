<?php
/**
 * Kanban Content Handler
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;

class KanbanContentHandler extends ContentHandler {
    
    public function __construct() {
        parent::__construct( 'kanban-board' );
    }
    
    /**
     * 获取内容模型ID
     */
    public function getModelID() {
        return 'kanban-board';
    }
    
    /**
     * 创建空内容
     */
    public function makeEmptyContent() {
        return new KanbanContent( '' );
    }
    
    /**
     * 从文本创建内容
     */
    public function unserializeContent( $text, $format = null ) {
        return new KanbanContent( $text );
    }
    
    /**
     * 获取支持的格式
     */
    public function getSupportedFormats() {
        return [ CONTENT_FORMAT_TEXT, CONTENT_FORMAT_WIKITEXT ];
    }
    
    /**
     * 获取默认格式
     */
    public function getDefaultFormat() {
        return CONTENT_FORMAT_TEXT;
    }
    
    /**
     * 检查格式是否支持
     */
    public function isSupportedFormat( $format ) {
        return in_array( $format, $this->getSupportedFormats() );
    }
    
    /**
     * 序列化内容
     */
    public function serializeContent( $content, $format = null ) {
        if ( $content instanceof KanbanContent ) {
            return $content->getText();
        }
        return '';
    }
    
    /**
     * 获取搜索索引字段
     */
    public function getFieldsForSearchIndex( $engine ) {
        $fields = parent::getFieldsForSearchIndex( $engine );
        
        $fields['kanban_title'] = $engine->makeSearchFieldMapping( 'kanban_title', \SearchIndexField::INDEX_TYPE_TEXT );
        $fields['kanban_title']->setFlag( \SearchIndexField::FLAG_SCORING );
        
        $fields['kanban_description'] = $engine->makeSearchFieldMapping( 'kanban_description', \SearchIndexField::INDEX_TYPE_TEXT );
        
        $fields['kanban_status'] = $engine->makeSearchFieldMapping( 'kanban_status', \SearchIndexField::INDEX_TYPE_KEYWORD );
        
        return $fields;
    }
    
    /**
     * 获取搜索索引数据
     */
    public function getDataForSearchIndex( $page, $parserOutput, $engine, $revision = null ) {
        $fields = parent::getDataForSearchIndex( $page, $parserOutput, $engine, $revision );
        
        // 获取看板数据
        $boardData = KanbanNamespaceHandler::getKanbanData( $page->getTitle() );
        
        if ( $boardData ) {
            $fields['kanban_title'] = $boardData->board_name;
            $fields['kanban_description'] = $boardData->board_description;
            
            // 获取看板状态
            $db = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
            $statuses = $db->selectFieldValues(
                'kanban_statuses',
                'status_name',
                [ 'board_id' => $boardData->board_id ],
                __METHOD__
            );
            
            $fields['kanban_status'] = implode( ' ', $statuses );
            
            // 获取任务数据
            $tasks = $db->select(
                'kanban_tasks',
                [ 'title', 'description' ],
                [ 'board_id' => $boardData->board_id, 'deleted_at IS NULL' ],
                __METHOD__
            );
            
            $taskTitles = [];
            $taskDescriptions = [];
            
            foreach ( $tasks as $task ) {
                $taskTitles[] = $task->title;
                if ( $task->description ) {
                    $taskDescriptions[] = $task->description;
                }
            }
            
            $fields['text'] = implode( ' ', array_merge( $taskTitles, $taskDescriptions ) );
        }
        
        return $fields;
    }
}
