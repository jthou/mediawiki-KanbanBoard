<?php
/**
 * MediaWiki Kanban Board Search Integration
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;
use MediaWiki\Search\SearchResultSet;
use MediaWiki\Search\SearchResult;
use MediaWiki\Title\Title;
use SpecialSearch;

class SearchHooks {
    
    /**
     * Hook handler for SearchResultsAugment
     * 在搜索结果中添加看板任务
     */
    public static function onSearchResultsAugment( $searchResultSet, $searchEngine, $term, $user ) {
        // 只对文本搜索生效
        if ( !$searchEngine instanceof \SearchEngine ) {
            return true;
        }
        
        // 检查是否搜索看板命名空间
        $namespaces = $searchEngine->getNamespaces();
        $searchKanbanNamespace = in_array( 3000, $namespaces ) || empty( $namespaces );
        
        if ( $searchKanbanNamespace ) {
            // 获取看板任务搜索结果
            $kanbanResults = self::searchKanbanTasks( $term, $user );
            
            if ( !empty( $kanbanResults ) ) {
                // 将看板任务结果添加到搜索结果中
                foreach ( $kanbanResults as $result ) {
                    $searchResultSet->addInterwikiResults( $result, SearchResultSet::SECONDARY_RESULTS, 'kanban' );
                }
            }
        }
        
        return true;
    }
    
    /**
     * 搜索看板任务
     */
    public static function searchKanbanTasks( $term, $user ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        // 构建搜索条件
        $searchConditions = [
            'kanban_tasks.deleted_at IS NULL', // 排除已删除的任务
            $db->makeList( [
                'kanban_tasks.title LIKE ' . $db->addQuotes( '%' . $term . '%' ),
                'kanban_tasks.description LIKE ' . $db->addQuotes( '%' . $term . '%' ),
                'kanban_boards.board_name LIKE ' . $db->addQuotes( '%' . $term . '%' ),
                'kanban_statuses.status_name LIKE ' . $db->addQuotes( '%' . $term . '%' )
            ], LIST_OR )
        ];
        
        // 简化的权限检查 - 只检查用户是否有权限访问看板
        $accessibleBoards = self::getUserAccessibleBoardIds( $user->getId() );
        
        if ( empty( $accessibleBoards ) ) {
            return []; // 用户没有权限访问任何看板
        }
        
        $searchConditions[] = 'kanban_tasks.board_id IN (' . $db->makeList( $accessibleBoards ) . ')';
        
        // 执行搜索查询
        $results = $db->select(
            [
                'kanban_tasks',
                'kanban_boards',
                'kanban_statuses'
            ],
            [
                'kanban_tasks.task_id',
                'kanban_tasks.title',
                'kanban_tasks.description',
                'kanban_tasks.priority',
                'kanban_tasks.due_date',
                'kanban_tasks.created_at',
                'kanban_boards.board_name',
                'kanban_boards.board_id',
                'kanban_statuses.status_name'
            ],
            $searchConditions,
            __METHOD__,
            [
                'ORDER BY' => 'kanban_tasks.created_at DESC',
                'LIMIT' => 20 // 限制结果数量
            ],
            [
                'kanban_boards' => [ 'INNER JOIN', 'kanban_tasks.board_id = kanban_boards.board_id' ],
                'kanban_statuses' => [ 'INNER JOIN', 'kanban_tasks.status_id = kanban_statuses.status_id' ]
            ]
        );
        
        $searchResults = [];
        foreach ( $results as $row ) {
            $searchResults[] = self::createSearchResult( $row, $term );
        }
        
        return $searchResults;
    }
    
    /**
     * 创建搜索结果对象
     */
    private static function createSearchResult( $row, $term ) {
        // 创建虚拟标题，指向看板页面
        $title = Title::makeTitle( NS_SPECIAL, 'KanbanBoard' );
        $title->setFragment( 'task-' . $row->task_id );
        
        // 创建搜索结果
        $result = new KanbanSearchResult( $title, $row, $term );
        
        return $result;
    }
    
    /**
     * Hook handler for SpecialSearchResultsPrepend
     * 在搜索结果页面顶部添加看板任务搜索提示
     */
    public static function onSpecialSearchResultsPrepend( $specialSearch, $output, $term ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        // 检查是否有看板任务匹配
        $taskCount = $db->selectField(
            [
                'kanban_tasks',
                'kanban_boards'
            ],
            'COUNT(*)',
            [
                'kanban_tasks.deleted_at IS NULL',
                'kanban_boards.board_id = kanban_tasks.board_id',
                $db->makeList( [
                    'kanban_tasks.title LIKE ' . $db->addQuotes( '%' . $term . '%' ),
                    'kanban_tasks.description LIKE ' . $db->addQuotes( '%' . $term . '%' ),
                    'kanban_boards.board_name LIKE ' . $db->addQuotes( '%' . $term . '%' )
                ], LIST_OR )
            ],
            __METHOD__
        );
        
        if ( $taskCount > 0 ) {
            $output->addHTML( 
                '<div class="kanban-search-notice">' .
                '<strong>找到 ' . $taskCount . ' 个相关看板任务</strong> - ' .
                '<a href="' . Title::makeTitle( NS_SPECIAL, 'KanbanBoard' )->getLocalURL( [ 'search' => $term ] ) . '">查看看板任务</a>' .
                '</div>'
            );
        }
        
        return true;
    }
    
    /**
     * 获取用户有权限访问的看板ID列表
     */
    private static function getUserAccessibleBoardIds( $userId ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
        
        // 获取用户有权限的看板ID
        $boardIds = $db->selectFieldValues(
            'kanban_boards',
            'board_id',
            $db->makeList( [
                'board_owner_id' => $userId, // 用户拥有的看板
                'visibility' => 'public', // 公开的看板
                'EXISTS (SELECT 1 FROM ' . $db->tableName( 'kanban_permissions' ) . ' WHERE board_id = kanban_boards.board_id AND user_id = ' . $db->addQuotes( $userId ) . ')' // 用户有权限的看板
            ], LIST_OR ),
            __METHOD__
        );
        
        return $boardIds ?: [];
    }
}
