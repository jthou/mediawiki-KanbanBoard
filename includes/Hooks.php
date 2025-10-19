<?php
/**
 * MediaWiki Kanban Board Extension
 * 
 * @file
 * @ingroup Extensions
 * @author Your Name
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\KanbanBoard\KanbanBoard;
use MediaWiki\Extension\KanbanBoard\KanbanColumn;
use MediaWiki\Extension\KanbanBoard\KanbanCard;
use MediaWiki\Extension\KanbanBoard\SearchHooks;
use MediaWiki\Extension\KanbanBoard\KanbanNamespaceHandler;
use MediaWiki\Extension\KanbanBoard\KanbanSearchAugmentor;

class Hooks {
    
    /**
     * Hook handler for ParserFirstCallInit
     * 注册看板解析器标签
     */
    public static function onParserFirstCallInit( $parser ) {
        $parser->setHook( 'kanban', [ self::class, 'renderKanbanTag' ] );
        return true;
    }
    
    /**
     * 渲染看板标签
     */
    public static function renderKanbanTag( $input, $args, $parser, $frame ) {
        $parser->getOutput()->addModules( [ 'ext.kanbanboard' ] );
        
        $boardId = $args['board'] ?? null;
        $boardName = $args['name'] ?? null;

        if ( $boardName && !$boardId ) {
            // 通过 kanban_name 或 kanban_slug 查找（不区分大小写）
            $dbr = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
            $needle = strtolower( $boardName );
            $orConds = $dbr->makeList( [
                'LOWER(kanban_name) = ' . $dbr->addQuotes( $needle ),
                'LOWER(kanban_slug) = ' . $dbr->addQuotes( $needle )
            ], LIST_OR );
            $row = $dbr->selectRow(
                'kanban_boards',
                [ 'board_id' ],
                [ $orConds ],
                __METHOD__
            );
            if ( $row ) {
                $boardId = (string)$row->board_id;
            }
        }

        if ( !$boardId ) {
            return '<div class="kanban-error">' . htmlspecialchars( wfMessage( 'kanbanboard-error' )->text() . ': board not found' ) . '</div>';
        }
        $readOnly = isset( $args['readonly'] ) ? 'true' : 'false';
        
        $html = '<div class="kanban-board" data-board-id="' . htmlspecialchars( $boardId ) . '" data-readonly="' . $readOnly . '">';
        $html .= '<div class="kanban-loading">' . wfMessage( 'kanbanboard-loading' )->text() . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Hook handler for SearchResultsAugment
     * 在搜索结果中添加看板任务
     */
    public static function onSearchResultsAugment( &$setAugmentors, &$rowAugmentors ) {
        // 暂时不使用这个钩子，改用 SpecialSearchResultsPrepend
        return true;
    }
    
    /**
     * Hook handler for SpecialSearchResultsPrepend
     * 在搜索结果页面顶部添加看板任务搜索提示
     */
    public static function onSpecialSearchResultsPrepend( $specialSearch, $output, $term ) {
        if ( empty( $term ) ) {
            return true;
        }
        
        $user = $specialSearch->getUser();
        if ( !$user->isRegistered() ) {
            return true;
        }
        
        // 获取看板任务搜索结果
        $kanbanResults = SearchHooks::searchKanbanTasks( $term, $user );
        
        if ( empty( $kanbanResults ) ) {
            return true;
        }
        
        // 构建看板任务搜索结果HTML
        $html = '<div class="kanban-search-results-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">';
        $html .= '<h3 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 18px;">📋 看板任务搜索结果</h3>';
        $html .= '<div class="kanban-search-results">';
        
        foreach ( $kanbanResults as $result ) {
            $priorityClass = $result->getPriority() ? 'kanban-task-priority-' . $result->getPriority() : '';
            $priorityColors = [
                'urgent' => '#e74c3c',
                'high' => '#f39c12', 
                'medium' => '#3498db',
                'low' => '#27ae60'
            ];
            $priorityColor = $priorityColors[$result->getPriority()] ?? '#95a5a6';
            
            $html .= '<div class="kanban-task-result" style="background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: box-shadow 0.2s;">';
            $html .= '<h4 style="margin: 0 0 8px 0; font-size: 16px;"><a href="' . $result->getUrl() . '" style="color: #2c3e50; text-decoration: none; font-weight: bold;">' . htmlspecialchars( $result->getTaskTitle() ) . '</a></h4>';
            $html .= '<p class="kanban-task-description" style="margin: 0 0 10px 0; color: #6c757d; font-size: 14px; line-height: 1.4;">' . $result->getSnippet() . '</p>';
            $html .= '<div class="kanban-task-meta" style="display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px;">';
            $html .= '<span style="background: #e9ecef; padding: 3px 8px; border-radius: 12px; color: #495057;">📋 ' . htmlspecialchars( $result->getBoardName() ) . '</span>';
            if ( $result->getStatusName() ) {
                $html .= '<span style="background: #d1ecf1; padding: 3px 8px; border-radius: 12px; color: #0c5460;">📊 ' . htmlspecialchars( $result->getStatusName() ) . '</span>';
            }
            if ( $result->getPriority() ) {
                $html .= '<span style="background: ' . $priorityColor . '; color: white; padding: 3px 8px; border-radius: 12px; font-weight: bold;">⚡ ' . htmlspecialchars( $result->getPriority() ) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<p style="margin: 15px 0 0 0; text-align: center;"><a href="' . \SpecialPage::getTitleFor( 'KanbanSearch' )->getLocalURL( [ 'search' => $term ] ) . '" style="color: #007bff; text-decoration: none; font-weight: bold;">🔍 查看更多看板任务</a></p>';
        $html .= '</div>';
        
        $output->addHTML( $html );
        return true;
    }
    
    /**
     * Hook handler for ContentHandlerForModelID
     * 为看板命名空间指定内容处理器
     */
    public static function onContentHandlerForModelID( $modelId, &$handler ) {
        return KanbanNamespaceHandler::onContentHandlerForModelID( $modelId, $handler );
    }
    
    /**
     * Hook handler for NamespaceIsMovable
     * 控制看板命名空间的页面是否可移动
     */
    public static function onNamespaceIsMovable( $ns, &$result ) {
        return KanbanNamespaceHandler::onNamespaceIsMovable( $ns, $result );
    }
    
    /**
     * Hook handler for TitleExists
     * 检查看板页面是否存在
     */
    public static function onTitleExists( $title, &$exists ) {
        return KanbanNamespaceHandler::onTitleExists( $title, $exists );
    }
    
    /**
     * Hook handler for ArticleFromTitle
     * 为看板命名空间创建自定义文章对象
     */
    public static function onArticleFromTitle( $title, &$article ) {
        return KanbanNamespaceHandler::onArticleFromTitle( $title, $article );
    }
    
    /**
     * Hook handler for SearchableNamespaces
     * 将看板命名空间添加到可搜索命名空间列表
     */
    public static function onSearchableNamespaces( &$namespaces ) {
        return KanbanNamespaceHandler::onSearchableNamespaces( $namespaces );
    }
    
    /**
     * Hook handler for SearchGetNearMatch
     * 处理看板命名空间的搜索匹配
     */
    public static function onSearchGetNearMatch( $term, &$title ) {
        return KanbanNamespaceHandler::onSearchGetNearMatch( $term, $title );
    }
    
    /**
     * Hook handler for SearchResultInitFromTitle
     * 为看板搜索结果创建自定义结果对象
     */
    public static function onSearchResultInitFromTitle( $title, &$result ) {
        return KanbanNamespaceHandler::onSearchResultInitFromTitle( $title, $result );
    }
}
