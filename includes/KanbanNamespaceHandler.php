<?php
/**
 * Kanban Namespace Handler
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\HookContainer\HookContainer;

class KanbanNamespaceHandler {
    
    const NS_KANBAN = 3000;
    const NS_KANBAN_TALK = 3001;
    
    /**
     * Hook handler for ContentHandlerForModelID
     * 为看板命名空间指定内容处理器
     */
    public static function onContentHandlerForModelID( $modelId, &$handler ) {
        if ( $modelId === 'kanban-board' ) {
            $handler = new KanbanContentHandler();
            return false;
        }
        return true;
    }
    
    /**
     * Hook handler for NamespaceIsMovable
     * 控制看板命名空间的页面是否可移动
     */
    public static function onNamespaceIsMovable( $ns, &$result ) {
        if ( $ns === self::NS_KANBAN || $ns === self::NS_KANBAN_TALK ) {
            $result = false; // 看板页面不可移动
            return false; // 返回false表示我们已经处理了这个命名空间
        }
        // 对于其他命名空间，不修改$result，让MediaWiki使用默认行为
        return true;
    }
    
    /**
     * Hook handler for TitleExists
     * 检查看板页面是否存在
     */
    public static function onTitleExists( $title, &$exists ) {
        if ( $title->getNamespace() === self::NS_KANBAN ) {
            $exists = self::checkKanbanPageExists( $title );
            return false;
        }
        return true;
    }
    
    /**
     * Hook handler for ArticleFromTitle
     * 为看板命名空间创建自定义文章对象
     */
    public static function onArticleFromTitle( $title, &$article ) {
        if ( $title->getNamespace() === self::NS_KANBAN ) {
            $article = new KanbanArticle( $title );
            return false;
        }
        return true;
    }
    
    /**
     * Hook handler for SearchableNamespaces
     * 将看板命名空间添加到可搜索命名空间列表
     */
    public static function onSearchableNamespaces( &$namespaces ) {
        $namespaces[self::NS_KANBAN] = 'kanban';
        $namespaces[self::NS_KANBAN_TALK] = 'kanban_talk';
        return true;
    }
    
    /**
     * Hook handler for SearchGetNearMatch
     * 处理看板命名空间的搜索匹配
     */
    public static function onSearchGetNearMatch( $term, &$title ) {
        // 检查是否匹配看板名称
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $board = $db->selectRow(
            'kanban_boards',
            [ 'board_id', 'kanban_name', 'kanban_slug' ],
            $db->makeList( [
                'kanban_name' => $term,
                'kanban_slug' => $term
            ], LIST_OR ),
            __METHOD__
        );
        
        if ( $board ) {
            $title = Title::makeTitle( self::NS_KANBAN, $board->kanban_name );
            return false;
        }
        
        return true;
    }
    
    /**
     * Hook handler for SearchResultInitFromTitle
     * 为看板搜索结果创建自定义结果对象
     */
    public static function onSearchResultInitFromTitle( $title, &$result ) {
        if ( $title->getNamespace() === self::NS_KANBAN ) {
            $result = new KanbanSearchResult( $title, null, '' );
            return false;
        }
        return true;
    }
    
    /**
     * 检查看板页面是否存在
     */
    private static function checkKanbanPageExists( Title $title ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $boardName = $title->getText();
        
        $exists = $db->selectField(
            'kanban_boards',
            'board_id',
            $db->makeList( [
                'kanban_name' => $boardName,
                'kanban_slug' => $boardName
            ], LIST_OR ),
            __METHOD__
        );
        
        return (bool)$exists;
    }
    
    /**
     * 获取看板数据
     */
    public static function getKanbanData( Title $title ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        $boardName = $title->getText();
        
        $board = $db->selectRow(
            'kanban_boards',
            '*',
            $db->makeList( [
                'kanban_name' => $boardName,
                'kanban_slug' => $boardName
            ], LIST_OR ),
            __METHOD__
        );
        
        return $board;
    }
}
